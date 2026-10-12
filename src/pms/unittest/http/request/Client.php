<?php
declare(strict_types=1);
namespace pms\unittest\http\request;

use pms\unittest\http\runtime\TestRun;
use pms\unittest\http\contract\RequestIdentity;
use pms\unittest\http\response\Response;

use pms\helper\fetch\Client as FetchClient;

/** 基于 helper-fetch 发送真实 HTTP 请求，公共头由宿主按每次请求提供。 */
class Client
{
    private readonly TestRun $run;
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $headers = [],
        private readonly ?\Closure $headerProvider = null,
        ?TestRun $run = null,
        private readonly int $timeout = 30,
    ) {
        if (!in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) { throw new \InvalidArgumentException('baseUrl 必须是 HTTP 地址'); }
        $this->run = $run ?? new TestRun();
    }
    /** 返回当前流程统计。 */
    public function run(): TestRun { return $this->run; }
    public function baseUrl(): string { return $this->baseUrl; }
    /** 将枚举转换放在实际 HTTP 序列化边界。 */
    private function wire(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) { return $value->value; }
        if (is_array($value)) { return array_map($this->wire(...), $value); }
        return $value;
    }
    /** 按原生 PHP 表单键编码嵌套 multipart 字段。 */
    private function multipart(array $parameters, string $prefix = ''): array
    {
        $fields = [];
        foreach ($parameters as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                if ($value === []) { throw new \InvalidArgumentException('multipart 无法表示空数组: ' . $name); }
                $fields = array_replace($fields, $this->multipart($value, $name));
            } elseif (is_scalar($value)) {
                $fields[$name] = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
            } else {
                throw new \InvalidArgumentException('multipart 字段必须是标量、数组: ' . $name);
            }
        }
        return $fields;
    }

    /** 发出一个请求；路径占位参数从参数中提取，其余成为 query、JSON、multipart。 */
    public function request(string $method, string $path, array $parameters = [], array $files = [], array $headers = [], ?RequestIdentity $identity = null, ?\Closure $prepareHeaders = null): Response
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) { throw new \InvalidArgumentException('不支持的 HTTP 方法: ' . $method); }
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) { throw new \InvalidArgumentException('接口 path 必须是站点内绝对路径'); }
        $parameters = $this->wire($parameters);
        $identityParameters = $identity === null ? [] : ['terminal' => $identity->terminal()];
        $path = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $m) use (&$parameters, $identityParameters): string {
            $value = array_key_exists($m[1], $parameters) ? $parameters[$m[1]] : ($identityParameters[$m[1]] ?? null);
            if (!is_scalar($value)) { throw new \InvalidArgumentException('缺少路径参数: ' . $m[1]); }
            $value = str_replace('%3A', ':', rawurlencode((string)$value));
            unset($parameters[$m[1]]);
            return $value;
        }, $path);
        $url = rtrim($this->baseUrl, '/') . $path;
        $identityHeaders = $identity?->headers($this, $method, $path, $parameters) ?? [];
        $common = $this->headerProvider === null ? [] : ($this->headerProvider)($method, $path, $parameters);
        $prepared = $prepareHeaders === null ? [] : $prepareHeaders($method, $path, $parameters);
        $allHeaders = array_replace(array_change_key_case($this->headers, CASE_LOWER), array_change_key_case($common, CASE_LOWER), array_change_key_case($identityHeaders, CASE_LOWER), array_change_key_case($headers, CASE_LOWER), array_change_key_case($prepared, CASE_LOWER));
        if ($method === 'HEAD' && $parameters !== []) { $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($parameters); }
        $fetch = (new FetchClient())->setUrl($url)->setMethod($method)->setTimeout($this->timeout);
        // 测试请求不自动跳转，保留目标接口真实状态和响应。
        $fetch->addOptions(CURLOPT_FOLLOWLOCATION, false);
        if ($files !== []) {
            if (in_array($method, ['GET', 'HEAD'], true)) { throw new \InvalidArgumentException('GET、HEAD 不支持文件请求体'); }
            foreach ($files as $file) { if (!$file instanceof \CURLFile) { throw new \InvalidArgumentException('上传文件必须是 CURLFile'); } }
            unset($allHeaders['content-type']);
            $fetch->addOptions(CURLOPT_POSTFIELDS, array_replace($this->multipart($parameters), $files));
        } elseif ($method === 'GET') {
            $fetch->setData($parameters);
        } elseif ($method === 'HEAD') {
            $fetch->addOptions(CURLOPT_NOBODY, true);
        } else {
            $allHeaders['content-type'] = 'application/json';
            $fetch->addOptions(CURLOPT_POSTFIELDS, json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        foreach ($allHeaders as $key => $value) { $fetch->addHeader($key, $value); }
        $this->run->requested();
        $response = $fetch->execute();
        if ($response->getErrno() !== 0) { throw new \RuntimeException($method . ' ' . $url . ' 传输失败: ' . $response->getError(), $response->getErrno()); }
        // 在 fetch 句柄释放前取得状态和正文，返回结果不依赖 cURL 句柄生命周期。
        return $this->createResponse((int)$response->getInfo(CURLINFO_HTTP_CODE), (string)$response->getBody(), $method, (string)$response->getInfo(CURLINFO_EFFECTIVE_URL), $this->run);
    }
    /** 宿主可返回自身业务响应断言类。 */
    protected function createResponse(int $status, string $body, string $method, string $url, TestRun $run): Response
    {
        return new Response($status, $body, $method, $url, $run);
    }
}

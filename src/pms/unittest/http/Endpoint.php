<?php
declare(strict_types=1);
namespace pms\unittest\http;

use pms\unittest\http\contract\RequestIdentity;
use pms\unittest\http\request\InterfaceRoute;
use pms\unittest\http\runtime\TestRun;
use pms\unittest\http\request\Client;
use pms\unittest\http\runtime\TestScope;
use pms\unittest\http\response\Response;

/** 公共请求基建：身份、运行时路由、客户端、请求头/参数钩子和实际发送。 */
abstract class Endpoint
{
    protected const INTERFACE_CLASS = '';
    protected const HTTP_METHOD = 'GET';
    public function __construct(protected readonly RequestIdentity $identity) {}

    /** 最终发送时才解析地址，生成类不保存 path 或终端。 */
    protected function path(): string
    {
        $prefix = config('unittest-http.request_prefix');
        if (!is_string($prefix)) { throw new \InvalidArgumentException('utests/config.php 必须定义 request_prefix'); }
        return InterfaceRoute::path(static::INTERFACE_CLASS, $this->identity, $prefix);
    }
    /** 宿主可返回业务响应适配客户端，包默认使用纯 HTTP 客户端。 */
    protected function createClient(TestRun $run): Client
    {
        $settings = config('unittest-http');
        return new Client($settings['base_url'], $settings['headers'], run: $run, timeout: $settings['timeout']);
    }
    /** 在认证准备完成后补充请求头，适合时间签名等实际业务头。 */
    protected function headers(string $method, string $path, array $parameters): array { return []; }
    /** 业务需要补充公共参数时，在序列化前注入。 */
    protected function parameters(array $parameters): array { return $parameters; }
    /** 按接口声明发送；多方法接口由调用方明确指定当前方法。 */
    protected function send(array $parameters, array $files, ?string $method = null): Response
    {
        $methods = is_array(static::HTTP_METHOD) ? static::HTTP_METHOD : [static::HTTP_METHOD];
        if ($method === null) {
            if (count($methods) !== 1) { throw new \InvalidArgumentException('多方法接口必须指定请求方法'); }
            $method = $methods[0];
        }
        $method = strtoupper($method);
        if (!in_array($method, $methods, true)) { throw new \InvalidArgumentException('接口未声明请求方法: ' . $method); }
        $parameters = $this->parameters($parameters);
        $path = $this->path();
        $client = $this->createClient(TestScope::current());
        return $client->request($method, $path, $parameters, $files,
            identity: $this->identity, prepareHeaders: $this->headers(...));
    }
}

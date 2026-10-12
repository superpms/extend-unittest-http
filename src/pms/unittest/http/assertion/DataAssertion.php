<?php
declare(strict_types=1);
namespace pms\unittest\http\assertion;

use pms\unittest\http\runtime\TestRun;
use pms\unittest\http\exception\AssertionFailed;

/** 对实际数据作局部路径断言，供 HTTP 响应、数据库查询及业务计算共用。 */
class DataAssertion
{
    public function __construct(
        private readonly mixed $data,
        protected readonly TestRun $run,
        private readonly string $description = 'data',
    ) {}
    protected function assertionData(): mixed { return $this->data; }
    /** 查询路径；空路径表示完整结果，支持数组下标以及数组形式的原始键。 */
    private function locate(string|array $path): array
    {
        $keys = is_array($path) ? $path : ($path === '' ? [] : explode('.', $path));
        $value = $this->assertionData();
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) { return [false, null]; }
            $value = $value[$key];
        }
        return [true, $value];
    }
    /** 读取指定响应路径，缺失路径抛出断言异常，null 保持原值。 */
    public function value(string|array $path = ''): mixed
    {
        [$exists, $value] = $this->locate($path);
        if (!$exists) { throw new AssertionFailed($this->context($path) . ' 字段缺失'); }
        return $value;
    }
    /** 形成不包含认证请求头的断言定位。 */
    private function context(string|array $path): string
    {
        return $this->description . ' @ ' . (is_array($path) ? json_encode($path, JSON_UNESCAPED_UNICODE) : $path);
    }
    /** 严格比较指定结果路径的实际类型和值。 */
    public function assertEquals(string|array $path, mixed $expected): static
    {
        [$exists, $actual] = $this->locate($path);
        $this->run->check($exists && $actual === $expected, $this->context($path), $expected, $exists ? $actual : '[字段缺失]');
        return $this;
    }
    /** 断言字段存在，允许字段值为 null。 */
    public function assertExists(string|array $path): static
    {
        [$exists] = $this->locate($path);
        $this->run->check($exists, $this->context($path), '字段存在', $exists ? '字段存在' : '字段缺失');
        return $this;
    }
    /** 断言字段不存在。 */
    public function assertMissing(string|array $path): static
    {
        [$exists, $actual] = $this->locate($path);
        $this->run->check(!$exists, $this->context($path), '字段缺失', $exists ? $actual : '字段缺失');
        return $this;
    }
    /** 断言路径值的 PHP 类型：int、float、string、bool、array、null。 */
    public function assertType(string|array $path, string $type): static
    {
        if (!in_array($type, ['int', 'float', 'string', 'bool', 'array', 'null'], true)) { throw new \InvalidArgumentException('不支持的断言类型: ' . $type); }
        $value = $this->value($path);
        $this->run->check(get_debug_type($value) === $type, $this->context($path), $type, get_debug_type($value));
        return $this;
    }
    /** 断言指定路径的列表长度。 */
    public function assertCount(string|array $path, int $expected): static
    {
        $value = $this->value($path);
        $this->run->check(is_array($value) && count($value) === $expected, $this->context($path), $expected, is_array($value) ? count($value) : get_debug_type($value));
        return $this;
    }
    /** 对指定路径执行业务自定义断言。 */
    public function assert(string|array $path, callable $predicate, string $description): static
    {
        $value = $this->value($path);
        $this->run->check($predicate($value) === true, $this->context($path) . ' ' . $description, true, $value);
        return $this;
    }
}

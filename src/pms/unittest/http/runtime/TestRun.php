<?php
declare(strict_types=1);
namespace pms\unittest\http\runtime;

use pms\unittest\http\exception\AssertionFailed;

/** 一次流程执行的请求、断言统计与输出。 */
final class TestRun
{
    private int $requests = 0;
    private int $assertions = 0;
    private string $step = '';
    public function __construct(private readonly ?\Closure $report = null) {}
    /** 标记当前业务步骤。 */
    public function step(string $name): void { $this->step = $name; }
    /** 登记一次真实请求。 */
    public function requested(): void { $this->requests++; }
    /** 检查条件，失败立即中断依赖当前结果的后续流程。 */
    public function check(bool $passed, string $context, mixed $expected, mixed $actual): void
    {
        $prefix = $this->step === '' ? '' : '[' . $this->step . '] ';
        if (!$passed) {
            throw new AssertionFailed($prefix . $context . "\n期望: " . var_export($expected, true) . "\n实际: " . var_export($actual, true));
        }
        $this->assertions++;
        if ($this->report !== null) { ($this->report)('[PASS] ' . $prefix . $context); }
    }
    /** 返回本次执行统计。 */
    public function summary(): array { return ['requests' => $this->requests, 'assertions' => $this->assertions]; }
}

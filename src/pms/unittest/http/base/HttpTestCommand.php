<?php
declare(strict_types=1);
namespace pms\unittest\http\base;

use pms\unittest\http\runtime\TestRun;
use pms\unittest\http\runtime\TestScope;
use pms\unittest\http\assertion\DataAssertion;

use pms\app\TerminalCommandApp;

/** 使用既有 Command 执行整段业务测试，输出断言并给出明确退出状态。 */
abstract class HttpTestCommand extends TerminalCommandApp
{
    protected TestRun $testRun;
    /** 子类按顺序编排真实 HTTP 调用及断言。 */
    abstract protected function test(): void;
    /** 标记流程中的业务步骤。 */
    protected function step(string $name): void { $this->testRun->step($name); }
    /** 断言真实查询结果；宿主在流程中自行发出数据库查询，包不连接数据库。 */
    protected function assertData(mixed $data, string $description = 'data'): DataAssertion
    {
        return new DataAssertion($data, $this->testRun, $description);
    }
    /** 完整执行测试命令；失败立即中断并返回非零退出码。 */
    final public function entry(): void
    {
        $this->testRun = new TestRun(static fn(string $message) => print($message . PHP_EOL));
        $previous = TestScope::enter($this->testRun);
        $exitCode = 0;
        try {
            $this->test();
            $summary = $this->testRun->summary();
            echo '[PASS] ' . static::class . ' 请求 ' . $summary['requests'] . ' 次，断言 ' . $summary['assertions'] . ' 条' . PHP_EOL;
        } catch (\Throwable $e) {
            fwrite(STDERR, '[FAIL] ' . static::class . ': ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            $exitCode = 1;
        } finally { TestScope::restore($previous); }
        if ($exitCode !== 0) { exit($exitCode); }
    }
}

<?php
declare(strict_types=1);
namespace pms\unittest\http\runtime;

/** CLI 流程共用的断言统计作用域，嵌套调用结束时恢复上层。 */
final class TestScope
{
    private static ?TestRun $run = null;
    public static function enter(TestRun $run): ?TestRun { $previous = self::$run; self::$run = $run; return $previous; }
    public static function restore(?TestRun $run): void { self::$run = $run; }
    public static function current(): TestRun
    {
        if (self::$run === null) { throw new \LogicException('接口请求必须在 HttpTestCommand 流程作用域内执行'); }
        return self::$run;
    }
}

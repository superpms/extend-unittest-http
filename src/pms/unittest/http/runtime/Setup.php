<?php
declare(strict_types=1);
namespace pms\unittest\http\runtime;

use pms\facade\Config;
use pms\facade\Path;

/** Terminal 启动时自动识别 utests 根配置和唯一根 autoload 入口。 */
final class Setup
{
    public static function entry(): void
    {
        $config = Path::getRoot('utests/config.php');
        if (is_file($config)) {
            $settings = require $config;
            if (!is_array($settings)) { throw new \UnexpectedValueException('utests/config.php 必须返回数组'); }
            Config::mount('unittest-http', $settings);
        }
        $autoload = Path::getRoot('utests/autoload.php');
        if (is_file($autoload)) { require_once $autoload; }
    }
}

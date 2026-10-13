<?php

use pms\hook\TerminalLifecycleHook;
use pms\unittest\http\runtime\Setup;

/** Composer 自动加载包入口，类型由包的 PSR-4 声明加载。 */
TerminalLifecycleHook::mount(LIFECYCLE_BOOT, Setup::class);

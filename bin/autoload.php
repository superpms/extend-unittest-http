<?php
/** Composer 安装及框架实体源码联调共用入口。 */
foreach (['exception/AssertionFailed','runtime/TestRun','assertion/DataAssertion','response/Response','contract/RequestIdentity','request/Client','request/InterfaceRoute','runtime/TestScope','Endpoint','generator/Generator','base/HttpTestCommand','runtime/Setup'] as $class) {
    $name = 'pms\\unittest\\http\\' . str_replace('/', '\\', $class);
    if (class_exists($name, false) || interface_exists($name, false)) { continue; }
    require_once __DIR__ . '/../src/pms/unittest/http/' . $class . '.php';
}
if (PHP_SAPI === 'cli') {
    \pms\hook\TerminalLifecycleHook::mount(LIFECYCLE_BOOT, \pms\unittest\http\runtime\Setup::class);
}

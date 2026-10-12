<?php
declare(strict_types=1);
namespace pms\unittest\http\request;

use pms\unittest\http\contract\RequestIdentity;

use pms\app\HttpApp;
use pms\facade\BootOptions;
use pms\interpreter\http\sandbox\HttpRoute;

/** 发起请求时按实际接口 class、身份终端、HTTP 配置和指定前缀构造地址。 */
final class InterfaceRoute
{
    public static function path(string $interface, RequestIdentity $identity, string $prefix): string
    {
        if (!is_subclass_of($interface, HttpApp::class)) { throw new \InvalidArgumentException('目标不是实际 HTTP 接口类: ' . $interface); }
        $parts = explode('\\', ltrim($interface, '\\'));
        $root = trim(BootOptions::get_dir_app(), '/');
        $package = config('http.structure_name.package', 'http');
        if ($parts[0] !== $root) { throw new \InvalidArgumentException('实际接口类不属于配置的 app 根: ' . $interface); }
        $position = array_search($package, $parts, true);
        if (!in_array($position, [2, 3], true) || count($parts) <= $position + 1) { throw new \InvalidArgumentException('接口类结构不符合当前 HTTP 配置: ' . $interface); }
        $terminal = $identity->terminal();
        if ($terminal === '' || str_contains($terminal, '/') || str_contains($terminal, '\\')) { throw new \InvalidArgumentException('身份 terminal 不合法'); }
        $interfaceParts = array_slice($parts, $position + 1);
        $segments = config('http.app.route.mode', HTTP_ROUTE_MODE_APP) === HTTP_ROUTE_MODE_TERMINAL
            ? [$terminal, $parts[1], ...$interfaceParts] : [$parts[1], $terminal, ...$interfaceParts];
        // 用实际配置核对 alias 后的目标类，类移动/路由变化不会悄悄请求另一个接口。
        $route = HttpRoute::resolve(HttpRoute::builder($segments));
        $route->constructInterface();
        if (ltrim((string)$route->interfaceClass, '\\') !== ltrim($interface, '\\')) {
            throw new \RuntimeException('当前身份和 HTTP 路由未指向所关联的接口类: ' . $interface);
        }
        return HttpRoute::builder($segments, $prefix);
    }
}

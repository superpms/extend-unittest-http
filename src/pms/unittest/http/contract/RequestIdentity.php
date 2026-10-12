<?php
declare(strict_types=1);
namespace pms\unittest\http\contract;

use pms\unittest\http\request\Client;

/** 身份只提供当前终端和认证头；不负责传输或指定接口地址。 */
interface RequestIdentity
{
    public function terminal(): string;
    public function headers(Client $client, string $method, string $path, array $parameters): array;
}

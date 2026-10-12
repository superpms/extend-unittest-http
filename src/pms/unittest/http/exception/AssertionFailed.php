<?php
declare(strict_types=1);
namespace pms\unittest\http\exception;

/** 响应断言失败，保留请求与比较位置。 */
final class AssertionFailed extends \RuntimeException {}

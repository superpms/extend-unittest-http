<?php
declare(strict_types=1);
namespace pms\unittest\http\response;

use pms\unittest\http\assertion\DataAssertion;
use pms\unittest\http\runtime\TestRun;

/** 保留实际 HTTP 状态和正文，复用统一数据路径断言。 */
class Response extends DataAssertion
{
    private mixed $json = null;
    private bool $decoded = false;
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $method,
        public readonly string $url,
        TestRun $run,
    ) {
        parent::__construct(null, $run, $method . ' ' . $url);
    }
    public function json(): mixed
    {
        if (!$this->decoded) {
            try { $this->json = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw new \UnexpectedValueException($this->method . ' ' . $this->url . ' 响应不是有效 JSON', 0, $e); }
            $this->decoded = true;
        }
        return $this->json;
    }
    protected function assertionData(): mixed { return $this->json(); }
    public function assertHttpStatus(int $expected): static
    {
        $this->run->check($this->status === $expected, $this->method . ' ' . $this->url . ' HTTP', $expected, $this->status);
        return $this;
    }
}

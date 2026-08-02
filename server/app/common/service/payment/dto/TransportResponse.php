<?php
declare(strict_types=1);

namespace app\common\service\payment\dto;

final class TransportResponse
{
    private int $statusCode;
    private string $body;
    private array $headers;

    public function __construct(int $statusCode, string $body, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function statusCode(): int { return $this->statusCode; }
    public function body(): string { return $this->body; }
    public function headers(): array { return $this->headers; }
}

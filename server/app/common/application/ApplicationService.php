<?php
declare(strict_types=1);

namespace app\common\application;

abstract class ApplicationService
{
    private string $error = '';

    /** Clear the previous operation error on this service instance. */
    protected function clearError(): void
    {
        $this->error = '';
    }

    public function setError(string $error): void
    {
        $this->error = $error;
    }

    /** Record an operation error while preserving the legacy false contract. */
    protected function fail(\Throwable|string $error): false
    {
        $this->setError($error instanceof \Throwable ? $error->getMessage() : $error);
        return false;
    }

    public function getError(): string
    {
        return $this->error;
    }
}

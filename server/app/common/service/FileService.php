<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\service\member\AuthenticatedMemberContext;
use app\common\service\storage\StorageService;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\TenantSystemContext;

/** Business file references enter the physical storage layer only here. */
final readonly class FileService
{
    public function __construct(private StorageService $storage) {}

    public function getFileUrl(string $reference = ''): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $reference) === 1) {
            return $reference;
        }
        $url = $this->storage->publicUrl($reference);
        return $url !== '' ? $url : rtrim((string)request()->domain(), '/') . '/' . ltrim($reference, '/');
    }

    public function setTenantFileUrl(
        AuthenticatedMemberContext|TenantContext|TenantSystemContext $context,
        string $value = '',
    ): string {
        return $this->storage->normalizePublicReference($context->tenantId, $value);
    }
}

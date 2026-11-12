<?php

namespace App\Domain;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * İş kuralı ihlali. API'de Türkçe mesaj ve makine tarafından okunabilir
 * bir hata kodu ile JSON olarak döner.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_error',
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $message = 'Bu işlem için yetkiniz yok.'): self
    {
        return new self($message, 'forbidden', 403);
    }

    public static function notFound(string $message = 'Kayıt bulunamadı.'): self
    {
        return new self($message, 'not_found', 404);
    }

    /** İş kuralı hataları loglanmaz. */
    public function report(): bool
    {
        return true;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->context), $this->status);
    }
}

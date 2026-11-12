<?php

namespace App\Data;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class DirectoryUser implements Authenticatable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public UserRole $role,
        public ?string $studentQrCode,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Google-only authentication does not issue persistent login tokens.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}

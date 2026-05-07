<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

class CurrentTenant
{
    public function __construct(
        private ?Tenant $tenant = null,
    ) {}

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}

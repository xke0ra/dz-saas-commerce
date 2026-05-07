<?php

namespace App\Data\Tenancy;

use App\Models\TenantInvitation;

class TenantInvitationResult
{
    public function __construct(
        public readonly TenantInvitation $invitation,
        public readonly string $plainToken,
    ) {}
}

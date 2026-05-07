<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        ?string $tenantId = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): AuditLog {
        $request = request();
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'tenant_id' => $tenantId ?? $this->tenantIdFor($auditable),
            'actor_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey() === null ? null : (string) $auditable->getKey(),
            'old_values' => $this->normalize($oldValues),
            'new_values' => $this->normalize($newValues),
            'metadata' => $this->normalize($metadata),
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : $request->userAgent(),
        ]);
    }

    private function tenantIdFor(?Model $auditable): ?string
    {
        if ($auditable instanceof Tenant) {
            return $auditable->id;
        }

        $tenantId = $auditable?->getAttribute('tenant_id');

        if (is_string($tenantId)) {
            return $tenantId;
        }

        return app(CurrentTenant::class)->id();
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $value;
    }
}

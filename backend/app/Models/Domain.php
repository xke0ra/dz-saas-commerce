<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'store_id',
    'hostname',
    'status',
    'verification_token',
    'verified_at',
    'last_checked_at',
    'is_primary',
    'redirect_to_primary',
    'metadata',
])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::creating(function (Domain $domain): void {
            $domain->verification_token ??= Str::random(48);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_primary' => 'boolean',
            'redirect_to_primary' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function hostname(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeHostname($value),
        );
    }

    public function isResolvable(): bool
    {
        return $this->status === DomainStatus::Active && $this->verified_at !== null;
    }

    public function verificationRecordName(): string
    {
        return '_dz-saas-commerce.'.$this->hostname;
    }

    public function verificationRecordValue(): string
    {
        return 'dz-saas-commerce-verification='.$this->verification_token;
    }

    public static function normalizeHostname(string $host): string
    {
        $host = trim(strtolower($host));

        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            $parsedHost = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsedHost) ? $parsedHost : $host;
        }

        return Str::of($host)
            ->before('/')
            ->before(':')
            ->trim('.')
            ->toString();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan_id', 'key', 'value'])]
class PlanFeature extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function normalizedValue(): mixed
    {
        $value = is_array($this->value) && array_key_exists('value', $this->value)
            ? $this->value['value']
            : $this->value;

        if (! is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        return match (true) {
            $normalized === 'true' => true,
            $normalized === 'false' => false,
            $normalized === 'null', $normalized === '' => null,
            ctype_digit($normalized) => (int) $normalized,
            default => $value,
        };
    }
}

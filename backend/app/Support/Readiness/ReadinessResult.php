<?php

namespace App\Support\Readiness;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ReadinessResult implements Arrayable
{
    /**
     * @param  array<int, array{code: string, message: string}>  $errors
     * @param  array<int, array{code: string, message: string}>  $warnings
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly array $warnings = [],
    ) {}

    public function ready(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<int, string>
     */
    public function errorCodes(): array
    {
        return array_values(array_map(
            fn (array $error): string => $error['code'],
            $this->errors,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function warningCodes(): array
    {
        return array_values(array_map(
            fn (array $warning): string => $warning['code'],
            $this->warnings,
        ));
    }

    /**
     * @return array{ready: bool, errors: array<int, array{code: string, message: string}>, warnings: array<int, array{code: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}

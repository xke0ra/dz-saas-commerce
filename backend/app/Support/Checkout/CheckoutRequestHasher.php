<?php

namespace App\Support\Checkout;

use JsonException;

class CheckoutRequestHasher
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($this->normalize($payload), JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}

<?php

namespace App\Support\Domains;

class DomainDnsLookup
{
    /**
     * @return array<int, string>
     */
    public function txtRecords(string $hostname): array
    {
        $records = dns_get_record($hostname, DNS_TXT);

        if ($records === false) {
            return [];
        }

        return collect($records)
            ->map(function (array $record): ?string {
                if (isset($record['txt']) && is_string($record['txt'])) {
                    return trim($record['txt'], '"');
                }

                if (isset($record['entries']) && is_array($record['entries'])) {
                    return trim(implode('', $record['entries']), '"');
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}

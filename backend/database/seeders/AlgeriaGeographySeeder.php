<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class AlgeriaGeographySeeder extends Seeder
{
    /**
     * Seed the 58 Algerian wilayas and 1541 communes.
     *
     * The 2026 Algerian territorial reform creates 69 wilayas, but the current
     * checkout dataset intentionally keeps the proven 58-wilaya commune mapping
     * until an authoritative 69-wilaya commune reassignment dataset is added.
     */
    public function run(): void
    {
        $now = now();
        $wilayas = $this->loadJson('algeria_wilayas_58.json');
        $communes = $this->loadJson('algeria_communes_1541.json');

        Wilaya::query()
            ->whereNotIn('id', array_map(fn (array $wilaya): int => (int) $wilaya['id'], $wilayas))
            ->update(['is_active' => false]);

        Wilaya::query()->upsert(
            array_map(fn (array $wilaya): array => [
                'id' => (int) $wilaya['id'],
                'name_ar' => $wilaya['ar_name'],
                'name_fr' => $wilaya['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $wilayas),
            ['id'],
            ['name_ar', 'name_fr', 'is_active', 'updated_at'],
        );

        Commune::query()
            ->whereNotIn('id', array_map(fn (array $commune): int => (int) $commune['id'], $communes))
            ->update(['is_active' => false]);

        Commune::query()->upsert(
            array_map(fn (array $commune): array => [
                'id' => (int) $commune['id'],
                'wilaya_id' => (int) $commune['wilaya_id'],
                'name_ar' => $commune['ar_name'],
                'name_fr' => $commune['name'],
                'postal_code' => $commune['post_code'] ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $communes),
            ['id'],
            ['wilaya_id', 'name_ar', 'name_fr', 'postal_code', 'is_active', 'updated_at'],
        );

        $this->syncCommuneSequence();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadJson(string $file): array
    {
        $path = __DIR__.'/data/'.$file;

        if (! is_file($path)) {
            throw new RuntimeException("Missing Algeria geography seed data file [{$file}].");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid Algeria geography seed data file [{$file}].", previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException("Algeria geography seed data file [{$file}] must contain an array.");
        }

        return $data;
    }

    private function syncCommuneSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("select setval(pg_get_serial_sequence('communes', 'id'), (select coalesce(max(id), 1) from communes))");
    }
}

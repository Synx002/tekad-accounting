<?php

namespace App\Services;

use App\Models\FixedAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetService
{
    public function create(array $data, int $userId): FixedAsset
    {
        $salvage = round((float) ($data['salvage_value'] ?? 0), 2);
        $cost = round((float) $data['cost'], 2);

        return FixedAsset::create([
            'asset_code' => $this->generateAssetCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'acquisition_date' => $data['acquisition_date'],
            'cost' => $cost,
            'salvage_value' => $salvage,
            'useful_life_months' => (int) $data['useful_life_months'],
            'depreciation_method' => $data['depreciation_method'] ?? 'straight_line',
            'accumulated_depreciation' => 0,
            'book_value' => round($cost - 0, 2),
            'status' => $data['status'] ?? 'active',
            'created_by' => $userId,
        ]);
    }

    public function update(FixedAsset $asset, array $data): FixedAsset
    {
        if ($asset->depreciationEntries()->exists()) {
            $asset->update(\Illuminate\Support\Arr::only($data, ['name', 'description', 'location', 'status']));

            return $asset->fresh();
        }

        $payload = \Illuminate\Support\Arr::only($data, [
            'name', 'description', 'location', 'acquisition_date', 'cost', 'salvage_value',
            'useful_life_months', 'depreciation_method', 'status',
        ]);

        if (array_key_exists('cost', $payload) || array_key_exists('salvage_value', $payload)) {
            $cost = round((float) ($payload['cost'] ?? $asset->cost), 2);
            $salvage = round((float) ($payload['salvage_value'] ?? $asset->salvage_value), 2);
            $payload['cost'] = $cost;
            $payload['salvage_value'] = $salvage;
            $payload['book_value'] = round($cost - (float) $asset->accumulated_depreciation, 2);
        }

        $asset->update($payload);

        return $asset->fresh();
    }

    public function delete(FixedAsset $asset): void
    {
        if ($asset->depreciationEntries()->exists()) {
            throw ValidationException::withMessages([
                'fixed_asset' => 'Aset tidak dapat dihapus karena sudah ada jurnal penyusutan.',
            ]);
        }

        DB::transaction(function () use ($asset): void {
            $asset->delete();
        });
    }

    private function generateAssetCode(): string
    {
        $prefix = 'FA-'.now()->format('Ymd');
        $last = FixedAsset::query()
            ->where('asset_code', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', $last->asset_code);
            $next = (int) end($parts) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}

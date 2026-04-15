<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetDepreciationService
{
    public function recordPeriod(FixedAsset $asset, int $year, int $month, int $userId, ?string $note = null): FixedAssetDepreciationEntry
    {
        return DB::transaction(function () use ($asset, $year, $month, $userId, $note): FixedAssetDepreciationEntry {
            /** @var FixedAsset $locked */
            $locked = FixedAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Penyusutan hanya untuk aset berstatus aktif.',
                ]);
            }

            if ($locked->depreciation_method !== 'straight_line') {
                throw ValidationException::withMessages([
                    'depreciation_method' => 'Metode penyusutan belum didukung.',
                ]);
            }

            if (FixedAssetDepreciationEntry::query()
                ->where('fixed_asset_id', $locked->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->exists()) {
                throw ValidationException::withMessages([
                    'period' => 'Penyusutan untuk periode ini sudah dicatat.',
                ]);
            }

            $depreciable = round((float) $locked->cost - (float) $locked->salvage_value, 2);
            $accumulated = round((float) $locked->accumulated_depreciation, 2);
            $remaining = round($depreciable - $accumulated, 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'fixed_asset' => 'Aset sudah tidak memiliki basis penyusutan tersisa.',
                ]);
            }

            $periodsDone = $locked->depreciationEntries()->count();
            $life = (int) $locked->useful_life_months;

            if ($periodsDone >= $life) {
                throw ValidationException::withMessages([
                    'fixed_asset' => 'Masa manfaat penyusutan sudah habis.',
                ]);
            }

            $monthly = round($depreciable / $life, 2);

            if ($periodsDone === $life - 1) {
                $amount = $remaining;
            } else {
                $amount = min($monthly, $remaining);
            }

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'fixed_asset' => 'Nilai penyusutan tidak valid.',
                ]);
            }

            $newAccumulated = round($accumulated + $amount, 2);
            $newBook = round((float) $locked->cost - $newAccumulated, 2);

            if ($newBook < (float) $locked->salvage_value - 0.01) {
                throw ValidationException::withMessages([
                    'fixed_asset' => 'Penyusutan akan menurunkan nilai buku di bawah nilai sisa.',
                ]);
            }

            $entry = FixedAssetDepreciationEntry::query()->create([
                'fixed_asset_id' => $locked->id,
                'period_year' => $year,
                'period_month' => $month,
                'amount' => $amount,
                'book_value_after' => $newBook,
                'note' => $note,
                'created_by' => $userId,
            ]);

            $status = $newBook <= (float) $locked->salvage_value + 0.005 ? 'fully_depreciated' : 'active';

            $locked->update([
                'accumulated_depreciation' => $newAccumulated,
                'book_value' => $newBook,
                'status' => $status,
            ]);

            return $entry;
        });
    }
}

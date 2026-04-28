<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function recordMovement(InventoryItem $item, array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($item, $data, $userId): InventoryMovement {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            $qty = (float) $data['quantity'];
            $type = $data['type'];

            $change = match ($type) {
                'in' => round(abs($qty), 2),
                'out' => -round(abs($qty), 2),
                'adjustment' => round($qty, 2),
                default => throw ValidationException::withMessages(['type' => 'Tipe tidak valid.']),
            };

            $newHand = round((float) $locked->quantity_on_hand + $change, 2);

            if ($newHand < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi untuk transaksi ini.',
                ]);
            }

            $locked->update(['quantity_on_hand' => $newHand]);

            return InventoryMovement::query()->create([
                'inventory_item_id' => $locked->id,
                'type' => $type,
                'quantity_change' => $change,
                'quantity_after' => $newHand,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }
}

<?php

namespace App\Services;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryItemService
{
    public function create(array $data): InventoryItem
    {
        return InventoryItem::create([
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'unit' => $data['unit'] ?? 'pcs',
            'quantity_on_hand' => 0,
            'reorder_level' => $data['reorder_level'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(InventoryItem $item, array $data): InventoryItem
    {
        $item->fill(\Illuminate\Support\Arr::only($data, ['sku', 'name', 'unit', 'reorder_level', 'notes']));
        $item->save();

        return $item->fresh();
    }

    public function delete(InventoryItem $item): void
    {
        if ($item->movements()->exists()) {
            throw ValidationException::withMessages([
                'inventory_item' => 'Item tidak dapat dihapus karena sudah ada riwayat pergerakan stok.',
            ]);
        }

        if ((float) $item->quantity_on_hand != 0.0) {
            throw ValidationException::withMessages([
                'inventory_item' => 'Item tidak dapat dihapus karena stok tidak nol.',
            ]);
        }

        DB::transaction(function () use ($item): void {
            $item->delete();
        });
    }
}

<?php

namespace App\Services;

use App\Models\PurchaseBill;
use Illuminate\Support\Facades\DB;

class PurchaseBillService
{
    public function create(array $data, int $userId): PurchaseBill
    {
        return DB::transaction(function () use ($data, $userId): PurchaseBill {
            $bill = PurchaseBill::create([
                'purchase_number' => $this->generatePurchaseNumber(),
                'purchase_date' => $data['purchase_date'],
                'due_date' => $data['due_date'] ?? null,
                'supplier_name' => $data['supplier_name'],
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'subtotal' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ]);

            $this->syncItemsAndTotals($bill, $data['items']);

            return $bill->load('items');
        });
    }

    public function update(PurchaseBill $purchaseBill, array $data): PurchaseBill
    {
        return DB::transaction(function () use ($purchaseBill, $data): PurchaseBill {
            $purchaseBill->update([
                'purchase_date' => $data['purchase_date'],
                'due_date' => $data['due_date'] ?? null,
                'supplier_name' => $data['supplier_name'],
                'status' => $data['status'] ?? $purchaseBill->status,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItemsAndTotals($purchaseBill, $data['items']);

            return $purchaseBill->load('items');
        });
    }

    public function delete(PurchaseBill $purchaseBill): void
    {
        DB::transaction(function () use ($purchaseBill): void {
            $purchaseBill->items()->delete();
            $purchaseBill->delete();
        });
    }

    private function syncItemsAndTotals(PurchaseBill $bill, array $items): void
    {
        $bill->items()->delete();

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $taxPercent = (float) ($item['tax_percent'] ?? 0);

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineTaxTotal = round($lineSubtotal * ($taxPercent / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTaxTotal, 2);

            $bill->items()->create([
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_percent' => $taxPercent,
                'line_subtotal' => $lineSubtotal,
                'line_tax_total' => $lineTaxTotal,
                'line_total' => $lineTotal,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTaxTotal;
        }

        $bill->update([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal + $taxTotal, 2),
        ]);
    }

    private function generatePurchaseNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd');
        $last = PurchaseBill::query()
            ->where('purchase_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($last) {
            $parts = explode('-', $last->purchase_number);
            $lastSequence = (int) end($parts);
            $nextNumber = $lastSequence + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextNumber);
    }
}

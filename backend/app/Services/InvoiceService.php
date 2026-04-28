<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId): Invoice {
            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'customer_name' => $data['customer_name'],
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'subtotal' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ]);

            $this->syncItemsAndTotals($invoice, $data['items']);

            return $invoice->load('items');
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $invoice->update([
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'customer_name' => $data['customer_name'],
                'status' => $data['status'] ?? $invoice->status,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItemsAndTotals($invoice, $data['items']);

            return $invoice->load('items');
        });
    }

    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    private function syncItemsAndTotals(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $taxPercent = (float) ($item['tax_percent'] ?? 0);

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineTaxTotal = round($lineSubtotal * ($taxPercent / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTaxTotal, 2);

            $invoice->items()->create([
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

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal + $taxTotal, 2),
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $lastInvoice = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $lastSequence = (int) end($parts);
            $nextNumber = $lastSequence + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextNumber);
    }
}

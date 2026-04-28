<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function create(array $data, int $userId): Expense
    {
        $subtotal = round((float) $data['subtotal'], 2);
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        $taxTotal = round($subtotal * ($taxPercent / 100), 2);
        $grandTotal = round($subtotal + $taxTotal, 2);

        return Expense::create([
            'expense_number' => $this->generateExpenseNumber(),
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'payee_name' => $data['payee_name'] ?? null,
            'description' => $data['description'],
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    public function update(Expense $expense, array $data): Expense
    {
        $subtotal = round((float) ($data['subtotal'] ?? $expense->subtotal), 2);
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        if (! array_key_exists('tax_percent', $data)) {
            if ((float) $expense->subtotal > 0) {
                $taxPercent = round(((float) $expense->tax_total / (float) $expense->subtotal) * 100, 2);
            } else {
                $taxPercent = 0;
            }
        }

        $taxTotal = round($subtotal * ($taxPercent / 100), 2);
        $grandTotal = round($subtotal + $taxTotal, 2);

        $expense->update([
            'expense_date' => $data['expense_date'] ?? $expense->expense_date,
            'category' => $data['category'] ?? $expense->category,
            'payee_name' => $data['payee_name'] ?? $expense->payee_name,
            'description' => $data['description'] ?? $expense->description,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'reference' => $data['reference'] ?? $expense->reference,
            'status' => $data['status'] ?? $expense->status,
            'notes' => $data['notes'] ?? $expense->notes,
        ]);

        return $expense->fresh();
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $expense->delete();
        });
    }

    private function generateExpenseNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Ymd');
        $last = Expense::query()
            ->where('expense_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', $last->expense_number);
            $next = (int) end($parts) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}

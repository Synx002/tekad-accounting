<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\Expense;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseJournalService
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    /**
     * Posting jurnal otomatis saat expense berubah status ke "paid".
     *
     * Skema jurnal:
     *   Debit  Beban Operasional (expense.operational) = subtotal
     *   Debit  PPN Masukan (expense.tax_input)         = tax_total (jika ada)
     *   Kredit Bank (expense.cash)                     = grand_total
     */
    public function postPaid(Expense $expense, int $userId): JournalEntry
    {
        $this->guardAlreadyPosted($expense);

        return DB::transaction(function () use ($expense, $userId): JournalEntry {
            /** @var Expense $locked */
            $locked = Expense::query()->whereKey($expense->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Biaya sudah dalam status paid.',
                ]);
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Biaya dibatalkan tidak dapat diubah ke paid.',
                ]);
            }

            $grandTotal = round((float) $locked->grand_total, 2);
            $subtotal   = round((float) $locked->subtotal, 2);
            $taxTotal   = round((float) $locked->tax_total, 2);

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'grand_total' => 'Biaya memiliki grand total nol, tidak dapat diposting.',
                ]);
            }

            $operationalAccount = AccountMapping::getAccount('expense.operational');
            $taxInputAccount    = AccountMapping::getAccount('expense.tax_input');
            $cashAccount        = AccountMapping::getAccount('expense.cash');

            $locked->update(['status' => 'paid']);

            $lines = [
                [
                    'account_id'  => $operationalAccount->id,
                    'debit'       => $subtotal,
                    'credit'      => 0,
                    'description' => 'Beban '.$locked->category.' — '.$locked->expense_number,
                    'sort_order'  => 0,
                ],
            ];

            if ($taxTotal > 0) {
                $lines[] = [
                    'account_id'  => $taxInputAccount->id,
                    'debit'       => $taxTotal,
                    'credit'      => 0,
                    'description' => 'PPN masukan '.$locked->expense_number,
                    'sort_order'  => 1,
                ];
            }

            $lines[] = [
                'account_id'  => $cashAccount->id,
                'debit'       => 0,
                'credit'      => $grandTotal,
                'description' => 'Pembayaran biaya '.$locked->expense_number,
                'sort_order'  => 2,
            ];

            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber(),
                'entry_date'     => now()->toDateString(),
                'reference'      => $locked->expense_number,
                'description'    => 'Biaya '.$locked->expense_number.' — '.($locked->payee_name ?? $locked->category).' (paid)',
                'status'         => 'draft',
                'created_by'     => $userId,
                'source_type'    => Expense::class,
                'source_id'      => $locked->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            $this->journalEntryService->post($entry);

            return $entry->fresh('lines.account');
        });
    }

    private function guardAlreadyPosted(Expense $expense): void
    {
        $exists = JournalEntry::query()
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->where('status', 'posted')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'expense' => 'Jurnal untuk biaya ini sudah diposting.',
            ]);
        }
    }

    private function nextJournalNumber(): string
    {
        $prefix = 'JE-'.now()->format('Ymd');
        $last   = JournalEntry::query()
            ->where('journal_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', $last->journal_number);
            $next  = (int) end($parts) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}

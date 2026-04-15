<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceJournalService
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    /**
     * Posting jurnal otomatis saat invoice berubah status ke "paid".
     *
     * Skema jurnal:
     *   Debit  Bank (invoice.cash)              = grand_total
     *   Kredit Piutang Usaha (invoice.receivable) = grand_total
     *
     * Jika invoice belum pernah dijurnal (status sebelumnya draft/sent),
     * baris pendapatan dan PPN juga dibuat dalam jurnal yang sama:
     *   Debit  Piutang Usaha = subtotal + tax_total  (entry awal piutang)
     *   Kredit Pendapatan Penjualan = subtotal
     *   Kredit PPN Keluaran = tax_total (jika ada)
     */
    public function postPaid(Invoice $invoice, int $userId): JournalEntry
    {
        $this->guardAlreadyPosted($invoice);

        return DB::transaction(function () use ($invoice, $userId): JournalEntry {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Invoice sudah dalam status paid.',
                ]);
            }

            if (in_array($locked->status, ['cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Invoice dibatalkan tidak dapat diubah ke paid.',
                ]);
            }

            $grandTotal = round((float) $locked->grand_total, 2);
            $subtotal = round((float) $locked->subtotal, 2);
            $taxTotal = round((float) $locked->tax_total, 2);

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'grand_total' => 'Invoice memiliki grand total nol, tidak dapat diposting.',
                ]);
            }

            $cashAccount = AccountMapping::getAccount('invoice.cash');
            $receivableAccount = AccountMapping::getAccount('invoice.receivable');
            $revenueAccount = AccountMapping::getAccount('invoice.revenue');
            $taxOutputAccount = AccountMapping::getAccount('invoice.tax_output');

            $previousStatus = $locked->status;

            $locked->update(['status' => 'paid']);

            $lines = [];

            if ($previousStatus === 'draft' || $previousStatus === 'sent') {
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'debit' => $grandTotal,
                    'credit' => 0,
                    'description' => 'Penerimaan pembayaran invoice '.$locked->invoice_number,
                    'sort_order' => 0,
                ];

                $lines[] = [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => $subtotal,
                    'description' => 'Pendapatan penjualan invoice '.$locked->invoice_number,
                    'sort_order' => 1,
                ];

                if ($taxTotal > 0) {
                    $lines[] = [
                        'account_id' => $taxOutputAccount->id,
                        'debit' => 0,
                        'credit' => $taxTotal,
                        'description' => 'PPN keluaran invoice '.$locked->invoice_number,
                        'sort_order' => 2,
                    ];
                }
            } else {
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'debit' => $grandTotal,
                    'credit' => 0,
                    'description' => 'Penerimaan pembayaran invoice '.$locked->invoice_number,
                    'sort_order' => 0,
                ];

                $lines[] = [
                    'account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => $grandTotal,
                    'description' => 'Pelunasan piutang invoice '.$locked->invoice_number,
                    'sort_order' => 1,
                ];
            }

            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber(),
                'entry_date' => now()->toDateString(),
                'reference' => $locked->invoice_number,
                'description' => 'Invoice '.$locked->invoice_number.' — '.$locked->customer_name.' (paid)',
                'status' => 'draft',
                'created_by' => $userId,
                'source_type' => Invoice::class,
                'source_id' => $locked->id,
            ]);

            foreach ($lines as $i => $line) {
                $entry->lines()->create($line);
            }

            $this->journalEntryService->post($entry);

            return $entry->fresh('lines.account');
        });
    }

    private function guardAlreadyPosted(Invoice $invoice): void
    {
        $exists = JournalEntry::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('status', 'posted')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice' => 'Jurnal untuk invoice ini sudah diposting.',
            ]);
        }
    }

    private function nextJournalNumber(): string
    {
        $prefix = 'JE-'.now()->format('Ymd');
        $last = JournalEntry::query()
            ->where('journal_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', $last->journal_number);
            $next = (int) end($parts) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}

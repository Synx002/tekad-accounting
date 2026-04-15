<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\PurchaseBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseBillJournalService
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    /**
     * Posting jurnal otomatis saat purchase bill berubah status ke "paid".
     *
     * Skema jurnal:
     *
     * Jika status sebelumnya draft/received (belum pernah dijurnal hutang):
     *   Debit  Beban Pokok Penjualan (purchase_bill.expense)  = subtotal
     *   Debit  PPN Masukan (purchase_bill.tax_input)          = tax_total (jika ada)
     *   Kredit Bank (purchase_bill.cash)                      = grand_total
     *
     * Jika status sebelumnya sudah melewati tahap hutang:
     *   Debit  Hutang Usaha (purchase_bill.payable)           = grand_total
     *   Kredit Bank (purchase_bill.cash)                      = grand_total
     */
    public function postPaid(PurchaseBill $purchaseBill, int $userId): JournalEntry
    {
        $this->guardAlreadyPosted($purchaseBill);

        return DB::transaction(function () use ($purchaseBill, $userId): JournalEntry {
            /** @var PurchaseBill $locked */
            $locked = PurchaseBill::query()->whereKey($purchaseBill->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Purchase bill sudah dalam status paid.',
                ]);
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Purchase bill dibatalkan tidak dapat diubah ke paid.',
                ]);
            }

            $grandTotal = round((float) $locked->grand_total, 2);
            $subtotal   = round((float) $locked->subtotal, 2);
            $taxTotal   = round((float) $locked->tax_total, 2);

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'grand_total' => 'Purchase bill memiliki grand total nol, tidak dapat diposting.',
                ]);
            }

            $payableAccount = AccountMapping::getAccount('purchase_bill.payable');
            $expenseAccount = AccountMapping::getAccount('purchase_bill.expense');
            $taxInputAccount = AccountMapping::getAccount('purchase_bill.tax_input');
            $cashAccount    = AccountMapping::getAccount('purchase_bill.cash');

            $previousStatus = $locked->status;

            $locked->update(['status' => 'paid']);

            $lines = [];

            if (in_array($previousStatus, ['draft', 'received'], true)) {
                // Belum pernah dijurnal — langsung catat pembelian + pembayaran
                $lines[] = [
                    'account_id'  => $expenseAccount->id,
                    'debit'       => $subtotal,
                    'credit'      => 0,
                    'description' => 'Pembelian '.$locked->purchase_number,
                    'sort_order'  => 0,
                ];

                if ($taxTotal > 0) {
                    $lines[] = [
                        'account_id'  => $taxInputAccount->id,
                        'debit'       => $taxTotal,
                        'credit'      => 0,
                        'description' => 'PPN masukan '.$locked->purchase_number,
                        'sort_order'  => 1,
                    ];
                }

                $lines[] = [
                    'account_id'  => $cashAccount->id,
                    'debit'       => 0,
                    'credit'      => $grandTotal,
                    'description' => 'Pembayaran pembelian '.$locked->purchase_number,
                    'sort_order'  => 2,
                ];
            } else {
                // Sudah dijurnal sebagai hutang — catat pelunasan hutang
                $lines[] = [
                    'account_id'  => $payableAccount->id,
                    'debit'       => $grandTotal,
                    'credit'      => 0,
                    'description' => 'Pelunasan hutang '.$locked->purchase_number,
                    'sort_order'  => 0,
                ];

                $lines[] = [
                    'account_id'  => $cashAccount->id,
                    'debit'       => 0,
                    'credit'      => $grandTotal,
                    'description' => 'Pembayaran hutang '.$locked->purchase_number,
                    'sort_order'  => 1,
                ];
            }

            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber(),
                'entry_date'     => now()->toDateString(),
                'reference'      => $locked->purchase_number,
                'description'    => 'Purchase Bill '.$locked->purchase_number.' — '.$locked->supplier_name.' (paid)',
                'status'         => 'draft',
                'created_by'     => $userId,
                'source_type'    => PurchaseBill::class,
                'source_id'      => $locked->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            $this->journalEntryService->post($entry);

            return $entry->fresh('lines.account');
        });
    }

    private function guardAlreadyPosted(PurchaseBill $purchaseBill): void
    {
        $exists = JournalEntry::query()
            ->where('source_type', PurchaseBill::class)
            ->where('source_id', $purchaseBill->id)
            ->where('status', 'posted')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'purchase_bill' => 'Jurnal untuk purchase bill ini sudah diposting.',
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

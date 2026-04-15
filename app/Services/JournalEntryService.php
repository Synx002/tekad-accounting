<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function createDraft(array $data, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $userId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber(),
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            $this->insertLines($entry, $data['lines']);

            return $entry->load('lines.account');
        });
    }

    public function updateDraft(JournalEntry $journalEntry, array $data): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya jurnal berstatus draf yang dapat diubah.',
            ]);
        }

        return DB::transaction(function () use ($journalEntry, $data): JournalEntry {
            $journalEntry->update([
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $journalEntry->lines()->delete();
            $this->insertLines($journalEntry, $data['lines']);

            return $journalEntry->load('lines.account');
        });
    }

    public function post(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya jurnal draf yang dapat diposting.',
            ]);
        }

        return DB::transaction(function () use ($journalEntry): JournalEntry {
            /** @var JournalEntry $locked */
            $locked = JournalEntry::query()->whereKey($journalEntry->getKey())->lockForUpdate()->firstOrFail();
            $locked->load('lines');

            $this->assertBalanced($locked);

            foreach ($locked->lines as $line) {
                $account = Account::query()->whereKey($line->account_id)->lockForUpdate()->first();
                if (! $account || ! $account->is_active || ! $account->is_postable) {
                    throw ValidationException::withMessages([
                        'lines' => 'Akun tidak aktif atau tidak dapat diposting: '.$line->account_id,
                    ]);
                }
            }

            $locked->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            return $locked->fresh('lines.account');
        });
    }

    public function deleteDraft(JournalEntry $journalEntry): void
    {
        if ($journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya jurnal draf yang dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($journalEntry): void {
            $journalEntry->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function insertLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
                'sort_order' => (int) ($line['sort_order'] ?? $i),
            ]);
        }
    }

    private function assertBalanced(JournalEntry $entry): void
    {
        $debit = round((float) $entry->lines->sum('debit'), 2);
        $credit = round((float) $entry->lines->sum('credit'), 2);

        if ($debit !== $credit || $debit <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Jurnal tidak seimbang atau total nol.',
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

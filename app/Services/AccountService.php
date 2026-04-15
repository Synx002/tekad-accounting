<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function create(array $data): Account
    {
        return Account::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_postable' => $data['is_postable'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->fill(\Illuminate\Support\Arr::only($data, [
            'code', 'name', 'type', 'parent_id', 'is_postable', 'is_active', 'sort_order',
        ]));
        $account->save();

        return $account->fresh();
    }

    public function delete(Account $account): void
    {
        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Akun memiliki sub-akun dan tidak dapat dihapus.',
            ]);
        }

        if ($account->journalEntryLines()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Akun sudah dipakai di jurnal dan tidak dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($account): void {
            $account->delete();
        });
    }
}

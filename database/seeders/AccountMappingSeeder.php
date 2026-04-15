<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;

class AccountMappingSeeder extends Seeder
{
    /** @var array<string, array{code: string, description: string}> */
    private array $mappings = [
        'invoice.receivable' => [
            'code' => '1-1300',
            'description' => 'Piutang Usaha — debit saat invoice dibuat/dikirim',
        ],
        'invoice.revenue' => [
            'code' => '4-4100',
            'description' => 'Pendapatan Penjualan — kredit saat invoice dibuat/dikirim',
        ],
        'invoice.tax_output' => [
            'code' => '2-2200',
            'description' => 'PPN Keluaran — kredit saat ada pajak di invoice',
        ],
        'invoice.cash' => [
            'code' => '1-1200',
            'description' => 'Bank — debit saat invoice dibayar (lunas)',
        ],
    ];

    public function run(): void
    {
        foreach ($this->mappings as $key => $def) {
            $account = Account::query()->where('code', $def['code'])->firstOrFail();
            AccountMapping::updateOrCreate(
                ['key' => $key],
                ['account_id' => $account->id, 'description' => $def['description']]
            );
        }
    }
}

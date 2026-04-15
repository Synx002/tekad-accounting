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

        // Purchase Bill
        'purchase_bill.payable' => [
            'code' => '2-2100',
            'description' => 'Hutang Usaha — kredit saat purchase bill dibuat/diterima',
        ],
        'purchase_bill.expense' => [
            'code' => '5-5100',
            'description' => 'Beban Pokok Penjualan — debit saat purchase bill dibuat/diterima',
        ],
        'purchase_bill.tax_input' => [
            'code' => '2-2300',
            'description' => 'PPN Masukan — debit saat ada pajak di purchase bill',
        ],
        'purchase_bill.cash' => [
            'code' => '1-1200',
            'description' => 'Bank — kredit saat purchase bill dibayar (lunas)',
        ],

        // Expense
        'expense.operational' => [
            'code' => '5-5200',
            'description' => 'Beban Operasional — debit saat biaya dibayar',
        ],
        'expense.tax_input' => [
            'code' => '2-2300',
            'description' => 'PPN Masukan — debit saat ada pajak di biaya',
        ],
        'expense.cash' => [
            'code' => '1-1200',
            'description' => 'Bank — kredit saat biaya dibayar',
        ],

        // Fixed Asset Depreciation
        'depreciation.expense' => [
            'code' => '5-5300',
            'description' => 'Beban Penyusutan — debit saat penyusutan dicatat',
        ],
        'depreciation.accumulated' => [
            'code' => '1-1600',
            'description' => 'Akumulasi Penyusutan — kredit saat penyusutan dicatat',
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

<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => '1-1100', 'name' => 'Kas', 'type' => 'asset', 'sort_order' => 10],
            ['code' => '1-1200', 'name' => 'Bank', 'type' => 'asset', 'sort_order' => 20],
            ['code' => '1-1300', 'name' => 'Piutang Usaha', 'type' => 'asset', 'sort_order' => 30],
            ['code' => '1-1400', 'name' => 'Persediaan', 'type' => 'asset', 'sort_order' => 40],
            ['code' => '1-1500', 'name' => 'Aset Tetap', 'type' => 'asset', 'sort_order' => 50],
            ['code' => '1-1600', 'name' => 'Akumulasi Penyusutan', 'type' => 'asset', 'sort_order' => 60],
            ['code' => '2-2100', 'name' => 'Hutang Usaha', 'type' => 'liability', 'sort_order' => 110],
            ['code' => '3-3100', 'name' => 'Modal', 'type' => 'equity', 'sort_order' => 210],
            ['code' => '4-4100', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'sort_order' => 310],
            ['code' => '5-5100', 'name' => 'Beban Pokok Penjualan', 'type' => 'expense', 'sort_order' => 410],
            ['code' => '5-5200', 'name' => 'Beban Operasional', 'type' => 'expense', 'sort_order' => 420],
            ['code' => '5-5300', 'name' => 'Beban Penyusutan', 'type' => 'expense', 'sort_order' => 430],
            ['code' => '2-2200', 'name' => 'PPN Keluaran', 'type' => 'liability', 'sort_order' => 120],
            ['code' => '2-2300', 'name' => 'PPN Masukan', 'type' => 'asset', 'sort_order' => 70],
        ];

        foreach ($rows as $row) {
            Account::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'parent_id' => null,
                    'is_postable' => true,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}

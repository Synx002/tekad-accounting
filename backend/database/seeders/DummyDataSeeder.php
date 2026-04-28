<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\ExpenseJournalService;
use App\Services\ExpenseService;
use App\Services\FixedAssetDepreciationService;
use App\Services\InventoryStockService;
use App\Services\InvoiceJournalService;
use App\Services\InvoiceService;
use App\Services\JournalEntryService;
use App\Services\PurchaseBillJournalService;
use App\Services\PurchaseBillService;
use Illuminate\Database\Seeder;

/**
 * DummyDataSeeder — Simulasi bisnis "Toko Busana Tekad"
 *
 * Cerita bisnis:
 *  - Toko busana dimulai Januari 2026 dengan modal awal Rp 50 juta.
 *  - Membeli aset tetap (mesin jahit, motor, laptop) secara bertahap.
 *  - Melakukan penjualan ke beberapa pelanggan, sebagian sudah dibayar.
 *  - Membeli bahan baku dari supplier, sebagian sudah dilunasi.
 *  - Membayar biaya operasional (sewa, listrik, internet).
 *  - Mencatat penyusutan aset setiap bulan.
 *  - Di akhir periode, ada jurnal penutup untuk memindahkan laba ke modal.
 *
 * Jurnal yang dihasilkan otomatis:
 *  - Invoice PAID  → Debit Bank | Kredit Pendapatan (+ PPN jika ada)
 *  - Bill PAID     → Debit BPP  | Kredit Bank
 *  - Expense PAID  → Debit Beban Operasional | Kredit Bank
 *  - Depreciation  → Debit Beban Penyusutan | Kredit Akum. Penyusutan
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $userId = (int) $user->id;

        $this->command->info('🏪  Memulai seeding data dummy Toko Busana Tekad...');

        $this->seedOpeningCapital($userId);
        $this->seedFixedAssets($userId);
        $this->seedInventory($userId);
        $this->seedInvoices($userId);
        $this->seedPurchaseBills($userId);
        $this->seedExpenses($userId);
        $this->seedClosingEntry($userId);

        $this->command->info('✅  Seeding selesai! Silakan eksplorasi semua fitur.');
    }

    // -------------------------------------------------------------------------
    // 1. MODAL AWAL (Opening Capital)
    // -------------------------------------------------------------------------
    private function seedOpeningCapital(int $userId): void
    {
        if (JournalEntry::query()->where('reference', 'MODAL-AWAL-2026')->exists()) {
            $this->command->line('   [skip] Modal awal sudah ada.');
            return;
        }

        $bank  = $this->acc('1-1200');
        $modal = $this->acc('3-3100');

        /** @var JournalEntryService $svc */
        $svc = app(JournalEntryService::class);

        $entry = $svc->createDraft([
            'entry_date'  => '2026-01-02',
            'reference'   => 'MODAL-AWAL-2026',
            'description' => 'Setoran modal awal pendirian Toko Busana Tekad',
            'lines' => [
                ['account_id' => $bank->id,  'debit' => 50_000_000, 'credit' => 0,          'description' => 'Setoran modal ke rekening bank'],
                ['account_id' => $modal->id, 'debit' => 0,          'credit' => 50_000_000, 'description' => 'Modal pemilik — Toko Busana Tekad'],
            ],
        ], $userId);

        $svc->post($entry);
        $this->command->line('   ✓ Modal awal Rp 50.000.000 diposting.');
    }

    // -------------------------------------------------------------------------
    // 2. ASET TETAP + AKUISISI + PENYUSUTAN
    // -------------------------------------------------------------------------
    private function seedFixedAssets(int $userId): void
    {
        $svc      = app(JournalEntryService::class);
        $deprSvc  = app(FixedAssetDepreciationService::class);
        $asetAkun = $this->acc('1-1500');
        $bankAkun = $this->acc('1-1200');

        // ── Mesin Jahit Industrial ──────────────────────────────────────────
        $mesin = FixedAsset::firstOrCreate(
            ['asset_code' => 'FA-001'],
            [
                'name'                    => 'Mesin Jahit Industrial Brother',
                'description'             => 'Mesin jahit industri kapasitas tinggi untuk produksi massal',
                'location'               => 'Lantai 1 — Area Produksi',
                'acquisition_date'       => '2026-01-05',
                'cost'                   => 25_000_000,
                'salvage_value'          => 1_000_000,
                'useful_life_months'     => 60,
                'depreciation_method'    => 'straight_line',
                'accumulated_depreciation' => 0,
                'book_value'             => 25_000_000,
                'status'                 => 'active',
                'created_by'             => $userId,
            ]
        );

        if ($mesin->wasRecentlyCreated) {
            // Jurnal akuisisi
            $entry = $svc->createDraft([
                'entry_date'  => '2026-01-05',
                'reference'   => 'FA-001',
                'description' => 'Pembelian Mesin Jahit Industrial Brother',
                'lines' => [
                    ['account_id' => $asetAkun->id, 'debit' => 25_000_000, 'credit' => 0,          'description' => 'Mesin Jahit Industrial Brother'],
                    ['account_id' => $bankAkun->id, 'debit' => 0,          'credit' => 25_000_000, 'description' => 'Pembayaran mesin jahit'],
                ],
            ], $userId);
            $svc->post($entry);

            // Penyusutan Jan–Mar 2026 (Rp 400.000/bulan)
            foreach ([[2026, 1], [2026, 2], [2026, 3]] as [$y, $m]) {
                $deprSvc->recordPeriod($mesin->fresh(), $y, $m, $userId);
            }
            $this->command->line('   ✓ Mesin Jahit Industrial + 3 bulan penyusutan.');
        }

        // ── Motor Delivery Honda Beat ───────────────────────────────────────
        $motor = FixedAsset::firstOrCreate(
            ['asset_code' => 'FA-002'],
            [
                'name'                    => 'Motor Delivery Honda Beat',
                'description'             => 'Motor untuk pengiriman pesanan pelanggan',
                'location'               => 'Parkiran Toko',
                'acquisition_date'       => '2026-02-01',
                'cost'                   => 18_000_000,
                'salvage_value'          => 0,
                'useful_life_months'     => 48,
                'depreciation_method'    => 'straight_line',
                'accumulated_depreciation' => 0,
                'book_value'             => 18_000_000,
                'status'                 => 'active',
                'created_by'             => $userId,
            ]
        );

        if ($motor->wasRecentlyCreated) {
            $entry = $svc->createDraft([
                'entry_date'  => '2026-02-01',
                'reference'   => 'FA-002',
                'description' => 'Pembelian Motor Delivery Honda Beat',
                'lines' => [
                    ['account_id' => $asetAkun->id, 'debit' => 18_000_000, 'credit' => 0,          'description' => 'Motor Delivery Honda Beat'],
                    ['account_id' => $bankAkun->id, 'debit' => 0,          'credit' => 18_000_000, 'description' => 'Pembayaran motor delivery'],
                ],
            ], $userId);
            $svc->post($entry);

            // Penyusutan Feb–Mar 2026 (Rp 375.000/bulan)
            foreach ([[2026, 2], [2026, 3]] as [$y, $m]) {
                $deprSvc->recordPeriod($motor->fresh(), $y, $m, $userId);
            }
            $this->command->line('   ✓ Motor Delivery Honda Beat + 2 bulan penyusutan.');
        }

        // ── Laptop Operasional ASUS ─────────────────────────────────────────
        $laptop = FixedAsset::firstOrCreate(
            ['asset_code' => 'FA-003'],
            [
                'name'                    => 'Laptop Kasir ASUS VivoBook',
                'description'             => 'Laptop untuk kasir, administrasi, dan pencatatan keuangan',
                'location'               => 'Meja Kasir',
                'acquisition_date'       => '2026-03-01',
                'cost'                   => 8_640_000,
                'salvage_value'          => 0,
                'useful_life_months'     => 36,
                'depreciation_method'    => 'straight_line',
                'accumulated_depreciation' => 0,
                'book_value'             => 8_640_000,
                'status'                 => 'active',
                'created_by'             => $userId,
            ]
        );

        if ($laptop->wasRecentlyCreated) {
            $entry = $svc->createDraft([
                'entry_date'  => '2026-03-01',
                'reference'   => 'FA-003',
                'description' => 'Pembelian Laptop Kasir ASUS VivoBook',
                'lines' => [
                    ['account_id' => $asetAkun->id, 'debit' => 8_640_000, 'credit' => 0,         'description' => 'Laptop Kasir ASUS VivoBook'],
                    ['account_id' => $bankAkun->id, 'debit' => 0,         'credit' => 8_640_000, 'description' => 'Pembayaran laptop'],
                ],
            ], $userId);
            $svc->post($entry);

            // Penyusutan Mar 2026 (Rp 240.000/bulan)
            $deprSvc->recordPeriod($laptop->fresh(), 2026, 3, $userId);
            $this->command->line('   ✓ Laptop Kasir ASUS + 1 bulan penyusutan.');
        }
    }

    // -------------------------------------------------------------------------
    // 3. INVENTORI
    // -------------------------------------------------------------------------
    private function seedInventory(int $userId): void
    {
        /** @var InventoryStockService $stockSvc */
        $stockSvc = app(InventoryStockService::class);

        $items = [
            [
                'sku' => 'KMP-001', 'name' => 'Kemeja Pria Lengan Panjang', 'unit' => 'pcs',
                'quantity_on_hand' => 0, 'reorder_level' => 10,
                'in'  => [['qty' => 80, 'note' => 'Stok awal — pembelian dari CV Bahan Jaya']],
                'out' => [['qty' => 30, 'note' => 'Penjualan ke PT Maju Jaya Textile']],
            ],
            [
                'sku' => 'CCH-002', 'name' => 'Celana Chino Pria', 'unit' => 'pcs',
                'quantity_on_hand' => 0, 'reorder_level' => 10,
                'in'  => [['qty' => 50, 'note' => 'Stok awal — pembelian dari CV Bahan Jaya']],
                'out' => [['qty' => 20, 'note' => 'Penjualan ke PT Maju Jaya Textile']],
            ],
            [
                'sku' => 'KAP-003', 'name' => 'Kaos Polo Polos', 'unit' => 'pcs',
                'quantity_on_hand' => 0, 'reorder_level' => 20,
                'in'  => [['qty' => 120, 'note' => 'Stok awal — pembelian dari PT Tekstil Nusantara']],
                'out' => [['qty' => 50, 'note' => 'Penjualan ke CV Berkah Sandang'], ['qty' => 15, 'note' => 'Penjualan ke Toko Indah Permai']],
            ],
            [
                'sku' => 'JKD-004', 'name' => 'Jaket Denim Unisex', 'unit' => 'pcs',
                'quantity_on_hand' => 0, 'reorder_level' => 5,
                'in'  => [['qty' => 30, 'note' => 'Stok awal — pembelian dari PT Tekstil Nusantara']],
                'out' => [['qty' => 5, 'note' => 'Penjualan ke CV Berkah Sandang'], ['qty' => 3, 'note' => 'Penjualan ke Toko Indah Permai']],
            ],
            [
                'sku' => 'BAT-005', 'name' => 'Batik Tulis Jogja', 'unit' => 'lembar',
                'quantity_on_hand' => 0, 'reorder_level' => 5,
                'in'  => [['qty' => 25, 'note' => 'Stok awal — pembelian dari UD Aksesoris Mode']],
                'out' => [],
            ],
        ];

        foreach ($items as $itemData) {
            $item = InventoryItem::firstOrCreate(
                ['sku' => $itemData['sku']],
                [
                    'name'             => $itemData['name'],
                    'unit'             => $itemData['unit'],
                    'quantity_on_hand' => 0,
                    'reorder_level'    => $itemData['reorder_level'],
                ]
            );

            if ($item->wasRecentlyCreated) {
                foreach ($itemData['in'] as $mv) {
                    $stockSvc->recordMovement($item->fresh(), ['type' => 'in', 'quantity' => $mv['qty'], 'note' => $mv['note']], $userId);
                }
                foreach ($itemData['out'] as $mv) {
                    $stockSvc->recordMovement($item->fresh(), ['type' => 'out', 'quantity' => $mv['qty'], 'note' => $mv['note']], $userId);
                }
            }
        }

        $this->command->line('   ✓ 5 item inventori dengan pergerakan stok.');
    }

    // -------------------------------------------------------------------------
    // 4. INVOICE PENJUALAN
    // -------------------------------------------------------------------------
    private function seedInvoices(int $userId): void
    {
        /** @var InvoiceService $invSvc */
        $invSvc = app(InvoiceService::class);
        /** @var InvoiceJournalService $jrnSvc */
        $jrnSvc = app(InvoiceJournalService::class);

        $invoices = [
            // PAID — jurnal otomatis dibuat
            [
                'invoice_date' => '2026-04-01', 'due_date' => '2026-04-15',
                'customer_name' => 'PT Maju Jaya Textile',
                'status' => 'draft',
                'notes' => 'Pesanan rutin bulanan — pakaian kerja.',
                'items' => [
                    ['description' => 'Kemeja Pria Lengan Panjang', 'quantity' => 30, 'unit_price' => 150_000, 'tax_percent' => 11],
                    ['description' => 'Celana Chino Pria',           'quantity' => 20, 'unit_price' => 200_000, 'tax_percent' => 11],
                ],
                'pay' => true,
            ],
            // PAID — tanpa PPN
            [
                'invoice_date' => '2026-04-05', 'due_date' => '2026-04-20',
                'customer_name' => 'CV Berkah Sandang',
                'status' => 'draft',
                'notes' => 'Pengiriman ke toko cabang Bekasi.',
                'items' => [
                    ['description' => 'Kaos Polo Polos',  'quantity' => 50, 'unit_price' => 80_000, 'tax_percent' => 0],
                    ['description' => 'Jaket Denim Unisex', 'quantity' => 5,  'unit_price' => 400_000, 'tax_percent' => 0],
                ],
                'pay' => true,
            ],
            // SENT — belum dibayar (contoh piutang)
            [
                'invoice_date' => '2026-04-10', 'due_date' => '2026-04-25',
                'customer_name' => 'Toko Indah Permai',
                'status' => 'sent',
                'notes' => 'Invoice sudah dikirim, menunggu pembayaran.',
                'items' => [
                    ['description' => 'Kaos Polo Polos',   'quantity' => 15, 'unit_price' => 85_000,  'tax_percent' => 0],
                    ['description' => 'Jaket Denim Unisex', 'quantity' => 3,  'unit_price' => 420_000, 'tax_percent' => 0],
                ],
                'pay' => false,
            ],
            // DRAFT — baru dibuat
            [
                'invoice_date' => '2026-04-15', 'due_date' => '2026-04-30',
                'customer_name' => 'Pak Budi Santoso',
                'status' => 'draft',
                'notes' => 'Pesanan personal, belum dikonfirmasi harga.',
                'items' => [
                    ['description' => 'Kemeja Pria Lengan Panjang', 'quantity' => 5, 'unit_price' => 160_000, 'tax_percent' => 0],
                ],
                'pay' => false,
            ],
        ];

        foreach ($invoices as $data) {
            $pay = $data['pay'];
            unset($data['pay']);

            $invoice = $invSvc->create($data, $userId);

            if ($pay) {
                $jrnSvc->postPaid($invoice, $userId);
            }
        }

        $this->command->line('   ✓ 4 invoice (2 paid, 1 sent, 1 draft).');
    }

    // -------------------------------------------------------------------------
    // 5. PURCHASE BILLS (PEMBELIAN)
    // -------------------------------------------------------------------------
    private function seedPurchaseBills(int $userId): void
    {
        /** @var PurchaseBillService $billSvc */
        $billSvc = app(PurchaseBillService::class);
        /** @var PurchaseBillJournalService $jrnSvc */
        $jrnSvc = app(PurchaseBillJournalService::class);

        $bills = [
            // PAID
            [
                'purchase_date' => '2026-04-01', 'due_date' => '2026-04-20',
                'supplier_name' => 'CV Bahan Jaya',
                'status' => 'draft',
                'notes' => 'Pembelian bahan baku kain katun & benang bulan April.',
                'items' => [
                    ['description' => 'Kain Katun Premium (50 meter)', 'quantity' => 50, 'unit_price' => 60_000, 'tax_percent' => 0],
                    ['description' => 'Benang Jahit Polyester (20 kg)', 'quantity' => 20, 'unit_price' => 15_000,  'tax_percent' => 0],
                ],
                'pay' => true,
            ],
            // PAID
            [
                'purchase_date' => '2026-04-07', 'due_date' => '2026-04-22',
                'supplier_name' => 'PT Tekstil Nusantara',
                'status' => 'draft',
                'notes' => 'Pembelian kain batik dan kain denim untuk produksi April.',
                'items' => [
                    ['description' => 'Kain Batik Tulis (30 meter)',  'quantity' => 30, 'unit_price' => 80_000, 'tax_percent' => 0],
                ],
                'pay' => true,
            ],
            // DRAFT — belum dibayar
            [
                'purchase_date' => '2026-04-14', 'due_date' => '2026-04-28',
                'supplier_name' => 'UD Aksesoris Mode',
                'status' => 'draft',
                'notes' => 'PO aksesoris: kancing, resleting, dan label merek.',
                'items' => [
                    ['description' => 'Kancing Baju (1.000 pcs)',  'quantity' => 1000, 'unit_price' => 500, 'tax_percent' => 0],
                    ['description' => 'Resleting YKK 20cm (200 pcs)', 'quantity' => 200, 'unit_price' => 3_500, 'tax_percent' => 0],
                    ['description' => 'Label Merek Custom (500 pcs)',  'quantity' => 500, 'unit_price' => 1_500, 'tax_percent' => 0],
                ],
                'pay' => false,
            ],
        ];

        foreach ($bills as $data) {
            $pay = $data['pay'];
            unset($data['pay']);

            $bill = $billSvc->create($data, $userId);

            if ($pay) {
                $jrnSvc->postPaid($bill, $userId);
            }
        }

        $this->command->line('   ✓ 3 purchase bill (2 paid, 1 draft).');
    }

    // -------------------------------------------------------------------------
    // 6. BIAYA OPERASIONAL (EXPENSES)
    // -------------------------------------------------------------------------
    private function seedExpenses(int $userId): void
    {
        /** @var ExpenseService $expSvc */
        $expSvc = app(ExpenseService::class);
        /** @var ExpenseJournalService $jrnSvc */
        $jrnSvc = app(ExpenseJournalService::class);

        $expenses = [
            // PAID
            [
                'expense_date' => '2026-04-01', 'category' => 'Sewa',
                'payee_name' => 'Ibu Hartati (Pemilik Ruko)',
                'description' => 'Sewa ruko Jl. Sudirman No. 12 bulan April 2026',
                'subtotal' => 2_500_000, 'tax_percent' => 0,
                'reference' => 'SEWA-APR-2026',
                'pay' => true,
            ],
            // PAID
            [
                'expense_date' => '2026-04-03', 'category' => 'Listrik',
                'payee_name' => 'PLN UP3 Jakarta Pusat',
                'description' => 'Tagihan listrik bulan Maret 2026 — No. Pel. 521234567890',
                'subtotal' => 350_000, 'tax_percent' => 0,
                'reference' => 'PLN-MAR-2026',
                'pay' => true,
            ],
            // PAID
            [
                'expense_date' => '2026-04-03', 'category' => 'Internet & Komunikasi',
                'payee_name' => 'Telkom IndiHome',
                'description' => 'Tagihan internet & telepon bulan April 2026',
                'subtotal' => 250_000, 'tax_percent' => 0,
                'reference' => 'TELKOM-APR-2026',
                'pay' => true,
            ],
            // PAID
            [
                'expense_date' => '2026-04-08', 'category' => 'Transportasi',
                'payee_name' => null,
                'description' => 'Bensin operasional motor delivery minggu pertama April',
                'subtotal' => 150_000, 'tax_percent' => 0,
                'reference' => null,
                'pay' => true,
            ],
            // DRAFT — belum diproses
            [
                'expense_date' => '2026-04-15', 'category' => 'Gaji & Upah',
                'payee_name' => 'Karyawan Toko (3 orang)',
                'description' => 'Gaji karyawan periode 1–15 April 2026',
                'subtotal' => 4_500_000, 'tax_percent' => 0,
                'reference' => 'GAJI-APR1-2026',
                'pay' => false,
            ],
            // DRAFT — belum diproses
            [
                'expense_date' => '2026-04-18', 'category' => 'Pemasaran',
                'payee_name' => 'Meta Ads (Instagram & Facebook)',
                'description' => 'Iklan digital media sosial April 2026',
                'subtotal' => 500_000, 'tax_percent' => 0,
                'reference' => 'ADS-APR-2026',
                'pay' => false,
            ],
        ];

        foreach ($expenses as $data) {
            $pay = $data['pay'];
            unset($data['pay']);

            $expense = $expSvc->create($data, $userId);

            if ($pay) {
                $jrnSvc->postPaid($expense, $userId);
            }
        }

        $this->command->line('   ✓ 6 biaya operasional (4 paid, 2 draft).');
    }

    // -------------------------------------------------------------------------
    // 7. JURNAL PENUTUP (Closing Entry)
    //    Memindahkan saldo Pendapatan & Beban ke akun Modal (Ekuitas)
    //    sehingga Neraca menjadi seimbang.
    //
    //    Perhitungan laba bersih periode Jan–Apr 2026:
    //      Pendapatan Penjualan        : Rp 14.500.000
    //      Beban Pokok Penjualan       : Rp  5.700.000
    //      Beban Operasional           : Rp  3.250.000
    //      Beban Penyusutan            : Rp  2.190.000
    //      ────────────────────────────────────────────
    //      Laba Bersih                 : Rp  3.360.000
    // -------------------------------------------------------------------------
    private function seedClosingEntry(int $userId): void
    {
        if (JournalEntry::query()->where('reference', 'CLOSING-APR-2026')->exists()) {
            $this->command->line('   [skip] Jurnal penutup sudah ada.');
            return;
        }

        $pendapatan = $this->acc('4-4100');
        $bpp        = $this->acc('5-5100');
        $bebanOps   = $this->acc('5-5200');
        $bebanDepr  = $this->acc('5-5300');
        $modal      = $this->acc('3-3100');

        /*
         * Nilai-nilai di bawah dihitung dari data yang di-seed di atas.
         *
         * Revenue dari invoice yang di-paid (subtotal saja, tanpa PPN):
         *   Invoice 1 (PT Maju Jaya)  : 8.500.000
         *   Invoice 2 (CV Berkah)     : 6.000.000
         *   TOTAL                     : 14.500.000
         *
         * COGS dari purchase bill yang di-paid:
         *   Bill 1 (CV Bahan Jaya)    : 3.300.000
         *   Bill 2 (PT Tekstil)       : 2.400.000
         *   TOTAL                     : 5.700.000
         *
         * Beban Operasional yang di-paid:
         *   Sewa Ruko                 : 2.500.000
         *   Listrik PLN               : 350.000
         *   Internet Telkom           : 250.000
         *   Bensin                    : 150.000
         *   TOTAL                     : 3.250.000
         *
         * Beban Penyusutan (dari FixedAssetDepreciationService):
         *   Mesin Jahit ×3 @ 400.000  : 1.200.000
         *   Motor Delivery ×2 @ 375.000: 750.000
         *   Laptop ×1 @ 240.000       : 240.000
         *   TOTAL                     : 2.190.000
         *
         * Laba Bersih = 14.500.000 − 5.700.000 − 3.250.000 − 2.190.000
         *             = 3.360.000
         */
        $totalRevenue    = 14_500_000;
        $totalCogs       = 5_700_000;
        $totalOperasional = 3_250_000;
        $totalDepresiasi = 2_190_000;
        $labaBersih      = $totalRevenue - $totalCogs - $totalOperasional - $totalDepresiasi; // 3.360.000

        /** @var JournalEntryService $svc */
        $svc = app(JournalEntryService::class);

        $entry = $svc->createDraft([
            'entry_date'  => '2026-04-30',
            'reference'   => 'CLOSING-APR-2026',
            'description' => 'Jurnal Penutup — Pemindahan Laba Bersih ke Modal (Periode Jan–Apr 2026)',
            'lines' => [
                // Tutup akun Pendapatan (debit untuk menolkan saldo kredit)
                ['account_id' => $pendapatan->id, 'debit' => $totalRevenue,    'credit' => 0,             'description' => 'Penutupan saldo Pendapatan Penjualan'],
                // Tutup akun Beban (kredit untuk menolkan saldo debit)
                ['account_id' => $bpp->id,        'debit' => 0,                'credit' => $totalCogs,    'description' => 'Penutupan saldo Beban Pokok Penjualan'],
                ['account_id' => $bebanOps->id,   'debit' => 0,                'credit' => $totalOperasional, 'description' => 'Penutupan saldo Beban Operasional'],
                ['account_id' => $bebanDepr->id,  'debit' => 0,                'credit' => $totalDepresiasi,  'description' => 'Penutupan saldo Beban Penyusutan'],
                // Laba bersih masuk ke Modal (Ekuitas)
                ['account_id' => $modal->id,      'debit' => 0,                'credit' => $labaBersih,   'description' => 'Laba bersih periode Jan–Apr 2026 → Modal'],
            ],
        ], $userId);

        $svc->post($entry);

        $this->command->line(sprintf(
            '   ✓ Jurnal penutup diposting. Laba bersih Rp %s masuk ke Modal.',
            number_format($labaBersih, 0, ',', '.')
        ));
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------
    private function acc(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }
}

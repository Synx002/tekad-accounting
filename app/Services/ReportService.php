<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function financialSummary(Carbon $start, Carbon $end): array
    {
        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $purchases = PurchaseBill::query()
            ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'void')
            ->get();

        $revenueSubtotal = round((float) $invoices->sum('subtotal'), 2);
        $revenueTax = round((float) $invoices->sum('tax_total'), 2);
        $revenueGrand = round((float) $invoices->sum('grand_total'), 2);

        $purchaseSubtotal = round((float) $purchases->sum('subtotal'), 2);
        $purchaseTax = round((float) $purchases->sum('tax_total'), 2);
        $purchaseGrand = round((float) $purchases->sum('grand_total'), 2);

        $expenseSubtotal = round((float) $expenses->sum('subtotal'), 2);
        $expenseTax = round((float) $expenses->sum('tax_total'), 2);
        $expenseGrand = round((float) $expenses->sum('grand_total'), 2);

        $periodStartKey = $start->year * 100 + $start->month;
        $periodEndKey = $end->year * 100 + $end->month;

        $depreciation = round((float) FixedAssetDepreciationEntry::query()
            ->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [$periodStartKey, $periodEndKey])
            ->sum('amount'), 2);

        $inventoryItems = InventoryItem::query()->count();
        $inventoryQty = round((float) InventoryItem::query()->sum('quantity_on_hand'), 2);

        $fixedAssets = FixedAsset::query()->get();
        $fixedAssetsBookTotal = round((float) $fixedAssets->sum('book_value'), 2);
        $fixedAssetsCostTotal = round((float) $fixedAssets->sum('cost'), 2);
        $fixedAssetsAccumDepr = round((float) $fixedAssets->sum('accumulated_depreciation'), 2);

        $ledgerActivity = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->select([
                'accounts.code',
                'accounts.name',
                DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
                DB::raw('SUM(journal_entry_lines.credit) as total_credit'),
            ])
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'total_debit' => round((float) $row->total_debit, 2),
                'total_credit' => round((float) $row->total_credit, 2),
                'net' => round((float) $row->total_debit - (float) $row->total_credit, 2),
            ]);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'revenue' => [
                'invoice_count' => $invoices->count(),
                'subtotal' => $revenueSubtotal,
                'tax_total' => $revenueTax,
                'grand_total' => $revenueGrand,
                'by_status' => $invoices->groupBy('status')->map->count(),
            ],
            'purchases' => [
                'bill_count' => $purchases->count(),
                'subtotal' => $purchaseSubtotal,
                'tax_total' => $purchaseTax,
                'grand_total' => $purchaseGrand,
                'by_status' => $purchases->groupBy('status')->map->count(),
            ],
            'expenses' => [
                'document_count' => $expenses->count(),
                'subtotal' => $expenseSubtotal,
                'tax_total' => $expenseTax,
                'grand_total' => $expenseGrand,
                'by_status' => $expenses->groupBy('status')->map->count(),
                'by_category' => $expenses->groupBy('category')->map(fn (Collection $g) => round((float) $g->sum('grand_total'), 2)),
            ],
            'estimates' => [
                'gross_margin' => round($revenueGrand - $purchaseGrand, 2),
                'depreciation_expense' => $depreciation,
                'simplified_operating_result' => round($revenueGrand - $purchaseGrand - $expenseGrand - $depreciation, 2),
            ],
            'inventory' => [
                'item_count' => $inventoryItems,
                'quantity_on_hand_total' => $inventoryQty,
            ],
            'fixed_assets' => [
                'asset_count' => $fixedAssets->count(),
                'total_cost' => $fixedAssetsCostTotal,
                'accumulated_depreciation' => $fixedAssetsAccumDepr,
                'total_book_value' => $fixedAssetsBookTotal,
            ],
            'ledger_activity' => $ledgerActivity,
        ];
    }

    /**
     * Laporan Laba Rugi (Income Statement).
     *
     * Menghitung saldo bersih per akun dari jurnal yang sudah diposting
     * dalam rentang periode, lalu mengelompokkan ke revenue & expense.
     *
     * Konvensi normal balance:
     *   Revenue  → kredit normal  → saldo = credit - debit
     *   Expense  → debit normal   → saldo = debit - credit
     *
     * @return array<string, mixed>
     */
    public function incomeStatement(Carbon $start, Carbon $end): array
    {
        $rows = $this->periodAccountBalances($start, $end, ['revenue', 'expense']);

        $revenueRows = $rows->where('type', 'revenue')->values();
        $expenseRows = $rows->where('type', 'expense')->values();

        $totalRevenue = round($revenueRows->sum('balance'), 2);
        $totalExpense = round($expenseRows->sum('balance'), 2);
        $netIncome    = round($totalRevenue - $totalExpense, 2);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
            ],
            'revenue' => [
                'accounts'      => $revenueRows,
                'total_revenue' => $totalRevenue,
            ],
            'expense' => [
                'accounts'      => $expenseRows,
                'total_expense' => $totalExpense,
            ],
            'net_income'  => $netIncome,
            'is_profit'   => $netIncome >= 0,
        ];
    }

    /**
     * Neraca (Balance Sheet) per tanggal tertentu.
     *
     * Mengakumulasi semua jurnal posted sejak awal hingga $asOf.
     * Konvensi normal balance:
     *   Asset     → debit normal  → saldo = debit - credit
     *   Liability → kredit normal → saldo = credit - debit
     *   Equity    → kredit normal → saldo = credit - debit
     *
     * Persamaan: Total Aset = Total Kewajiban + Total Ekuitas
     *
     * @return array<string, mixed>
     */
    public function balanceSheet(Carbon $asOf): array
    {
        $rows = $this->cumulativeAccountBalances($asOf, ['asset', 'liability', 'equity']);

        $assetRows     = $rows->where('type', 'asset')->values();
        $liabilityRows = $rows->where('type', 'liability')->values();
        $equityRows    = $rows->where('type', 'equity')->values();

        $totalAssets      = round($assetRows->sum('balance'), 2);
        $totalLiabilities = round($liabilityRows->sum('balance'), 2);
        $totalEquity      = round($equityRows->sum('balance'), 2);
        $balanced         = abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.02;

        return [
            'as_of' => $asOf->toDateString(),
            'assets' => [
                'accounts'     => $assetRows,
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'accounts'          => $liabilityRows,
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'accounts'     => $equityRows,
                'total_equity' => $totalEquity,
            ],
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
            'is_balanced' => $balanced,
        ];
    }

    /**
     * Saldo akun per periode tertentu (hanya jurnal dalam rentang tanggal).
     *
     * @param  string[]  $types
     * @return Collection<int, array<string, mixed>>
     */
    private function periodAccountBalances(Carbon $start, Carbon $end, array $types): Collection
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('accounts.type', $types)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->select([
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
                DB::raw('SUM(journal_entry_lines.credit) as total_credit'),
            ])
            ->get()
            ->map(function ($row): array {
                $debit  = round((float) $row->total_debit, 2);
                $credit = round((float) $row->total_credit, 2);

                // Normal balance: revenue & equity & liability = credit; asset & expense = debit
                $balance = in_array($row->type, ['revenue', 'liability', 'equity'], true)
                    ? round($credit - $debit, 2)
                    : round($debit - $credit, 2);

                return [
                    'code'          => $row->code,
                    'name'          => $row->name,
                    'type'          => $row->type,
                    'total_debit'   => $debit,
                    'total_credit'  => $credit,
                    'balance'       => $balance,
                ];
            });
    }

    /**
     * Akumulasi saldo akun sejak awal hingga $asOf (untuk Neraca).
     *
     * @param  string[]  $types
     * @return Collection<int, array<string, mixed>>
     */
    private function cumulativeAccountBalances(Carbon $asOf, array $types): Collection
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->where('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->whereIn('accounts.type', $types)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->select([
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
                DB::raw('SUM(journal_entry_lines.credit) as total_credit'),
            ])
            ->get()
            ->map(function ($row): array {
                $debit  = round((float) $row->total_debit, 2);
                $credit = round((float) $row->total_credit, 2);

                $balance = in_array($row->type, ['revenue', 'liability', 'equity'], true)
                    ? round($credit - $debit, 2)
                    : round($debit - $credit, 2);

                return [
                    'code'         => $row->code,
                    'name'         => $row->name,
                    'type'         => $row->type,
                    'total_debit'  => $debit,
                    'total_credit' => $credit,
                    'balance'      => $balance,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function businessPack(Carbon $start, Carbon $end, Carbon $asOf, int $limit = 10): array
    {
        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $salesByMonth = $invoices
            ->groupBy(fn (Invoice $i) => $i->invoice_date->format('Y-m'))
            ->map(fn (Collection $group, string $ym) => [
                'month' => $ym,
                'invoice_count' => $group->count(),
                'grand_total' => round((float) $group->sum('grand_total'), 2),
            ])
            ->values()
            ->sortBy('month')
            ->values();

        $topCustomers = $invoices
            ->groupBy('customer_name')
            ->map(fn (Collection $group, string $name) => [
                'customer_name' => $name,
                'invoice_count' => $group->count(),
                'grand_total' => round((float) $group->sum('grand_total'), 2),
            ])
            ->sortByDesc('grand_total')
            ->values()
            ->take($limit);

        $topProducts = $this->topInvoiceLineProducts($start, $end, $limit);

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'void')
            ->get();

        $topExpenseCategories = $expenses
            ->groupBy('category')
            ->map(fn (Collection $group, string $cat) => [
                'category' => $cat,
                'document_count' => $group->count(),
                'grand_total' => round((float) $group->sum('grand_total'), 2),
            ])
            ->sortByDesc('grand_total')
            ->values()
            ->take($limit);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'as_of' => $asOf->toDateString(),
            ],
            'sales_by_month' => $salesByMonth,
            'top_customers' => $topCustomers,
            'top_products' => $topProducts,
            'top_expense_categories' => $topExpenseCategories,
            'aging_receivables' => $this->agingReceivables($asOf),
            'aging_payables' => $this->agingPayables($asOf),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function topInvoiceLineProducts(Carbon $start, Carbon $end, int $limit): Collection
    {
        $rows = InvoiceItem::query()
            ->whereHas('invoice', function ($q) use ($start, $end): void {
                $q->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        return $rows
            ->groupBy('description')
            ->map(fn (Collection $lines, string $desc) => [
                'description' => $desc,
                'quantity' => round((float) $lines->sum('quantity'), 2),
                'line_total' => round((float) $lines->sum('line_total'), 2),
            ])
            ->sortByDesc('line_total')
            ->values()
            ->take($limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function agingReceivables(Carbon $asOf): array
    {
        $open = Invoice::query()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        return $this->bucketOutstanding($open, $asOf, fn (Invoice $i) => $i->due_date ?? $i->invoice_date, 'grand_total');
    }

    /**
     * @return array<string, mixed>
     */
    private function agingPayables(Carbon $asOf): array
    {
        $open = PurchaseBill::query()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        return $this->bucketOutstanding($open, $asOf, fn (PurchaseBill $b) => $b->due_date ?? $b->purchase_date, 'grand_total');
    }

    /**
     * @param  Collection<int, Invoice|PurchaseBill>  $rows
     * @param  callable(Invoice|PurchaseBill): \Carbon\Carbon  $dueResolver
     * @return array<string, mixed>
     */
    private function bucketOutstanding(Collection $rows, Carbon $asOf, callable $dueResolver, string $amountField): array
    {
        $buckets = [
            'current' => ['count' => 0, 'amount' => 0.0],
            'days_1_30' => ['count' => 0, 'amount' => 0.0],
            'days_31_60' => ['count' => 0, 'amount' => 0.0],
            'days_61_90' => ['count' => 0, 'amount' => 0.0],
            'days_over_90' => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($rows as $row) {
            /** @var \Carbon\Carbon $due */
            $due = Carbon::parse($dueResolver($row)->toDateString());
            $amount = (float) $row->{$amountField};

            if ($due->greaterThan($asOf)) {
                $key = 'current';
            } else {
                $daysOverdue = $due->diffInDays($asOf);
                if ($daysOverdue <= 30) {
                    $key = 'days_1_30';
                } elseif ($daysOverdue <= 60) {
                    $key = 'days_31_60';
                } elseif ($daysOverdue <= 90) {
                    $key = 'days_61_90';
                } else {
                    $key = 'days_over_90';
                }
            }

            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += $amount;
        }

        foreach ($buckets as $k => $v) {
            $buckets[$k]['amount'] = round($v['amount'], 2);
        }

        $buckets['open_document_count'] = $rows->count();
        $buckets['open_amount_total'] = round((float) $rows->sum($amountField), 2);

        return $buckets;
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_report_requires_authentication(): void
    {
        $this->getJson('/api/v1/reports/financial?start_date=2026-01-01&end_date=2026-01-31')
            ->assertUnauthorized();
    }

    public function test_financial_report_returns_expected_shape_for_owner(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/financial?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk()
            ->assertJsonStructure([
                'period' => ['start_date', 'end_date'],
                'revenue' => ['invoice_count', 'subtotal', 'tax_total', 'grand_total', 'by_status'],
                'purchases' => ['bill_count', 'subtotal', 'tax_total', 'grand_total', 'by_status'],
                'estimates' => ['gross_margin', 'depreciation_expense'],
                'inventory' => ['item_count', 'quantity_on_hand_total'],
                'fixed_assets' => ['asset_count', 'total_cost', 'accumulated_depreciation', 'total_book_value'],
            ]);
    }

    public function test_business_report_returns_expected_shape_for_owner(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/business?start_date=2026-01-01&end_date=2026-01-31&as_of=2026-01-31')
            ->assertOk()
            ->assertJsonStructure([
                'period' => ['start_date', 'end_date', 'as_of'],
                'sales_by_month',
                'top_customers',
                'top_products',
                'aging_receivables' => [
                    'current',
                    'days_1_30',
                    'days_31_60',
                    'days_61_90',
                    'days_over_90',
                    'open_document_count',
                    'open_amount_total',
                ],
                'aging_payables' => [
                    'current',
                    'open_document_count',
                    'open_amount_total',
                ],
            ]);
    }
}

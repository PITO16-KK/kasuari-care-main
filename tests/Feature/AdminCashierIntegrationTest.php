<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_front_office_can_access_all_cashier_and_finance_routes(): void
    {
        $admin = $this->userWithRole('front_office');
        $this->actingAs($admin);

        $this->get('/bills')->assertOk();
        $this->get('/sales/create')->assertOk();
        $this->get('/payment-history')->assertOk();
        $this->get('/finance/records')->assertOk();
    }

    public function test_admin_front_office_sees_finance_metrics_on_dashboard(): void
    {
        $admin = $this->userWithRole('front_office');
        $this->actingAs($admin);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canSeeFinance', true)
                ->has('stats.todayRevenue'));
    }

    public function test_admin_front_office_can_record_a_clinic_expense(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('front_office');
        $this->actingAs($admin);

        $response = $this->post('/finance/expenses', [
            'name' => 'Beli Alat Tulis Kantor',
            'amount' => 150000,
            'expense_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('nota.jpg'),
        ]);

        $response->assertRedirect('/finance/records');
        $this->assertDatabaseHas('clinic_expenses', [
            'name' => 'Beli Alat Tulis Kantor',
            'amount' => 150000,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_front_office_can_create_a_sale_via_pos(): void
    {
        $admin = $this->userWithRole('front_office');
        $this->actingAs($admin);

        $patient = Patient::create([
            'patient_code' => 'KL-2026-999',
            'name' => 'Pasien Tes Kasir',
            'is_active' => true,
        ]);

        $productId = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
            'sku' => 'PRD-001',
            'name' => 'Buku Panduan Terapi',
            'price' => 50000,
            'stock_quantity' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/sales', [
            'patient_id' => $patient->id,
            'line_type' => 'product',
            'item_id' => $productId,
            'quantity' => 1,
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect('/sales/1/receipt');
        $this->assertDatabaseHas('sales', [
            'patient_id' => $patient->id,
            'status' => 'paid',
            'cashier_id' => $admin->id,
            'grand_total' => 50000,
        ]);
        $this->assertDatabaseHas('payments', [
            'method' => 'cash',
            'amount' => 50000,
            'received_by' => $admin->id,
        ]);
    }

    public function test_admin_front_office_cannot_manage_payroll_reserved_for_owner(): void
    {
        $admin = $this->userWithRole('front_office');
        $this->actingAs($admin);

        $response = $this->post('/finance/payroll', [
            'therapist_profile_id' => 1,
            'payroll_period' => '2026-11',
            'amount' => 2000000,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_clear_cache_route_clears_cache_successfully(): void
    {
        $response = $this->get('/bersihkan-cache');
        $response->assertOk();
        $this->assertStringContainsString('Cache Laravel berhasil dibersihkan!', $response->getContent());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleRecord = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($roleRecord);

        return $user;
    }
}

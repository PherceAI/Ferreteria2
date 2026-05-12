<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\InventoryAlertSetting;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryAlertSettingService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class InventoryAlertSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_purchasing_can_save_inventory_alert_settings(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->userWithRole($branch, config('internal.owner_roles.0'));
        $purchasing = $this->userWithRole($branch, 'Encargada Compras');

        foreach ([$owner, $purchasing] as $user) {
            $response = $this
                ->actingAs($user)
                ->from(route('inventory.alerts.index'))
                ->post(route('inventory.alerts.settings.store'), [
                    'scope_type' => InventoryAlertSetting::SCOPE_GLOBAL,
                    'scope_key' => '',
                    'settings' => $this->settingsPayload(quantityEnabled: true),
                ]);

            $response->assertRedirect(route('inventory.alerts.index'));
        }

        $setting = InventoryAlertSetting::withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('scope_type', InventoryAlertSetting::SCOPE_GLOBAL)
            ->firstOrFail();

        $this->assertTrue($setting->settings['quantity']['enabled']);
    }

    public function test_other_roles_cannot_save_inventory_alert_settings(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->userWithRole($branch, 'Vendedor');

        $response = $this
            ->actingAs($seller)
            ->post(route('inventory.alerts.settings.store'), [
                'scope_type' => InventoryAlertSetting::SCOPE_GLOBAL,
                'scope_key' => '',
                'settings' => $this->settingsPayload(quantityEnabled: true),
            ]);

        $response->assertForbidden();
        $this->assertSame(0, InventoryAlertSetting::withoutBranchScope()->count());
    }

    public function test_metric_ranges_are_validated(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->userWithRole($branch, config('internal.owner_roles.0'));
        $settings = $this->settingsPayload(quantityEnabled: true);
        $settings['quantity']['levels']['low'] = 10000;

        $response = $this
            ->actingAs($owner)
            ->post(route('inventory.alerts.settings.store'), [
                'scope_type' => InventoryAlertSetting::SCOPE_GLOBAL,
                'scope_key' => '',
                'settings' => $settings,
            ]);

        $response->assertSessionHasErrors('settings.quantity.levels.low');
    }

    public function test_product_settings_take_priority_over_category_and_global_settings(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userWithRole($branch, config('internal.owner_roles.0'));
        $product = $this->product($branch, 'HAM-001', 'Herramientas', 8);
        $service = app(InventoryAlertSettingService::class);

        $service->save($branch->id, InventoryAlertSetting::SCOPE_GLOBAL, '', 'Global', $this->settingsPayload(quantityEnabled: true, low: 900), $user->id);
        $service->save($branch->id, InventoryAlertSetting::SCOPE_CATEGORY, 'Herramientas', 'Herramientas', $this->settingsPayload(quantityEnabled: true, low: 300), $user->id);
        $service->save($branch->id, InventoryAlertSetting::SCOPE_PRODUCT, 'HAM-001', 'HAM-001 - Martillo', $this->settingsPayload(quantityEnabled: true, low: 20), $user->id);

        $effective = $service->effectiveSettingsForProduct($product);

        $this->assertSame(InventoryAlertSetting::SCOPE_PRODUCT, $effective['scope_type']);
        $this->assertSame(20, $effective['settings']['quantity']['levels']['low']);
    }

    public function test_category_settings_take_priority_over_global_settings(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userWithRole($branch, config('internal.owner_roles.0'));
        $product = $this->product($branch, 'PIN-001', 'Pintura', 25);
        $service = app(InventoryAlertSettingService::class);

        $service->save($branch->id, InventoryAlertSetting::SCOPE_GLOBAL, '', 'Global', $this->settingsPayload(quantityEnabled: true, low: 900), $user->id);
        $service->save($branch->id, InventoryAlertSetting::SCOPE_CATEGORY, 'Pintura', 'Pintura', $this->settingsPayload(quantityEnabled: true, low: 50), $user->id);

        $effective = $service->effectiveSettingsForProduct($product);

        $this->assertSame(InventoryAlertSetting::SCOPE_CATEGORY, $effective['scope_type']);
        $this->assertSame(50, $effective['settings']['quantity']['levels']['low']);
    }

    public function test_quantity_alerts_use_current_stock_and_respect_disabled_settings(): void
    {
        $branch = Branch::factory()->create(['name' => 'Matriz']);
        $user = $this->userWithRole($branch, config('internal.owner_roles.0'));
        $this->product($branch, 'CEM-001', 'Cemento', 5);
        $service = app(InventoryAlertSettingService::class);

        $service->save($branch->id, InventoryAlertSetting::SCOPE_GLOBAL, '', 'Global', $this->settingsPayload(quantityEnabled: false, maximum: 10), $user->id);
        $disabledAlerts = $service->quantityAlerts([$branch->id], collect([$branch->id => $branch->name]));

        $this->assertCount(0, $disabledAlerts);

        $service->save($branch->id, InventoryAlertSetting::SCOPE_GLOBAL, '', 'Global', $this->settingsPayload(quantityEnabled: true, maximum: 10), $user->id);
        $enabledAlerts = $service->quantityAlerts([$branch->id], collect([$branch->id => $branch->name]));

        $this->assertCount(1, $enabledAlerts);
        $this->assertSame('critical', $enabledAlerts->first()['type']);
        $this->assertStringContainsString('Cemento gris', $enabledAlerts->first()['message']);
    }

    private function userWithRole(Branch $branch, string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch);
        $user->assignRole($role);

        return $user;
    }

    private function product(Branch $branch, string $code, string $category, int $stock): InventoryProduct
    {
        return InventoryProduct::query()->create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'Cemento gris',
            'unit' => 'UND',
            'current_stock' => $stock,
            'category_name' => $category,
            'min_stock' => 0,
        ]);
    }

    /**
     * @return array<string, array{enabled:bool, levels:array{low:int, moderate:int, high:int, maximum:int}}>
     */
    private function settingsPayload(
        bool $quantityEnabled,
        int $low = 750,
        int $moderate = 250,
        int $high = 50,
        int $maximum = 10,
    ): array {
        $settings = app(InventoryAlertSettingService::class)->defaultSettings();
        $settings['quantity'] = [
            'enabled' => $quantityEnabled,
            'levels' => [
                'low' => $low,
                'moderate' => $moderate,
                'high' => $high,
                'maximum' => $maximum,
            ],
        ];

        return $settings;
    }
}

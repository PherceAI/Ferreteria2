<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryAlertSetting;
use App\Domain\Inventory\Models\InventoryProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class InventoryAlertSettingService
{
    /**
     * @var array<string, array{label:string, unit:string, min:int, max:int, defaults:array{low:int, moderate:int, high:int, maximum:int}}>
     */
    private const METRICS = [
        'percentage' => [
            'label' => 'Alerta por porcentaje de stock',
            'unit' => '%',
            'min' => 0,
            'max' => 100,
            'defaults' => ['low' => 75, 'moderate' => 50, 'high' => 25, 'maximum' => 10],
        ],
        'quantity' => [
            'label' => 'Alerta por cantidad de stock',
            'unit' => 'unidades',
            'min' => 0,
            'max' => 9999,
            'defaults' => ['low' => 750, 'moderate' => 250, 'high' => 50, 'maximum' => 10],
        ],
        'expiry_days' => [
            'label' => 'Alerta por fecha de caducidad',
            'unit' => 'dias',
            'min' => 1,
            'max' => 730,
            'defaults' => ['low' => 365, 'moderate' => 180, 'high' => 90, 'maximum' => 30],
        ],
        'stagnation_days' => [
            'label' => 'Alerta por estancamiento',
            'unit' => 'dias',
            'min' => 1,
            'max' => 1095,
            'defaults' => ['low' => 30, 'moderate' => 90, 'high' => 180, 'maximum' => 365],
        ],
        'consumption_days' => [
            'label' => 'Alerta por tiempo de consumo',
            'unit' => 'dias',
            'min' => 1,
            'max' => 730,
            'defaults' => ['low' => 365, 'moderate' => 180, 'high' => 90, 'maximum' => 30],
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function metricDefinitions(): array
    {
        return self::METRICS;
    }

    /**
     * @return array<string, array{enabled:bool, levels:array{low:int, moderate:int, high:int, maximum:int}}>
     */
    public function defaultSettings(): array
    {
        return collect(self::METRICS)
            ->map(fn (array $definition): array => [
                'enabled' => false,
                'levels' => $definition['defaults'],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array{enabled:bool, levels:array{low:int, moderate:int, high:int, maximum:int}}>
     */
    public function normalizeSettings(array $settings): array
    {
        $normalized = $this->defaultSettings();

        foreach (self::METRICS as $metric => $definition) {
            $incoming = is_array($settings[$metric] ?? null) ? $settings[$metric] : [];
            $levels = is_array($incoming['levels'] ?? null) ? $incoming['levels'] : [];

            $normalized[$metric]['enabled'] = (bool) ($incoming['enabled'] ?? false);

            foreach (['low', 'moderate', 'high', 'maximum'] as $level) {
                $value = (int) ($levels[$level] ?? $definition['defaults'][$level]);
                $normalized[$metric]['levels'][$level] = min(
                    $definition['max'],
                    max($definition['min'], $value),
                );
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function save(
        int $branchId,
        string $scopeType,
        string $scopeKey,
        string $scopeLabel,
        array $settings,
        int $userId,
    ): InventoryAlertSetting {
        if (! $this->settingsTableExists()) {
            throw new \RuntimeException('Inventory alert settings table does not exist.');
        }

        return InventoryAlertSetting::withoutBranchScope()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'scope_type' => $scopeType,
                'scope_key' => $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? '' : $scopeKey,
            ],
            [
                'scope_label' => $scopeLabel,
                'settings' => $this->normalizeSettings($settings),
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * @return array{scope_type:string, scope_key:string, scope_label:string, settings:array<string, mixed>, source:string}
     */
    public function effectiveSettingsForProduct(InventoryProduct $product): array
    {
        if (! $this->settingsTableExists()) {
            return $this->fallbackGlobalSettings();
        }

        $settings = InventoryAlertSetting::withoutBranchScope()
            ->where('branch_id', $product->branch_id)
            ->where(function ($query) use ($product): void {
                $query
                    ->where(function ($query) use ($product): void {
                        $query
                            ->where('scope_type', InventoryAlertSetting::SCOPE_PRODUCT)
                            ->where('scope_key', $product->code);
                    })
                    ->orWhere(function ($query) use ($product): void {
                        $query
                            ->where('scope_type', InventoryAlertSetting::SCOPE_CATEGORY)
                            ->where('scope_key', (string) $product->category_name);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('scope_type', InventoryAlertSetting::SCOPE_GLOBAL)
                            ->where('scope_key', '');
                    });
            })
            ->orderByRaw("case scope_type when 'product' then 0 when 'category' then 1 else 2 end")
            ->first();

        if (! $settings instanceof InventoryAlertSetting) {
            return $this->fallbackGlobalSettings();
        }

        return [
            'scope_type' => $settings->scope_type,
            'scope_key' => $settings->scope_key,
            'scope_label' => $settings->scope_label,
            'settings' => $this->normalizeSettings($settings->settings ?? []),
            'source' => 'database',
        ];
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  Collection<int, string>  $branchNames
     * @return Collection<int, array<string, mixed>>
     */
    public function quantityAlerts(array $branchIds, Collection $branchNames, int $limit = 4): Collection
    {
        if (! $this->settingsTableExists()) {
            return collect();
        }

        $settingsByScope = InventoryAlertSetting::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy(fn (InventoryAlertSetting $setting): string => $this->settingKey(
                (int) $setting->branch_id,
                $setting->scope_type,
                $setting->scope_key,
            ));

        return InventoryProduct::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->where('current_stock', '>=', 0)
            ->orderBy('current_stock')
            ->limit(200)
            ->get()
            ->map(fn (InventoryProduct $product): ?array => $this->quantityAlertForProduct($product, $branchNames, $settingsByScope))
            ->filter()
            ->sortBy(fn (array $alert): string => sprintf('%d-%012.3f', $alert['severity_rank'], $alert['stock']))
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, string>  $branchNames
     * @param  Collection<string, InventoryAlertSetting>  $settingsByScope
     * @return array<string, mixed>|null
     */
    private function quantityAlertForProduct(InventoryProduct $product, Collection $branchNames, Collection $settingsByScope): ?array
    {
        $effective = $this->effectiveSettingsForProductFromCollection($product, $settingsByScope);
        $quantity = $effective['settings']['quantity'] ?? null;

        if (! is_array($quantity) || ! ($quantity['enabled'] ?? false)) {
            return null;
        }

        $levels = $quantity['levels'] ?? [];
        $stock = (float) $product->current_stock;
        $severity = match (true) {
            $stock <= (float) ($levels['maximum'] ?? 0) => ['key' => 'maximum', 'type' => 'critical', 'rank' => 0, 'label' => 'maxima'],
            $stock <= (float) ($levels['high'] ?? 0) => ['key' => 'high', 'type' => 'high', 'rank' => 1, 'label' => 'alta'],
            $stock <= (float) ($levels['moderate'] ?? 0) => ['key' => 'moderate', 'type' => 'medium', 'rank' => 2, 'label' => 'moderada'],
            $stock <= (float) ($levels['low'] ?? 0) => ['key' => 'low', 'type' => 'info', 'rank' => 3, 'label' => 'baja'],
            default => null,
        };

        if ($severity === null) {
            return null;
        }

        $branchName = $branchNames[(int) $product->branch_id] ?? 'Sucursal';

        return [
            'id' => "inventory-quantity-{$product->id}-{$severity['key']}",
            'type' => $severity['type'],
            'title' => "Alerta de stock {$severity['label']}",
            'message' => sprintf(
                '%s: %s tiene %s %s disponibles.',
                $branchName,
                $product->name,
                number_format($stock, 2),
                $product->unit ?? 'unidades',
            ),
            'timestamp' => $product->inventory_updated_at?->diffForHumans(short: true)
                ?? $product->updated_at?->diffForHumans(short: true)
                ?? '',
            'href' => route('inventory.products.index', ['search' => $product->code], false),
            'actionText' => 'Ver producto',
            'sort' => $product->inventory_updated_at?->getTimestamp()
                ?? $product->updated_at?->getTimestamp()
                ?? 0,
            'severity_rank' => $severity['rank'],
            'stock' => $stock,
        ];
    }

    /**
     * @param  Collection<string, InventoryAlertSetting>  $settingsByScope
     * @return array{scope_type:string, scope_key:string, scope_label:string, settings:array<string, mixed>, source:string}
     */
    private function effectiveSettingsForProductFromCollection(InventoryProduct $product, Collection $settingsByScope): array
    {
        $candidates = [
            $this->settingKey((int) $product->branch_id, InventoryAlertSetting::SCOPE_PRODUCT, $product->code),
            $this->settingKey((int) $product->branch_id, InventoryAlertSetting::SCOPE_CATEGORY, (string) $product->category_name),
            $this->settingKey((int) $product->branch_id, InventoryAlertSetting::SCOPE_GLOBAL, ''),
        ];

        foreach ($candidates as $key) {
            $setting = $settingsByScope->get($key);

            if ($setting instanceof InventoryAlertSetting) {
                return [
                    'scope_type' => $setting->scope_type,
                    'scope_key' => $setting->scope_key,
                    'scope_label' => $setting->scope_label,
                    'settings' => $this->normalizeSettings($setting->settings ?? []),
                    'source' => 'database',
                ];
            }
        }

        return $this->fallbackGlobalSettings();
    }

    /**
     * @return array{scope_type:string, scope_key:string, scope_label:string, settings:array<string, mixed>, source:string}
     */
    private function fallbackGlobalSettings(): array
    {
        return [
            'scope_type' => InventoryAlertSetting::SCOPE_GLOBAL,
            'scope_key' => '',
            'scope_label' => 'Alertas globales',
            'settings' => $this->defaultSettings(),
            'source' => 'default',
        ];
    }

    private function settingKey(int $branchId, string $scopeType, string $scopeKey): string
    {
        return "{$branchId}:{$scopeType}:{$scopeKey}";
    }

    public function settingsTableExists(): bool
    {
        return Schema::hasTable('pherce_intel.inventory_alert_settings');
    }
}

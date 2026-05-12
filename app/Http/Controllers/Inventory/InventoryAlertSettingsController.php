<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\InventoryAlertSetting;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryAlertSettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryAlertSettingsController extends Controller
{
    public function __construct(
        private readonly InventoryAlertSettingService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $branchId = (int) Context::get('branch_id');
        $selectedCategory = trim((string) $request->query('category', ''));
        $selectedProduct = trim((string) $request->query('product', ''));

        return Inertia::render('inventory/alerts/index', [
            'canEdit' => $this->canEdit($request),
            'metricDefinitions' => $this->settings->metricDefinitions(),
            'defaultSettings' => $this->settings->defaultSettings(),
            'categories' => fn () => $this->categories($branchId),
            'products' => fn () => $this->products($branchId),
            'specializedItems' => fn () => $this->specializedItems($branchId),
            'selected' => [
                'category' => $selectedCategory,
                'product' => $selectedProduct,
            ],
            'settings' => [
                'global' => $this->settingPayload($branchId, InventoryAlertSetting::SCOPE_GLOBAL, ''),
                'category' => $selectedCategory !== ''
                    ? $this->settingPayload($branchId, InventoryAlertSetting::SCOPE_CATEGORY, $selectedCategory)
                    : null,
                'product' => $selectedProduct !== ''
                    ? $this->settingPayload($branchId, InventoryAlertSetting::SCOPE_PRODUCT, $selectedProduct)
                    : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        abort_unless($this->settings->settingsTableExists(), 503, 'La tabla de configuracion de alertas no existe. Ejecuta las migraciones.');

        $branchId = (int) Context::get('branch_id');
        $validated = $request->validate($this->rules());
        $scopeType = (string) $validated['scope_type'];
        $scopeKey = $scopeType === InventoryAlertSetting::SCOPE_GLOBAL
            ? ''
            : trim((string) $validated['scope_key']);

        $scopeLabel = $this->scopeLabel($branchId, $scopeType, $scopeKey);

        $this->settings->save(
            branchId: $branchId,
            scopeType: $scopeType,
            scopeKey: $scopeKey,
            scopeLabel: $scopeLabel,
            settings: $validated['settings'],
            userId: (int) $request->user()->getKey(),
        );

        return back()->with('success', 'Configuracion de alertas guardada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'scope_type' => ['required', 'string', Rule::in([
                InventoryAlertSetting::SCOPE_GLOBAL,
                InventoryAlertSetting::SCOPE_CATEGORY,
                InventoryAlertSetting::SCOPE_PRODUCT,
            ])],
            'scope_key' => ['nullable', 'string', 'max:180', 'required_unless:scope_type,global'],
            'settings' => ['required', 'array'],
            'settings.percentage' => ['required', 'array'],
            'settings.quantity' => ['required', 'array'],
            'settings.expiry_days' => ['required', 'array'],
            'settings.stagnation_days' => ['required', 'array'],
            'settings.consumption_days' => ['required', 'array'],
            'settings.*.enabled' => ['required', 'boolean'],
            'settings.*.levels' => ['required', 'array'],
            'settings.*.levels.low' => ['required', 'integer'],
            'settings.*.levels.moderate' => ['required', 'integer'],
            'settings.*.levels.high' => ['required', 'integer'],
            'settings.*.levels.maximum' => ['required', 'integer'],
            'settings.percentage.levels.*' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.quantity.levels.*' => ['required', 'integer', 'min:0', 'max:9999'],
            'settings.expiry_days.levels.*' => ['required', 'integer', 'min:1', 'max:730'],
            'settings.stagnation_days.levels.*' => ['required', 'integer', 'min:1', 'max:1095'],
            'settings.consumption_days.levels.*' => ['required', 'integer', 'min:1', 'max:730'],
        ];
    }

    private function canEdit(Request $request): bool
    {
        return $request->user()?->hasAnyRole(config('internal.inventory_alert_roles', [])) ?? false;
    }

    private function scopeLabel(int $branchId, string $scopeType, string $scopeKey): string
    {
        if ($scopeType === InventoryAlertSetting::SCOPE_GLOBAL) {
            return 'Alertas globales';
        }

        if ($scopeType === InventoryAlertSetting::SCOPE_CATEGORY) {
            abort_unless($this->categoryExists($branchId, $scopeKey), 422, 'Categoria no valida.');

            return $scopeKey;
        }

        $product = InventoryProduct::query()
            ->forBranch($branchId)
            ->where('code', $scopeKey)
            ->first();

        abort_unless($product instanceof InventoryProduct, 422, 'Producto no valido.');

        return "{$product->code} - {$product->name}";
    }

    private function categoryExists(int $branchId, string $category): bool
    {
        return InventoryProduct::query()
            ->forBranch($branchId)
            ->where('category_name', $category)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function categories(int $branchId): array
    {
        return InventoryProduct::query()
            ->forBranch($branchId)
            ->whereNotNull('category_name')
            ->where('category_name', '<>', '')
            ->distinct()
            ->orderBy('category_name')
            ->limit(300)
            ->pluck('category_name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code:string,name:string,category_name:string|null}>
     */
    private function products(int $branchId): array
    {
        return InventoryProduct::query()
            ->forBranch($branchId)
            ->select(['code', 'name', 'category_name'])
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (InventoryProduct $product): array => [
                'code' => $product->code,
                'name' => $product->name,
                'category_name' => $product->category_name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{scope_key:string,scope_label:string,updated_at:string|null}>
     */
    private function specializedItems(int $branchId): array
    {
        if (! $this->settings->settingsTableExists()) {
            return [];
        }

        return InventoryAlertSetting::query()
            ->forBranch($branchId)
            ->where('scope_type', InventoryAlertSetting::SCOPE_PRODUCT)
            ->orderBy('scope_label')
            ->get()
            ->map(fn (InventoryAlertSetting $setting): array => [
                'scope_key' => $setting->scope_key,
                'scope_label' => $setting->scope_label,
                'updated_at' => $setting->updated_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
            ])
            ->all();
    }

    /**
     * @return array{scope_type:string,scope_key:string,scope_label:string,settings:array<string, mixed>,exists:bool}
     */
    private function settingPayload(int $branchId, string $scopeType, string $scopeKey): array
    {
        if (! $this->settings->settingsTableExists()) {
            return [
                'scope_type' => $scopeType,
                'scope_key' => $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? '' : $scopeKey,
                'scope_label' => $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? 'Alertas globales' : $scopeKey,
                'settings' => $this->settings->defaultSettings(),
                'exists' => false,
            ];
        }

        $setting = InventoryAlertSetting::query()
            ->forBranch($branchId)
            ->where('scope_type', $scopeType)
            ->where('scope_key', $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? '' : $scopeKey)
            ->first();

        if (! $setting instanceof InventoryAlertSetting) {
            return [
                'scope_type' => $scopeType,
                'scope_key' => $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? '' : $scopeKey,
                'scope_label' => $scopeType === InventoryAlertSetting::SCOPE_GLOBAL ? 'Alertas globales' : $scopeKey,
                'settings' => $this->settings->defaultSettings(),
                'exists' => false,
            ];
        }

        return [
            'scope_type' => $setting->scope_type,
            'scope_key' => $setting->scope_key,
            'scope_label' => $setting->scope_label,
            'settings' => $this->settings->normalizeSettings($setting->settings ?? []),
            'exists' => true,
        ];
    }
}

import { Head, router } from '@inertiajs/react';
import {
    Bell,
    Boxes,
    CalendarClock,
    ChevronDown,
    PackageSearch,
    Save,
    Search,
    Timer,
    ToggleLeft,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type MetricKey =
    | 'percentage'
    | 'quantity'
    | 'expiry_days'
    | 'stagnation_days'
    | 'consumption_days';

type AlertLevel = 'low' | 'moderate' | 'high' | 'maximum';

type MetricDefinition = {
    label: string;
    unit: string;
    min: number;
    max: number;
    defaults: Record<AlertLevel, number>;
};

type MetricSettings = {
    enabled: boolean;
    levels: Record<AlertLevel, number>;
};

type AlertSettings = Record<MetricKey, MetricSettings>;

type SettingPayload = {
    scope_type: 'global' | 'category' | 'product';
    scope_key: string;
    scope_label: string;
    settings: AlertSettings;
    exists: boolean;
};

type ProductOption = {
    code: string;
    name: string;
    category_name: string | null;
};

type SpecializedItem = {
    scope_key: string;
    scope_label: string;
    updated_at: string | null;
};

type Props = {
    canEdit: boolean;
    metricDefinitions: Record<MetricKey, MetricDefinition>;
    defaultSettings: AlertSettings;
    categories: string[];
    products: ProductOption[];
    specializedItems: SpecializedItem[];
    selected: {
        category: string;
        product: string;
    };
    settings: {
        global: SettingPayload;
        category: SettingPayload | null;
        product: SettingPayload | null;
    };
};

const metricIcons: Record<MetricKey, typeof Boxes> = {
    percentage: Boxes,
    quantity: PackageSearch,
    expiry_days: CalendarClock,
    stagnation_days: Timer,
    consumption_days: Bell,
};

const levelLabels: Record<AlertLevel, string> = {
    low: 'Bajo',
    moderate: 'Moderado',
    high: 'Alto',
    maximum: 'Maximo',
};

const metricOrder: MetricKey[] = [
    'percentage',
    'quantity',
    'expiry_days',
    'stagnation_days',
    'consumption_days',
];

function cloneSettings(settings: AlertSettings): AlertSettings {
    return Object.fromEntries(
        Object.entries(settings).map(([metric, value]) => [
            metric,
            {
                enabled: value.enabled,
                levels: { ...value.levels },
            },
        ]),
    ) as AlertSettings;
}

export default function InventoryAlertsIndex({
    canEdit,
    metricDefinitions,
    defaultSettings,
    categories,
    products,
    specializedItems,
    selected,
    settings,
}: Props) {
    const [productSearch, setProductSearch] = useState('');

    const selectedProduct = products.find(
        (product) => product.code === selected.product,
    );

    const filteredProducts = useMemo(() => {
        const needle = productSearch.trim().toLowerCase();

        if (needle === '') {
            return products.slice(0, 80);
        }

        return products
            .filter((product) =>
                `${product.code} ${product.name} ${product.category_name ?? ''}`
                    .toLowerCase()
                    .includes(needle),
            )
            .slice(0, 80);
    }, [productSearch, products]);

    const selectCategory = (category: string) => {
        router.get(
            '/inventory/alerts',
            {
                category: category === 'none' ? '' : category,
                product: selected.product,
            },
            {
                only: ['selected', 'settings'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const selectProduct = (product: string) => {
        router.get(
            '/inventory/alerts',
            {
                category: selected.category,
                product: product === 'none' ? '' : product,
            },
            {
                only: ['selected', 'settings'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Inventario | Alertas" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        Alertas de inventario
                    </h1>
                    <p className="max-w-3xl text-sm text-neutral-500 dark:text-zinc-400">
                        Configura la jerarquia de reglas por sucursal activa:
                        producto especifico, categoria y global.
                    </p>
                </div>

                <div className="space-y-3">
                    <AlertSection
                        title="Alertas globales"
                        description="Parametros base para el stock general de la tienda."
                        defaultOpen
                    >
                        <SettingsEditor
                            key="global"
                            canEdit={canEdit}
                            scopeType="global"
                            scopeKey=""
                            scopeLabel="Alertas globales"
                            payload={settings.global}
                            fallbackSettings={defaultSettings}
                            metricDefinitions={metricDefinitions}
                        />
                    </AlertSection>

                    <AlertSection
                        title="Alertas por categoria"
                        description="Reemplazan los parametros globales para productos de una categoria."
                    >
                        <div className="mb-4 max-w-md">
                            <Label className="text-xs text-neutral-500">
                                Categoria
                            </Label>
                            <Select
                                value={selected.category || 'none'}
                                onValueChange={selectCategory}
                            >
                                <SelectTrigger className="mt-2 bg-white dark:bg-zinc-950">
                                    <SelectValue placeholder="Seleccionar categoria" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Seleccionar categoria
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category}
                                            value={category}
                                        >
                                            {category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {selected.category === '' ? (
                            <EmptyState text="Selecciona una categoria para editar sus parametros." />
                        ) : (
                            <SettingsEditor
                                key={`category-${selected.category}`}
                                canEdit={canEdit}
                                scopeType="category"
                                scopeKey={selected.category}
                                scopeLabel={selected.category}
                                payload={settings.category}
                                fallbackSettings={defaultSettings}
                                metricDefinitions={metricDefinitions}
                            />
                        )}
                    </AlertSection>

                    <AlertSection
                        title="Alertas especializadas"
                        description="Reglas para un producto especifico; tienen prioridad sobre categoria y global."
                    >
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                            <div className="space-y-3">
                                <div className="relative max-w-xl">
                                    <Search className="pointer-events-none absolute top-3 left-3 h-4 w-4 text-neutral-500" />
                                    <Input
                                        value={productSearch}
                                        onChange={(event) =>
                                            setProductSearch(
                                                event.target.value,
                                            )
                                        }
                                        className="bg-white pl-9 dark:bg-zinc-950"
                                        placeholder="Buscar item por codigo, nombre o categoria..."
                                    />
                                </div>

                                <Select
                                    value={selected.product || 'none'}
                                    onValueChange={selectProduct}
                                >
                                    <SelectTrigger className="max-w-xl bg-white dark:bg-zinc-950">
                                        <SelectValue placeholder="Seleccionar item" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Seleccionar item
                                        </SelectItem>
                                        {filteredProducts.map((product) => (
                                            <SelectItem
                                                key={product.code}
                                                value={product.code}
                                            >
                                                {product.code} - {product.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="rounded-lg border border-neutral-200 p-3 dark:border-zinc-800">
                                <p className="text-sm font-medium text-neutral-900 dark:text-zinc-50">
                                    Items con reglas
                                </p>
                                <div className="mt-3 max-h-44 space-y-2 overflow-y-auto">
                                    {specializedItems.length === 0 ? (
                                        <p className="text-xs text-neutral-500">
                                            Sin configuraciones especializadas.
                                        </p>
                                    ) : (
                                        specializedItems.map((item) => (
                                            <button
                                                key={item.scope_key}
                                                type="button"
                                                onClick={() =>
                                                    selectProduct(
                                                        item.scope_key,
                                                    )
                                                }
                                                className="block w-full rounded-md border border-neutral-200 px-3 py-2 text-left text-xs hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                                            >
                                                <span className="block truncate font-medium text-neutral-800 dark:text-zinc-100">
                                                    {item.scope_label}
                                                </span>
                                                <span className="text-neutral-500">
                                                    {item.updated_at ??
                                                        'Sin fecha'}
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>
                        </div>

                        {selected.product === '' ? (
                            <EmptyState text="Selecciona un item para editar sus parametros especializados." />
                        ) : (
                            <SettingsEditor
                                key={`product-${selected.product}`}
                                canEdit={canEdit}
                                scopeType="product"
                                scopeKey={selected.product}
                                scopeLabel={
                                    selectedProduct
                                        ? `${selectedProduct.code} - ${selectedProduct.name}`
                                        : selected.product
                                }
                                payload={settings.product}
                                fallbackSettings={defaultSettings}
                                metricDefinitions={metricDefinitions}
                            />
                        )}
                    </AlertSection>
                </div>
            </div>
        </>
    );
}

function AlertSection({
    title,
    description,
    defaultOpen = false,
    children,
}: {
    title: string;
    description: string;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    return (
        <Collapsible defaultOpen={defaultOpen}>
            <div className="rounded-lg border border-neutral-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                <CollapsibleTrigger className="flex w-full items-center justify-between gap-4 px-4 py-4 text-left">
                    <div>
                        <h2 className="text-base font-semibold text-neutral-900 dark:text-zinc-50">
                            {title}
                        </h2>
                        <p className="mt-1 text-sm text-neutral-500">
                            {description}
                        </p>
                    </div>
                    <ChevronDown className="h-4 w-4 shrink-0 text-neutral-500" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div className="border-t border-neutral-200 px-4 py-4 dark:border-zinc-800">
                        {children}
                    </div>
                </CollapsibleContent>
            </div>
        </Collapsible>
    );
}

function SettingsEditor({
    canEdit,
    scopeType,
    scopeKey,
    scopeLabel,
    payload,
    fallbackSettings,
    metricDefinitions,
}: {
    canEdit: boolean;
    scopeType: 'global' | 'category' | 'product';
    scopeKey: string;
    scopeLabel: string;
    payload: SettingPayload | null;
    fallbackSettings: AlertSettings;
    metricDefinitions: Record<MetricKey, MetricDefinition>;
}) {
    const [settings, setSettings] = useState(
        cloneSettings(payload?.settings ?? fallbackSettings),
    );
    const [saving, setSaving] = useState(false);

    const updateEnabled = (metric: MetricKey, enabled: boolean) => {
        setSettings((current) => ({
            ...current,
            [metric]: {
                ...current[metric],
                enabled,
            },
        }));
    };

    const updateLevel = (
        metric: MetricKey,
        level: AlertLevel,
        value: number,
    ) => {
        const definition = metricDefinitions[metric];
        const nextValue = Math.min(definition.max, Math.max(definition.min, value));

        setSettings((current) => ({
            ...current,
            [metric]: {
                ...current[metric],
                levels: {
                    ...current[metric].levels,
                    [level]: nextValue,
                },
            },
        }));
    };

    const save = () => {
        setSaving(true);
        router.post(
            '/inventory/alerts/settings',
            {
                scope_type: scopeType,
                scope_key: scopeKey,
                settings,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p className="text-sm font-medium text-neutral-900 dark:text-zinc-50">
                        {scopeLabel}
                    </p>
                    <p className="mt-1 text-xs text-neutral-500">
                        {payload?.exists
                            ? 'Configuracion guardada para este alcance.'
                            : 'Usando valores iniciales hasta guardar cambios.'}
                    </p>
                </div>
                <Button onClick={save} disabled={!canEdit || saving}>
                    <Save className="h-4 w-4" />
                    {saving ? 'Guardando...' : 'Guardar parametros'}
                </Button>
            </div>

            {!canEdit && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
                    Tu rol puede consultar estas reglas, pero no modificarlas.
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-5">
                {metricOrder.map((metric) => (
                    <MetricCard
                        key={metric}
                        metric={metric}
                        definition={metricDefinitions[metric]}
                        value={settings[metric]}
                        canEdit={canEdit}
                        onEnabledChange={(enabled) =>
                            updateEnabled(metric, enabled)
                        }
                        onLevelChange={(level, value) =>
                            updateLevel(metric, level, value)
                        }
                    />
                ))}
            </div>
        </div>
    );
}

function MetricCard({
    metric,
    definition,
    value,
    canEdit,
    onEnabledChange,
    onLevelChange,
}: {
    metric: MetricKey;
    definition: MetricDefinition;
    value: MetricSettings;
    canEdit: boolean;
    onEnabledChange: (enabled: boolean) => void;
    onLevelChange: (level: AlertLevel, value: number) => void;
}) {
    const Icon = metricIcons[metric];

    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader className="space-y-3 pb-3">
                <div className="flex items-start justify-between gap-3">
                    <CardTitle className="flex items-start gap-2 text-sm font-semibold">
                        <Icon className="mt-0.5 h-4 w-4 shrink-0 text-neutral-500" />
                        <span>{definition.label}</span>
                    </CardTitle>
                    <Checkbox
                        checked={value.enabled}
                        disabled={!canEdit}
                        onCheckedChange={(checked) =>
                            onEnabledChange(checked === true)
                        }
                    />
                </div>
                <div className="flex items-center gap-2 text-xs text-neutral-500">
                    <ToggleLeft className="h-3 w-3" />
                    {value.enabled ? 'Activo' : 'Inactivo'}
                </div>
            </CardHeader>
            <CardContent className="grid gap-3">
                {(Object.keys(levelLabels) as AlertLevel[]).map((level) => (
                    <div key={level} className="space-y-1">
                        <Label className="text-xs text-neutral-500">
                            {levelLabels[level]}
                        </Label>
                        <div className="flex items-center gap-2">
                            <Input
                                type="number"
                                min={definition.min}
                                max={definition.max}
                                value={value.levels[level]}
                                disabled={!canEdit}
                                onChange={(event) =>
                                    onLevelChange(
                                        level,
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <span className="w-16 text-xs text-neutral-500">
                                {definition.unit}
                            </span>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="rounded-lg border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-500 dark:border-zinc-700">
            {text}
        </div>
    );
}

InventoryAlertsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inventario',
            href: '/inventory/products',
        },
        {
            title: 'Alertas',
            href: '/inventory/alerts',
        },
    ],
};

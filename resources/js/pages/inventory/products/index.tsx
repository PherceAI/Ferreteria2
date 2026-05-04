import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArchiveX,
    Package,
    PackageX,
    Search,
    Tag,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type InventoryProduct = {
    id: number;
    code: string;
    name: string;
    unit: string | null;
    current_stock: number;
    cost: number | null;
    sale_price: number | null;
    min_stock: number;
    inventory_updated_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProducts = {
    data: InventoryProduct[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
};

type InventoryStats = {
    total: number;
    low_stock: number;
    zero_stock: number;
    without_price: number;
};

type Props = {
    products: PaginatedProducts;
    stats: InventoryStats;
    filters: {
        search: string;
        per_page: number;
    };
};

const currencyFormatter = new Intl.NumberFormat('es-EC', {
    style: 'currency',
    currency: 'USD',
});

const numberFormatter = new Intl.NumberFormat('es-EC', {
    maximumFractionDigits: 3,
});

function money(value: number | null): string {
    return value === null ? 'Pendiente' : currencyFormatter.format(value);
}

function stockStatus(product: InventoryProduct): {
    label: string;
    className: string;
} {
    if (product.current_stock <= 0) {
        return {
            label: 'Sin stock',
            className:
                'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
        };
    }

    if (product.min_stock > 0 && product.current_stock <= product.min_stock) {
        return {
            label: 'Bajo',
            className:
                'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
        };
    }

    return {
        label: 'Disponible',
        className:
            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
    };
}

export default function ProductsIndex({ products, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            router.get(
                '/inventory/products',
                {
                    search,
                    per_page: filters.per_page,
                },
                {
                    only: ['products', 'stats', 'filters'],
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [filters.per_page, filters.search, search]);

    const kpis = useMemo(
        () => [
            {
                title: 'Productos',
                value: numberFormatter.format(stats.total),
                desc: 'en sucursal matriz',
                icon: Package,
                color: 'text-sky-600',
                bg: 'bg-sky-50 dark:bg-sky-950/30',
            },
            {
                title: 'Sin stock',
                value: numberFormatter.format(stats.zero_stock),
                desc: 'requieren revisión',
                icon: ArchiveX,
                color: 'text-red-600',
                bg: 'bg-red-50 dark:bg-red-950/30',
            },
            {
                title: 'Stock bajo',
                value: numberFormatter.format(stats.low_stock),
                desc: 'según mínimo definido',
                icon: PackageX,
                color: 'text-amber-600',
                bg: 'bg-amber-50 dark:bg-amber-950/30',
            },
            {
                title: 'Sin precio',
                value: numberFormatter.format(stats.without_price),
                desc: 'pendientes de costeo',
                icon: Tag,
                color: 'text-violet-600',
                bg: 'bg-violet-50 dark:bg-violet-950/30',
            },
        ],
        [stats],
    );

    return (
        <>
            <Head title="Inventario | Productos" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        Productos
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Stock independiente de la sucursal matriz, consultado
                        por páginas.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpis.map((kpi) => (
                        <Card
                            key={kpi.title}
                            className="flex min-h-32 flex-col justify-between border border-neutral-200 p-5 shadow-none dark:border-zinc-800"
                        >
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    {kpi.title}
                                </div>
                                <div
                                    className={`flex h-8 w-8 items-center justify-center rounded-md ${kpi.bg}`}
                                >
                                    <kpi.icon
                                        className={`h-4 w-4 ${kpi.color}`}
                                    />
                                </div>
                            </div>
                            <div>
                                <div className="text-3xl font-bold text-neutral-900 dark:text-zinc-50">
                                    {kpi.value}
                                </div>
                                <div className="mt-1 text-xs text-neutral-500">
                                    {kpi.desc}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>

                <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                    <div className="flex flex-col gap-3 border-b border-neutral-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-zinc-800">
                        <div className="relative flex w-full max-w-md items-center">
                            <Search className="pointer-events-none absolute left-3 h-4 w-4 text-neutral-500 dark:text-zinc-400" />
                            <Input
                                type="search"
                                placeholder="Buscar código o producto..."
                                className="w-full bg-white pl-9 shadow-sm dark:bg-zinc-950"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>

                        <Select
                            value={String(filters.per_page)}
                            onValueChange={(value) =>
                                router.get(
                                    '/inventory/products',
                                    {
                                        search: filters.search,
                                        per_page: value,
                                    },
                                    {
                                        only: ['products', 'stats', 'filters'],
                                        preserveScroll: true,
                                        preserveState: true,
                                        replace: true,
                                    },
                                )
                            }
                        >
                            <SelectTrigger className="w-36 bg-white dark:bg-zinc-950">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="25">
                                    25 por página
                                </SelectItem>
                                <SelectItem value="50">
                                    50 por página
                                </SelectItem>
                                <SelectItem value="100">
                                    100 por página
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-neutral-200 bg-neutral-50/80 text-neutral-500 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Código
                                    </th>
                                    <th className="min-w-80 px-4 py-3 font-medium">
                                        Producto
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Unidad
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Stock
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Mínimo
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Costo
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Venta
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actualizado
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800">
                                {products.data.map((product) => {
                                    const status = stockStatus(product);

                                    return (
                                        <tr
                                            key={product.id}
                                            className="transition-colors hover:bg-neutral-50/70 dark:hover:bg-zinc-900/50"
                                        >
                                            <td className="px-4 py-3 font-medium whitespace-nowrap text-neutral-900 dark:text-zinc-100">
                                                {product.code}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-700 dark:text-zinc-300">
                                                {product.name}
                                            </td>
                                            <td className="px-4 py-3 text-center text-neutral-500">
                                                {product.unit ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold text-neutral-900 dark:text-zinc-100">
                                                {numberFormatter.format(
                                                    product.current_stock,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-neutral-500">
                                                {numberFormatter.format(
                                                    product.min_stock,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-neutral-500">
                                                {money(product.cost)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-neutral-500">
                                                {money(product.sale_price)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant="secondary"
                                                    className={`${status.className} shadow-none hover:bg-transparent`}
                                                >
                                                    {status.label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap text-neutral-500">
                                                {product.inventory_updated_at ??
                                                    '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {products.data.length === 0 ? (
                        <div className="flex min-h-44 flex-col items-center justify-center gap-2 px-4 py-8 text-center text-neutral-500">
                            <AlertTriangle className="h-5 w-5" />
                            <p className="text-sm">
                                No hay productos para la búsqueda actual.
                            </p>
                        </div>
                    ) : null}

                    <div className="flex flex-col gap-3 border-t border-neutral-200 px-4 py-3 text-sm text-neutral-500 md:flex-row md:items-center md:justify-between dark:border-zinc-800">
                        <span>
                            Mostrando {products.from ?? 0} - {products.to ?? 0}{' '}
                            de {numberFormatter.format(products.total)}
                        </span>

                        <div className="flex flex-wrap items-center gap-1">
                            {products.links.map((link, index) => (
                                <Button
                                    key={`${link.label}-${index}`}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    disabled={link.url === null}
                                    asChild={link.url !== null}
                                >
                                    {link.url === null ? (
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            only={[
                                                'products',
                                                'stats',
                                                'filters',
                                            ]}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                </Card>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inventario',
            href: '/inventory/products',
        },
        {
            title: 'Productos',
            href: '/inventory/products',
        },
    ],
};

import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArchiveX,
    Package,
    PackageX,
    Search,
    Tags,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
    last_purchase_cost: number | null;
    total_cost: number | null;
    supplier_name: string | null;
    category_name: string | null;
    subcategory_name: string | null;
    min_stock: number;
    inventory_updated_at: string | null;
    valued_inventory_updated_at: string | null;
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
    valued: number;
    inventory_value: number;
};

type Props = {
    products: PaginatedProducts;
    stats: InventoryStats;
    filterOptions: {
        suppliers: string[];
        categories: string[];
        subcategories: string[];
    };
    filters: {
        search: string;
        supplier: string;
        category: string;
        subcategory: string;
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

function paginationLabel(label: string): string {
    const clean = label.replace(/&laquo;|&raquo;/g, '').trim();

    if (clean === 'pagination.previous') {
        return 'Anterior';
    }

    if (clean === 'pagination.next') {
        return 'Siguiente';
    }

    return clean;
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

export default function ProductsIndex({
    products,
    stats,
    filterOptions,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = useCallback(
        (next: Partial<Props['filters']>) => {
            router.get(
                '/inventory/products',
                {
                    search: next.search ?? search,
                    supplier: next.supplier ?? filters.supplier,
                    category: next.category ?? filters.category,
                    subcategory: next.subcategory ?? filters.subcategory,
                    per_page: next.per_page ?? filters.per_page,
                },
                {
                    only: ['products', 'stats', 'filters', 'filterOptions'],
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        },
        [
            filters.category,
            filters.per_page,
            filters.subcategory,
            filters.supplier,
            search,
        ],
    );

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            applyFilters({ search });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [applyFilters, filters.search, search]);

    const kpis = useMemo(
        () => [
            {
                title: 'Productos',
                value: numberFormatter.format(stats.total),
                desc: 'en sucursal activa',
                icon: Package,
                color: 'text-sky-600',
                bg: 'bg-sky-50 dark:bg-sky-950/30',
            },
            {
                title: 'Sin stock',
                value: numberFormatter.format(stats.zero_stock),
                desc: 'requieren revision',
                icon: ArchiveX,
                color: 'text-red-600',
                bg: 'bg-red-50 dark:bg-red-950/30',
            },
            {
                title: 'Stock bajo',
                value: numberFormatter.format(stats.low_stock),
                desc: 'segun minimo definido',
                icon: PackageX,
                color: 'text-amber-600',
                bg: 'bg-amber-50 dark:bg-amber-950/30',
            },
            {
                title: 'Valor inventario',
                value: currencyFormatter.format(stats.inventory_value),
                desc: `${numberFormatter.format(stats.valued)} productos valorados`,
                icon: Tags,
                color: 'text-emerald-600',
                bg: 'bg-emerald-50 dark:bg-emerald-950/30',
            },
        ],
        [stats],
    );

    return (
        <>
            <Head title="Inventario | Productos" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                        Productos
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Stock y costo valorado por codigo, con filtros por
                        proveedor, categoria y subcategoria.
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
                                <div className="text-3xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
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
                    <div className="flex flex-col gap-3 border-b border-neutral-200 px-4 py-4 lg:flex-row lg:items-center dark:border-zinc-800">
                        <div className="relative flex min-w-64 flex-1 items-center">
                            <Search className="pointer-events-none absolute left-3 h-4 w-4 text-neutral-500 dark:text-zinc-400" />
                            <Input
                                type="search"
                                placeholder="Buscar codigo o producto..."
                                className="h-11 w-full bg-white pl-9 shadow-sm dark:bg-zinc-950"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-none lg:items-center">
                            <Select
                                value={filters.supplier || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        supplier: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="h-11 bg-white sm:min-w-56 lg:w-56 dark:bg-zinc-950">
                                    <SelectValue placeholder="Proveedor" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todos los proveedores
                                    </SelectItem>
                                    {filterOptions.suppliers.map((supplier) => (
                                        <SelectItem
                                            key={supplier}
                                            value={supplier}
                                        >
                                            {supplier}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.category || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        category: value === 'all' ? '' : value,
                                        subcategory: '',
                                    })
                                }
                            >
                                <SelectTrigger className="h-11 bg-white sm:min-w-52 lg:w-52 dark:bg-zinc-950">
                                    <SelectValue placeholder="Categoria" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todas las categorias
                                    </SelectItem>
                                    {filterOptions.categories.map(
                                        (category) => (
                                            <SelectItem
                                                key={category}
                                                value={category}
                                            >
                                                {category}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.subcategory || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        subcategory:
                                            value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="h-11 bg-white sm:min-w-56 lg:w-56 dark:bg-zinc-950">
                                    <SelectValue placeholder="Subcategoria" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Todas las subcategorias
                                    </SelectItem>
                                    {filterOptions.subcategories.map(
                                        (subcategory) => (
                                            <SelectItem
                                                key={subcategory}
                                                value={subcategory}
                                            >
                                                {subcategory}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>

                            <Select
                                value={String(filters.per_page)}
                                onValueChange={(value) =>
                                    applyFilters({ per_page: Number(value) })
                                }
                            >
                                <SelectTrigger className="h-11 bg-white sm:min-w-36 lg:w-36 dark:bg-zinc-950">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="25">
                                        25 por pagina
                                    </SelectItem>
                                    <SelectItem value="50">
                                        50 por pagina
                                    </SelectItem>
                                    <SelectItem value="100">
                                        100 por pagina
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-neutral-200 bg-neutral-50/80 text-neutral-500 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400">
                                <tr>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Codigo
                                    </th>
                                    <th className="min-w-80 px-4 py-3 font-medium">
                                        Producto
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Unidad
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Stock
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Minimo
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Ult. compra
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Costo total
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium">
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
                                            <td className="px-4 py-3 text-center font-medium whitespace-nowrap text-neutral-900 dark:text-zinc-100">
                                                {product.code}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-700 dark:text-zinc-300">
                                                <div>
                                                    <p>{product.name}</p>
                                                    {(product.category_name ||
                                                        product.subcategory_name) && (
                                                        <p className="mt-1 text-xs text-neutral-400">
                                                            {[
                                                                product.category_name,
                                                                product.subcategory_name,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' / ')}
                                                        </p>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center text-neutral-500">
                                                {product.unit ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center font-semibold text-neutral-900 dark:text-zinc-100">
                                                {numberFormatter.format(
                                                    product.current_stock,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center text-neutral-500">
                                                {numberFormatter.format(
                                                    product.min_stock,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center text-neutral-500">
                                                {money(
                                                    product.last_purchase_cost ??
                                                        product.cost,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center font-medium text-neutral-700 dark:text-zinc-300">
                                                {money(product.total_cost)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant="secondary"
                                                    className={`${status.className} shadow-none hover:bg-transparent`}
                                                >
                                                    {status.label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-center whitespace-nowrap text-neutral-500">
                                                {product.valued_inventory_updated_at ??
                                                    product.inventory_updated_at ??
                                                    '-'}
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
                                No hay productos para la busqueda o filtros
                                actuales.
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
                                        <span>
                                            {paginationLabel(link.label)}
                                        </span>
                                    ) : (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            only={[
                                                'products',
                                                'stats',
                                                'filters',
                                                'filterOptions',
                                            ]}
                                        >
                                            {paginationLabel(link.label)}
                                        </Link>
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

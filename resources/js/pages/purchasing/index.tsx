import { Head, Link } from '@inertiajs/react';
import {
    ClipboardList,
    Lightbulb,
    PackageSearch,
    ShoppingCart,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Suggestion = {
    id: number;
    code: string;
    name: string;
    branch: string;
    stock: number;
    min: number;
    suggestedQty: number;
    supplier: string;
    lastPrice: number | null;
    reason: string;
};

type BranchColumn = {
    id: number;
    name: string;
};

type MatrixRow = {
    code: string;
    name: string;
    total: number;
    branches: Array<{
        branchId: number;
        stock: number;
        low: boolean;
    }>;
};

type Props = {
    stats: {
        activeSuggestions: number;
        awaitingReceipts: number;
        estimatedRestockValue: number;
    };
    suggestions: Suggestion[];
    branches: BranchColumn[];
    stockMatrix: MatrixRow[];
};

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(value);
}

function formatQty(value: number): string {
    return new Intl.NumberFormat('es-EC', {
        maximumFractionDigits: 2,
    }).format(value);
}

export default function PurchasingIndex({
    stats,
    suggestions,
    branches,
    stockMatrix,
}: Props) {
    const [reviewIds, setReviewIds] = useState<number[]>([]);

    const selectedSuggestions = useMemo(
        () =>
            suggestions.filter((suggestion) =>
                reviewIds.includes(suggestion.id),
            ),
        [reviewIds, suggestions],
    );

    const estimatedTotal = selectedSuggestions.reduce(
        (total, suggestion) =>
            total + suggestion.suggestedQty * (suggestion.lastPrice ?? 0),
        0,
    );

    const addToReview = (id: number) => {
        setReviewIds((current) =>
            current.includes(id) ? current : [...current, id],
        );
    };

    return (
        <>
            <Head title="Compras Inteligentes" />

            <div className="flex flex-col gap-6 p-6 pb-32 font-sans">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        Compras Inteligentes
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Reposicion sugerida desde stock minimo y disponibilidad
                        real por bodega.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Sugerencias activas
                            </CardTitle>
                            <Lightbulb className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">
                                {stats.activeSuggestions}
                            </div>
                            <span className="text-xs text-neutral-500">
                                Productos bajo minimo en la sucursal actual
                            </span>
                        </CardContent>
                    </Card>
                    <Link href="/compras/recepcion">
                        <Card className="cursor-pointer border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-neutral-500">
                                    Recepciones pendientes
                                </CardTitle>
                                <ClipboardList className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">
                                    {stats.awaitingReceipts}
                                </div>
                                <span className="text-xs text-neutral-500">
                                    Facturas esperando bodega o en recepcion
                                </span>
                            </CardContent>
                        </Card>
                    </Link>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Reposicion estimada
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold text-green-700 dark:text-green-400">
                                {formatCurrency(stats.estimatedRestockValue)}
                            </div>
                            <span className="text-xs text-neutral-500">
                                Calculado con ultimo costo disponible
                            </span>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-4 rounded-lg border border-neutral-200 bg-white p-5 shadow-none dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-lg font-semibold text-neutral-900 dark:text-zinc-50">
                            Sugerencias de compra
                        </h2>
                        <p className="text-sm text-neutral-500">
                            La cantidad sugerida repone al menos el doble del
                            minimo configurado.
                        </p>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-zinc-800">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Producto
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Sucursal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Stock
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Minimo
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Sugerido
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Proveedor
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Accion
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                {suggestions.length > 0 ? (
                                    suggestions.map((suggestion) => (
                                        <tr
                                            key={suggestion.id}
                                            className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {suggestion.name}
                                                </div>
                                                <div className="text-xs text-neutral-500">
                                                    {suggestion.code} /{' '}
                                                    {suggestion.reason}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {suggestion.branch}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-red-600">
                                                {formatQty(suggestion.stock)}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500">
                                                {formatQty(suggestion.min)}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-blue-700 dark:text-blue-400">
                                                {formatQty(
                                                    suggestion.suggestedQty,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500">
                                                {suggestion.supplier}
                                                <br />
                                                <span className="text-xs">
                                                    Ultimo costo:{' '}
                                                    {suggestion.lastPrice ===
                                                    null
                                                        ? 'sin dato'
                                                        : formatCurrency(
                                                              suggestion.lastPrice,
                                                          )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        addToReview(
                                                            suggestion.id,
                                                        )
                                                    }
                                                    disabled={reviewIds.includes(
                                                        suggestion.id,
                                                    )}
                                                    className="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                                                >
                                                    {reviewIds.includes(
                                                        suggestion.id,
                                                    )
                                                        ? 'En revision'
                                                        : 'Agregar'}
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-sm text-neutral-500"
                                        >
                                            No hay productos bajo minimo para la
                                            sucursal actual.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-col gap-4 rounded-lg border border-neutral-200 bg-white p-5 shadow-none dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="flex items-start gap-3 rounded-lg border border-blue-100 bg-blue-50/70 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                        <PackageSearch className="mt-0.5 h-5 w-5 shrink-0 text-blue-700 dark:text-blue-400" />
                        <p className="text-sm font-medium text-blue-800 dark:text-blue-400">
                            Antes de comprar al proveedor, revisa si otra bodega
                            tiene stock disponible para un traspaso interno.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-center text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-zinc-800">
                                    <th className="min-w-[260px] px-4 py-3 text-left font-medium">
                                        Producto
                                    </th>
                                    {branches.map((branch) => (
                                        <th
                                            key={branch.id}
                                            className="min-w-[120px] bg-neutral-50 px-4 py-3 font-medium dark:bg-zinc-800/30"
                                        >
                                            {branch.name}
                                        </th>
                                    ))}
                                    <th className="bg-neutral-100 px-4 py-3 font-bold dark:bg-zinc-800">
                                        Total
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                {stockMatrix.length > 0 ? (
                                    stockMatrix.map((row) => (
                                        <tr key={row.code}>
                                            <td className="px-4 py-3 text-left">
                                                <div className="font-medium">
                                                    {row.name}
                                                </div>
                                                <div className="text-xs text-neutral-500">
                                                    {row.code}
                                                </div>
                                            </td>
                                            {row.branches.map((branchStock) => (
                                                <td
                                                    key={branchStock.branchId}
                                                    className={`px-4 py-3 ${
                                                        branchStock.low
                                                            ? 'bg-red-50/50 font-bold text-red-600 dark:bg-red-950/20'
                                                            : ''
                                                    }`}
                                                >
                                                    {formatQty(
                                                        branchStock.stock,
                                                    )}
                                                </td>
                                            ))}
                                            <td className="bg-neutral-50 px-4 py-3 font-bold dark:bg-zinc-800/10">
                                                {formatQty(row.total)}
                                            </td>
                                            <td className="px-4 py-3 text-left">
                                                <Link
                                                    href={`/inventory/transfers?product_search=${encodeURIComponent(row.code)}`}
                                                    className="inline-flex items-center gap-1 rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400"
                                                >
                                                    Solicitar traspaso
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={branches.length + 3}
                                            className="px-4 py-8 text-center text-sm text-neutral-500"
                                        >
                                            No hay productos para comparar entre
                                            bodegas.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {reviewIds.length > 0 && (
                    <div className="fixed right-0 bottom-0 left-0 z-40 md:pl-64">
                        <div className="mx-auto flex w-full max-w-7xl items-center justify-between border-t border-neutral-200 bg-white px-6 py-4 shadow-[0_-10px_40px_rgba(0,0,0,0.08)] dark:border-zinc-800 dark:bg-zinc-950">
                            <div>
                                <p className="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Lista de revision: {reviewIds.length}{' '}
                                    productos
                                </p>
                                <p className="text-sm text-neutral-500">
                                    Estimado con costos disponibles:{' '}
                                    {formatCurrency(estimatedTotal)}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                className="rounded-lg"
                                onClick={() => setReviewIds([])}
                            >
                                Limpiar lista
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PurchasingIndex.layout = {
    breadcrumbs: [
        { title: 'Compras', href: '/compras' },
        { title: 'Sugerencias', href: '/compras' },
    ],
};

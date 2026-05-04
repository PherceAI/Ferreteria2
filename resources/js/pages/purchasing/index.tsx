import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Lightbulb,
    ShoppingCart,
    TrendingDown,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MOCK_PURCHASE_SUGGESTIONS, MOCK_PRODUCTS } from '@/data/mock';

export default function PurchasingIndex() {
    const suggestions = MOCK_PURCHASE_SUGGESTIONS;
    const [cart, setCart] = useState<any[]>([]);

    const addToCart = (product: any) => {
        if (!cart.find((item) => item.id === product.id)) {
            setCart([...cart, product]);
        }
    };

    return (
        <>
            <Head title="Compras Inteligentes" />

            <div className="flex flex-col gap-6 p-6 pb-32 font-sans">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                            Compras Inteligentes
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-zinc-400">
                            Sugerencias basadas en rotación real del inventario
                        </p>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Sugerencias Activas
                            </CardTitle>
                            <Lightbulb className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">8</div>
                            <span className="text-xs text-neutral-500">
                                Productos necesitan reposición
                            </span>
                        </CardContent>
                    </Card>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Órdenes Pendientes
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">2</div>
                            <span className="text-xs text-neutral-500">
                                Esperando aprobación gerencial
                            </span>
                        </CardContent>
                    </Card>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Ahorro Estimado
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold text-green-600">
                                $340.00
                            </div>
                            <span className="text-xs text-neutral-500">
                                vs compras reactivas del mes anterior
                            </span>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Table: Sugerencias */}
                <div className="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 className="text-lg font-semibold text-neutral-900 dark:text-zinc-50">
                        Sugerencias de Compra
                    </h2>
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
                                        Stock Actual
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Mín.
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Consumo Diario
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Hasta agotamiento
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Qty Sugerida
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Proveedor
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                {suggestions.map((s, idx) => (
                                    <tr
                                        key={idx}
                                        className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {s.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.branch}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {s.stock}
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {s.min}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.dailyConsumption}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.daysLeft < 5 ? (
                                                <span className="flex items-center gap-1 font-semibold text-red-600">
                                                    <AlertTriangle className="h-3.5 w-3.5" />{' '}
                                                    ⚠️ {s.daysLeft} días
                                                </span>
                                            ) : s.daysLeft <= 10 ? (
                                                <span className="font-semibold text-amber-500">
                                                    🟡 {s.daysLeft} días
                                                </span>
                                            ) : (
                                                <span className="font-semibold text-green-600">
                                                    ✅ {s.daysLeft} días
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-blue-600">
                                            {s.suggestedQty}
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {s.supplier} <br />
                                            <span className="text-xs">
                                                Ult. {s.lastPrice}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {s.suggestedQty !== '—' ? (
                                                <Button
                                                    size="sm"
                                                    onClick={() => addToCart(s)}
                                                    disabled={cart.find(
                                                        (item) =>
                                                            item.id === s.id,
                                                    )}
                                                    className="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900"
                                                >
                                                    {cart.find(
                                                        (item) =>
                                                            item.id === s.id,
                                                    )
                                                        ? 'Agregado'
                                                        : 'Agregar a OC'}
                                                </Button>
                                            ) : (
                                                <span className="text-neutral-400">
                                                    Sin acción
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Heatmap: Visibilidad entre sucursales */}
                <div className="mt-4 flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="flex rounded-lg border border-blue-100 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                        <p className="text-sm font-medium text-blue-800 dark:text-blue-400">
                            💡 <strong>Antes de comprar al proveedor</strong>,
                            verifica el stock en tus otras sucursales para
                            transferir mercadería inmovilizada.
                        </p>
                    </div>
                    <div className="mt-2 overflow-x-auto">
                        <table className="w-full border-collapse text-center text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200">
                                    <th className="px-4 py-3 text-left font-medium">
                                        Producto
                                    </th>
                                    <th className="bg-neutral-50 px-4 py-3 font-medium dark:bg-zinc-800/30">
                                        Matriz
                                    </th>
                                    <th className="bg-neutral-50 px-4 py-3 font-medium dark:bg-zinc-800/30">
                                        Norte
                                    </th>
                                    <th className="bg-neutral-50 px-4 py-3 font-medium dark:bg-zinc-800/30">
                                        Guano
                                    </th>
                                    <th className="bg-neutral-50 px-4 py-3 font-medium dark:bg-zinc-800/30">
                                        Chambo
                                    </th>
                                    <th className="bg-neutral-100 px-4 py-3 font-bold dark:bg-zinc-800">
                                        TOTAL
                                    </th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                {MOCK_PRODUCTS.slice(0, 3).map((prod) => (
                                    <tr key={prod.id}>
                                        <td className="px-4 py-3 text-left font-medium">
                                            {prod.name}
                                        </td>
                                        <td
                                            className={`px-4 py-3 ${prod.stock_matriz < 50 ? 'bg-red-50/30 font-bold text-red-600' : ''}`}
                                        >
                                            {prod.stock_matriz}{' '}
                                            {prod.stock_matriz < 50 && '⚠️'}
                                        </td>
                                        <td
                                            className={`px-4 py-3 ${prod.stock_norte < 50 ? 'bg-red-50/30 font-bold text-red-600' : ''}`}
                                        >
                                            {prod.stock_norte}{' '}
                                            {prod.stock_norte < 50 && '⚠️'}
                                        </td>
                                        <td
                                            className={`px-4 py-3 ${prod.stock_guano < 50 ? 'bg-red-50/30 font-bold text-red-600' : ''}`}
                                        >
                                            {prod.stock_guano}{' '}
                                            {prod.stock_guano < 50 && '⚠️'}
                                        </td>
                                        <td
                                            className={`px-4 py-3 ${prod.stock_chambo < 50 ? 'bg-red-50/30 font-bold text-red-600' : ''}`}
                                        >
                                            {prod.stock_chambo}{' '}
                                            {prod.stock_chambo < 50 && '⚠️'}
                                        </td>
                                        <td className="bg-neutral-50 px-4 py-3 font-bold dark:bg-zinc-800/10">
                                            {prod.stock_matriz +
                                                prod.stock_norte +
                                                prod.stock_guano +
                                                prod.stock_chambo}
                                        </td>
                                        <td className="px-4 py-3 text-left">
                                            {prod.id === 'VAR-12M' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-400"
                                                >
                                                    Solicitar Traspaso desde
                                                    Norte
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Floating Bottom Bar (Cart Demo) */}
                {cart.length > 0 && (
                    <div className="fixed right-0 bottom-0 left-0 z-40 md:pl-64">
                        <div className="mx-auto flex w-full max-w-7xl items-center justify-between border-t border-neutral-200 bg-white px-6 py-4 shadow-[0_-10px_40px_rgba(0,0,0,0.08)] dark:border-zinc-800 dark:bg-zinc-950">
                            <div>
                                <p className="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Orden de Compra en preparación:{' '}
                                    {cart.length} productos
                                </p>
                                <p className="text-sm text-neutral-500">
                                    Total estimado: $
                                    {(cart.length * 450.5).toFixed(2)}
                                </p>
                            </div>
                            <Button
                                className="bg-red-600 font-medium text-white hover:bg-red-700"
                                onClick={() => setCart([])}
                            >
                                Revisar y Enviar a Aprobación
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

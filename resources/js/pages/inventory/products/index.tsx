import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Package,
    PackageX,
    Search,
    TrendingUp,
    Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

// =======================
// DATOS MOCK PARA DISEÑO
// =======================
const kpiData = [
    {
        title: 'Stock Bajo',
        value: '14',
        icon: PackageX,
        color: 'text-red-500',
        bg: 'bg-red-50 dark:bg-red-950/20',
        desc: 'Artículos bajo el mínimo',
    },
    {
        title: 'Por Vencerse',
        value: '3',
        icon: AlertTriangle,
        color: 'text-amber-500',
        bg: 'bg-amber-50 dark:bg-amber-950/20',
        desc: 'Próximos 30 días',
    },
    {
        title: 'En Tránsito',
        value: '5',
        icon: Truck,
        color: 'text-blue-500',
        bg: 'bg-blue-50 dark:bg-blue-950/20',
        desc: 'Órdenes en camino',
    },
    {
        title: 'Top Ventas',
        value: 'Cemento H.',
        icon: TrendingUp,
        color: 'text-emerald-500',
        bg: 'bg-emerald-50 dark:bg-emerald-950/20',
        desc: 'Líder del mes',
    },
];

const catalogData = [
    {
        code: 'CEM-001',
        name: 'Cemento Holcim Fuerte 50Kg',
        cost: 7.15,
        price: 8.5,
        minStock: 50,
        actualStock: 120,
    },
    {
        code: 'VAR-12M',
        name: 'Varilla Corrugada 12mm',
        cost: 4.5,
        price: 5.8,
        minStock: 200,
        actualStock: 310,
    },
    {
        code: 'TUB-PVC',
        name: 'Tubo PVC 1/2" Tigre',
        cost: 1.8,
        price: 2.5,
        minStock: 100,
        actualStock: 45, // Bajo
    },
    {
        code: 'PNT-LAT',
        name: 'Pintura Suprema Látex Galón',
        cost: 14.0,
        price: 18.5,
        minStock: 15,
        actualStock: 12, // Bajo
    },
    {
        code: 'CLV-ACR',
        name: 'Clavo Acero 2" Cajas',
        cost: 1.1,
        price: 1.5,
        minStock: 50,
        actualStock: 180,
    },
    {
        code: 'SEL-SIL',
        name: 'Sellador Silicona Transparente',
        cost: 3.2,
        price: 4.9,
        minStock: 20,
        actualStock: 25, // Cerca del mínimo
    },
];

export default function ProductsIndex() {
    return (
        <>
            <Head title="Inventario | Productos" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                {/* Cabecera */}
                <div className="flex flex-col gap-1 tracking-[-0.02em]">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        Catálogo de Productos
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Monitor inteligente del inventario de la ferretería.
                    </p>
                </div>

                {/* KPIs (Las 4 Tarjetas Clave) */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {kpiData.map((kpi) => (
                        <Card
                            key={kpi.title}
                            className="border border-neutral-200 shadow-none dark:border-zinc-800 p-5 flex flex-col justify-between"
                        >
                            <div className="flex flex-row items-center justify-between mb-3">
                                <div className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    {kpi.title}
                                </div>
                                <div
                                    className={`flex h-8 w-8 items-center justify-center rounded-md ${kpi.bg}`}
                                >
                                    <kpi.icon className={`h-4 w-4 ${kpi.color}`} />
                                </div>
                            </div>
                            <div>
                                <div className="text-4xl font-bold tracking-tight text-neutral-900 dark:text-zinc-50">
                                    {kpi.value}
                                </div>
                                <div className="mt-1 flex items-center text-xs text-neutral-500">
                                    <span>{kpi.desc}</span>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Contenedor Principal: Búsqueda y Tabla */}
                <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                    <div className="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-zinc-800">
                        {/* Barra de Búsqueda */}
                        <div className="relative w-full max-w-sm flex items-center">
                            <Search className="absolute left-3 h-4 w-4 text-neutral-500 dark:text-zinc-400 pointer-events-none" />
                            <Input
                                type="search"
                                placeholder="Buscar código o nombre de producto..."
                                className="w-full bg-white pl-9 shadow-sm dark:bg-zinc-950"
                            />
                        </div>
                    </div>

                    {/* Tabla de Productos Estilo Flat */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-neutral-200 bg-neutral-50/50 text-neutral-500 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Código</th>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium text-center">Costo</th>
                                    <th className="px-4 py-3 font-medium text-center">Precio Venta</th>
                                    <th className="px-4 py-3 font-medium text-center">Min</th>
                                    <th className="px-4 py-3 font-medium text-center">Stock Actual</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800">
                                {catalogData.map((item) => {
                                    const isCritical = item.actualStock < item.minStock;
                                    const isWarning =
                                        item.actualStock >= item.minStock &&
                                        item.actualStock <= item.minStock * 1.25;

                                    let stockColor = 'text-green-600 dark:text-green-500';
                                    let stockBg = 'bg-green-50 dark:bg-green-900/20';
                                    if (isCritical) {
                                        stockColor = 'text-red-600 dark:text-red-500';
                                        stockBg = 'bg-red-50 dark:bg-red-900/20';
                                    } else if (isWarning) {
                                        stockColor = 'text-amber-600 dark:text-amber-500';
                                        stockBg = 'bg-amber-50 dark:bg-amber-900/20';
                                    }

                                    return (
                                        <tr
                                            key={item.code}
                                            className="transition-colors hover:bg-neutral-50/50 dark:hover:bg-zinc-900/50"
                                        >
                                            <td className="whitespace-nowrap px-4 py-3 font-medium text-neutral-900 dark:text-zinc-100">
                                                {item.code}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-700 dark:text-zinc-300">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500 text-center">
                                                ${item.cost.toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-neutral-900 dark:text-zinc-100 text-center">
                                                ${item.price.toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500 text-center">
                                                {item.minStock}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant="secondary"
                                                    className={`${stockBg} ${stockColor} hover:bg-transparent shadow-none`}
                                                >
                                                    {item.actualStock}
                                                </Badge>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
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

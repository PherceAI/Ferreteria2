import { Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Building2,
    CalendarDays,
    Package,
    ShieldAlert,
    ShoppingCart,
} from 'lucide-react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { TooltipProvider } from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import type { Auth } from '@/types';

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    if (hour < 18) {
        return 'Buenas tardes';
    }

    return 'Buenas noches';
}

function formatDate(): string {
    return new Date().toLocaleDateString('es-EC', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

// =======================
// DATOS MOCK PARA DISEÑO
// =======================
const saleTrendsData = [
    { name: 'Lun', actual: 4000, anterior: 2400 },
    { name: 'Mar', actual: 3000, anterior: 1398 },
    { name: 'Mié', actual: 2000, anterior: 9800 },
    { name: 'Jue', actual: 2780, anterior: 3908 },
    { name: 'Vie', actual: 1890, anterior: 4800 },
    { name: 'Sáb', actual: 5390, anterior: 3800 },
];

const branchSalesData = [
    { name: 'Matriz', total: 6540 },
    { name: 'Norte', total: 3200 },
    { name: 'Guano', total: 1250 },
    { name: 'Chambo', total: 2100 },
];

const topProducts = [
    { id: 1, name: 'Cemento Holcim Fuerte', qty: '520 qq', trend: 'up' },
    { id: 2, name: 'Varilla Corrugada 12mm', qty: '310 U', trend: 'up' },
    { id: 3, name: 'Tubo PVC 1/2 Tigre', qty: '250 U', trend: 'down' },
    { id: 4, name: 'Clavo Acero 2"', qty: '180 cjs', trend: 'up' },
    { id: 5, name: 'Pintura Suprema Látex', qty: '45 gal', trend: 'down' },
];

const ticketsAverage = [
    { branch: 'Riobamba Matriz', value: '$45.00' },
    { branch: 'Riobamba Norte', value: '$32.50' },
    { branch: 'Chambo', value: '$25.00' },
    { branch: 'Guano', value: '$18.00' },
];

export default function Dashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const firstName = auth.user.name.split(' ')[0];
    const branchName = auth.activeBranch?.name ?? 'Sin sucursal';

    const [showSellers, setShowSellers] = useState(false);
    const [showCxC, setShowCxC] = useState(false);

    return (
        <TooltipProvider>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                {/* Cabecera / Saludo */}
                <div className="flex flex-col gap-1 tracking-[-0.02em]">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        {greeting()}, {firstName}
                    </h1>
                    <div className="flex items-center gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                        <Building2 className="h-3.5 w-3.5" />
                        <span>{branchName}</span>
                        <span className="text-neutral-300 dark:text-zinc-700">
                            ·
                        </span>
                        <CalendarDays className="h-3.5 w-3.5" />
                        <span className="capitalize">{formatDate()}</span>
                    </div>
                </div>

                <Separator className="bg-neutral-200 dark:bg-zinc-800" />

                {/* 1. CAPA: EL CORAZÓN (KPIs de Supervivencia) */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        onClick={() => setShowSellers(!showSellers)}
                    >
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                Venta Total del Día
                            </CardTitle>
                            <Banknote className="h-4 w-4 text-neutral-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                $4,850.50
                            </div>
                            <div className="mt-1 flex items-center text-xs text-green-600">
                                <ArrowUpRight className="mr-1 h-3 w-3" />
                                <span>+12.5% vs ayer</span>
                            </div>
                            {showSellers && (
                                <div className="mt-4 flex flex-col gap-2 border-t border-neutral-100 pt-3 dark:border-zinc-800">
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-500">
                                            Ana Castillo
                                        </span>
                                        <span className="font-semibold">
                                            $2,150.00
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-500">
                                            Pedro Rojas
                                        </span>
                                        <span className="font-semibold">
                                            $1,850.50
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-500">
                                            Luis Sánchez
                                        </span>
                                        <span className="font-semibold">
                                            $850.00
                                        </span>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Margen */}
                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                Margen Neto (Promedio)
                            </CardTitle>
                            <Activity className="h-4 w-4 text-neutral-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                24.8%
                            </div>
                            <div className="mt-1 flex items-center text-xs text-amber-600">
                                <ArrowDownRight className="mr-1 h-3 w-3" />
                                <span>-1.2% por descuentos altos</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        onClick={() => setShowCxC(!showCxC)}
                    >
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                CxC Vencidas
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-neutral-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                $12,400.00
                            </div>
                            <div className="mt-1 flex items-center text-xs text-red-600">
                                <ArrowUpRight className="mr-1 h-3 w-3" />
                                <span className="font-medium">
                                    +5% alerta de liquidez
                                </span>
                            </div>
                            {showCxC && (
                                <div className="mt-4 flex flex-col gap-2 border-t border-neutral-100 pt-3 dark:border-zinc-800">
                                    <div className="flex justify-between text-xs">
                                        <span className="font-medium text-red-500">
                                            Constructora Silva
                                        </span>
                                        <span className="font-semibold text-red-600">
                                            $5,420.00
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-500">
                                            Ing. Marco Villacreses
                                        </span>
                                        <span className="font-semibold">
                                            $2,100.00
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-500">
                                            Ferretería El Maestro
                                        </span>
                                        <span className="font-semibold">
                                            $1,850.00
                                        </span>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Link href="/inventory/products?filter=low_stock">
                        <Card className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    Ruptura Stock (Artículos A)
                                </CardTitle>
                                <Package className="h-4 w-4 text-neutral-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                    4.5%
                                </div>
                                <div className="mt-1 flex items-center text-xs text-red-600">
                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                    <span className="font-medium hover:underline">
                                        Ver productos
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                {/* 2. CAPA: LA COMPARATIVA (Gráficos) */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Ventas Sucursal (Bar) */}
                    <Card className="border border-neutral-200 p-6 shadow-none dark:border-zinc-800">
                        <h3 className="mb-6 text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Ventas por Sucursal
                        </h3>
                        <div className="h-[250px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={branchSalesData}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                        stroke="#e5e5e5"
                                    />
                                    <XAxis
                                        dataKey="name"
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 12, fill: '#737373' }}
                                        dy={10}
                                    />
                                    <YAxis
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 12, fill: '#737373' }}
                                        tickFormatter={(value) => `$${value}`}
                                    />
                                    <Tooltip
                                        cursor={{ fill: 'transparent' }}
                                        contentStyle={{
                                            borderRadius: '8px',
                                            border: '1px solid #e5e5e5',
                                        }}
                                    />
                                    <Bar
                                        dataKey="total"
                                        fill="#dc2626"
                                        radius={[4, 4, 0, 0]}
                                        barSize={40}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>

                    {/* Tendencia Semanal (Line) */}
                    <Card className="border border-neutral-200 p-6 shadow-none dark:border-zinc-800">
                        <h3 className="mb-6 text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Tendencia de Venta Semanal
                        </h3>
                        <div className="h-[250px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={saleTrendsData}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                        stroke="#e5e5e5"
                                    />
                                    <XAxis
                                        dataKey="name"
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 12, fill: '#737373' }}
                                        dy={10}
                                    />
                                    <YAxis
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 12, fill: '#737373' }}
                                        tickFormatter={(value) => `$${value}`}
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            borderRadius: '8px',
                                            border: '1px solid #e5e5e5',
                                        }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="actual"
                                        stroke="#dc2626"
                                        strokeWidth={3}
                                        dot={false}
                                        activeDot={{ r: 6 }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="anterior"
                                        stroke="#a3a3a3"
                                        strokeWidth={2}
                                        strokeDasharray="5 5"
                                        dot={false}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                </div>

                {/* 3. CAPA: GESTIÓN OPERATIVA Y ALERTAS */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Top 5 Productos */}
                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                Top 5 Más Vendidos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-4">
                                {topProducts.map((p) => (
                                    <div
                                        key={p.id}
                                        className="flex items-center justify-between"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-100 dark:bg-zinc-800">
                                                <Package className="h-4 w-4 text-neutral-500" />
                                            </div>
                                            <span className="text-sm text-neutral-700 dark:text-zinc-300">
                                                {p.name}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold tracking-tight">
                                                {p.qty}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Ticket Promedio */}
                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                Ticket Promedio
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-4">
                                {ticketsAverage.map((t) => (
                                    <div
                                        key={t.branch}
                                        className="flex items-center justify-between border-b border-dashed border-neutral-200 pb-2 last:border-0 dark:border-zinc-800"
                                    >
                                        <span className="text-sm text-neutral-600 dark:text-zinc-400">
                                            {t.branch}
                                        </span>
                                        <span className="text-sm font-semibold tracking-tight text-neutral-900 dark:text-zinc-50">
                                            {t.value}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Semáforo Alertas */}
                    <Card className="border border-red-200 bg-red-50/50 shadow-none dark:border-red-900/50 dark:bg-red-950/20">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold tracking-[-0.02em] text-red-700 dark:text-red-500">
                                <ShieldAlert className="h-5 w-5" />
                                Urgencias
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                <div className="flex cursor-pointer items-start gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-neutral-200 transition-colors hover:bg-red-50/50 dark:bg-zinc-900 dark:ring-zinc-800">
                                    <div className="relative mt-0.5 h-2 w-2">
                                        <div className="absolute h-2 w-2 animate-ping rounded-full bg-red-500 opacity-75"></div>
                                        <div className="relative h-2 w-2 rounded-full bg-red-500"></div>
                                    </div>
                                    <div className="text-sm text-neutral-700 dark:text-zinc-300">
                                        <span className="font-semibold text-neutral-900 dark:text-zinc-100">
                                            Discrepancia física:{' '}
                                        </span>
                                        Factura #001-204-000567. Los tornillos
                                        físicos no coinciden con los de la
                                        factura electrónica.
                                    </div>
                                </div>
                                <div className="flex cursor-pointer items-start gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-neutral-200 transition-colors hover:bg-amber-50/50 dark:bg-zinc-900 dark:ring-zinc-800">
                                    <div className="mt-0.5 h-2 w-2 rounded-full bg-amber-500"></div>
                                    <div className="text-sm text-neutral-700 dark:text-zinc-300">
                                        <span className="font-semibold text-neutral-900 dark:text-zinc-100">
                                            Cuentas:{' '}
                                        </span>
                                        Cemento Chimborazo está a punto de
                                        agotarse en sucursal principal.
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </TooltipProvider>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

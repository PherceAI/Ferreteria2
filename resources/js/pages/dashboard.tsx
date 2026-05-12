import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    Building2,
    CalendarDays,
    ClipboardCheck,
    Package,
    Repeat2,
    ShieldAlert,
} from 'lucide-react';
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
import type { Auth } from '@/types/auth';

type OperationalAlert = {
    id: string;
    type: 'critical' | 'high' | 'medium' | 'info';
    title: string;
    message: string;
    timestamp: string;
    href: string;
    actionText: string;
};

type DashboardOverview = {
    scopeLabel: string;
    summary: {
        inventoryValue: number;
        products: number;
        lowStock: number;
        zeroStock: number;
        pendingReceipts: number;
        discrepancies: number;
        activeTransfers: number;
    };
    inventoryByBranch: Array<{ name: string; total: number }>;
    invoiceTrend: Array<{ name: string; detected: number; received: number }>;
    topProducts: Array<{
        id: number;
        name: string;
        code: string;
        qty: string;
        value: number;
        unit: string | null;
    }>;
    receptionStatus: Array<{ status: string; label: string; count: number }>;
    urgentAlerts: OperationalAlert[];
};

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos dias';
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

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(value);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('es-EC').format(value);
}

function alertTone(type: OperationalAlert['type']): string {
    if (type === 'critical') {
        return 'border-red-200 bg-red-50/70 text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400';
    }

    if (type === 'high') {
        return 'border-amber-200 bg-amber-50/70 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-400';
    }

    return 'border-neutral-200 bg-white text-neutral-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300';
}

export default function Dashboard({
    overview,
}: {
    overview: DashboardOverview;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <TooltipProvider>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                        {greeting()}, {firstName}
                    </h1>
                    <div className="flex flex-wrap items-center gap-2 text-sm text-neutral-500 dark:text-zinc-400">
                        <Building2 className="h-3.5 w-3.5" />
                        <span>{overview.scopeLabel}</span>
                        <span className="text-neutral-300 dark:text-zinc-700">
                            /
                        </span>
                        <CalendarDays className="h-3.5 w-3.5" />
                        <span className="capitalize">{formatDate()}</span>
                    </div>
                </div>

                <Separator className="bg-neutral-200 dark:bg-zinc-800" />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                Valor de inventario
                            </CardTitle>
                            <Banknote className="h-4 w-4 text-neutral-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                                {formatCurrency(
                                    overview.summary.inventoryValue,
                                )}
                            </div>
                            <div className="mt-1 text-xs text-neutral-500">
                                {formatNumber(overview.summary.products)}{' '}
                                productos cargados
                            </div>
                        </CardContent>
                    </Card>

                    <Link href="/compras/recepcion">
                        <Card className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    Recepciones pendientes
                                </CardTitle>
                                <ClipboardCheck className="h-4 w-4 text-neutral-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                                    {overview.summary.pendingReceipts}
                                </div>
                                <div className="mt-1 text-xs text-neutral-500">
                                    {overview.summary.discrepancies} con novedad
                                </div>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/inventory/transfers">
                        <Card className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    Traspasos activos
                                </CardTitle>
                                <Repeat2 className="h-4 w-4 text-neutral-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                                    {overview.summary.activeTransfers}
                                </div>
                                <div className="mt-1 text-xs text-neutral-500">
                                    Solicitudes y envios en curso
                                </div>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/inventory/products">
                        <Card className="cursor-pointer border border-neutral-200 shadow-none transition-colors hover:bg-neutral-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-neutral-500 dark:text-zinc-400">
                                    Stock bajo
                                </CardTitle>
                                <Package className="h-4 w-4 text-neutral-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                                    {overview.summary.lowStock}
                                </div>
                                <div className="mt-1 flex items-center text-xs text-red-600">
                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                    {overview.summary.zeroStock} sin stock
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="border border-neutral-200 p-6 shadow-none dark:border-zinc-800">
                        <h3 className="mb-6 text-base font-medium text-neutral-900 dark:text-zinc-50">
                            Valor de inventario por sucursal
                        </h3>
                        <div className="h-[260px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={overview.inventoryByBranch}>
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
                                        formatter={(value) =>
                                            formatCurrency(Number(value))
                                        }
                                        cursor={{ fill: 'transparent' }}
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

                    <Card className="border border-neutral-200 p-6 shadow-none dark:border-zinc-800">
                        <h3 className="mb-6 text-base font-medium text-neutral-900 dark:text-zinc-50">
                            Facturas detectadas y recibidas
                        </h3>
                        <div className="h-[260px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={overview.invoiceTrend}>
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
                                    />
                                    <Tooltip />
                                    <Line
                                        type="monotone"
                                        dataKey="detected"
                                        name="Detectadas"
                                        stroke="#dc2626"
                                        strokeWidth={3}
                                        dot={false}
                                        activeDot={{ r: 5 }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="received"
                                        name="Recibidas"
                                        stroke="#525252"
                                        strokeWidth={2}
                                        strokeDasharray="5 5"
                                        dot={false}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="text-base font-medium text-neutral-900 dark:text-zinc-50">
                                Mayor valor en inventario
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-4">
                                {overview.topProducts.length > 0 ? (
                                    overview.topProducts.map((product) => (
                                        <Link
                                            key={product.id}
                                            href={`/inventory/products?search=${encodeURIComponent(product.code)}`}
                                            className="flex items-center justify-between gap-4 rounded-md transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/50"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-100 dark:bg-zinc-800">
                                                    <Package className="h-4 w-4 text-neutral-500" />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm text-neutral-700 dark:text-zinc-300">
                                                        {product.name}
                                                    </div>
                                                    <div className="text-xs text-neutral-500">
                                                        {product.qty}{' '}
                                                        {product.unit ?? ''}
                                                    </div>
                                                </div>
                                            </div>
                                            <span className="shrink-0 text-sm font-semibold">
                                                {formatCurrency(product.value)}
                                            </span>
                                        </Link>
                                    ))
                                ) : (
                                    <p className="text-sm text-neutral-500">
                                        Aun no hay productos valorizados.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="text-base font-medium text-neutral-900 dark:text-zinc-50">
                                Estado de recepcion
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-4">
                                {overview.receptionStatus.map((status) => (
                                    <div
                                        key={status.status}
                                        className="flex items-center justify-between border-b border-dashed border-neutral-200 pb-2 last:border-0 dark:border-zinc-800"
                                    >
                                        <span className="text-sm text-neutral-600 dark:text-zinc-400">
                                            {status.label}
                                        </span>
                                        <span className="text-sm font-semibold text-neutral-900 dark:text-zinc-50">
                                            {status.count}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-red-200 bg-red-50/50 shadow-none dark:border-red-900/50 dark:bg-red-950/20">
                        <CardHeader className="pt-6 pb-3">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold text-red-700 dark:text-red-500">
                                <ShieldAlert className="h-5 w-5" />
                                Urgencias
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                {overview.urgentAlerts.length > 0 ? (
                                    overview.urgentAlerts.map((alert) => (
                                        <Link
                                            key={alert.id}
                                            href={alert.href}
                                            className={`rounded-lg border p-3 transition-colors hover:bg-white dark:hover:bg-zinc-900 ${alertTone(alert.type)}`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="text-sm font-semibold">
                                                        {alert.title}
                                                    </div>
                                                    <p className="mt-1 text-sm leading-relaxed">
                                                        {alert.message}
                                                    </p>
                                                </div>
                                                <span className="shrink-0 text-xs opacity-70">
                                                    {alert.timestamp}
                                                </span>
                                            </div>
                                            <div className="mt-2 flex items-center gap-1 text-xs font-medium">
                                                {alert.actionText}
                                                <ArrowRight className="h-3 w-3" />
                                            </div>
                                        </Link>
                                    ))
                                ) : (
                                    <p className="rounded-lg border border-neutral-200 bg-white p-3 text-sm text-neutral-500 dark:border-zinc-800 dark:bg-zinc-900">
                                        No hay urgencias operativas en este
                                        momento.
                                    </p>
                                )}
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

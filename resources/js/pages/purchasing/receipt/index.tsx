import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileText,
    Inbox,
    PackageCheck,
    Search,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ReceiptItem = {
    id: number;
    code: string | null;
    description: string | null;
    expectedQty: number;
    receivedQty: number;
    conditionStatus: 'ok' | 'short' | 'over' | 'missing' | 'damaged';
    hasDiscrepancy: boolean;
    discrepancyNotes: string | null;
};

type InvoiceListItem = {
    id: number;
    supplier: string;
    invoiceNumber: string;
    total: number;
    status: string;
    statusLabel: string;
    emissionDate: string | null;
    detectedAt: string | null;
    ageLabel: string | null;
    itemsCount: number;
    discrepanciesCount: number;
};

type InvoiceDetail = InvoiceListItem & {
    accessKey: string;
    fromEmail: string | null;
    confirmation: {
        id: number;
        status: string;
        notes: string | null;
        confirmedAt: string | null;
        items: ReceiptItem[];
    } | null;
    invoiceItems: Array<{
        id: number;
        code: string | null;
        description: string;
        quantity: number;
        unitPrice: number;
        subtotal: number;
    }>;
    events: Array<{
        id: number;
        type: string;
        title: string;
        body: string | null;
        user: string | null;
        createdAt: string | null;
    }>;
};

type Props = {
    filters: {
        status: string;
        search: string;
    };
    stats: {
        detectedToday: number;
        awaitingPhysical: number;
        withDiscrepancy: number;
        receivedToday: number;
        averageWaitingHours: number;
    };
    invoices: InvoiceListItem[];
    selectedInvoice: InvoiceDetail | null;
    statusOptions: Array<{ value: string; label: string }>;
    viewMode: 'admin' | 'warehouse';
};

const currencyFormatter = new Intl.NumberFormat('es-EC', {
    style: 'currency',
    currency: 'USD',
});

const quantityFormatter = new Intl.NumberFormat('es-EC', {
    maximumFractionDigits: 2,
});

const statusTone: Record<string, string> = {
    pending: 'bg-neutral-400',
    awaiting_physical: 'bg-amber-500',
    detected: 'bg-blue-500',
    receiving: 'bg-blue-500',
    received_ok: 'bg-green-500',
    received_discrepancy: 'bg-red-500',
    closed: 'bg-neutral-400',
};

const conditionLabels: Record<ReceiptItem['conditionStatus'], string> = {
    ok: 'Completo',
    short: 'Faltante',
    over: 'Sobrante',
    missing: 'No llego',
    damaged: 'Danado',
};

export default function PurchasingReceiptIndex({
    filters,
    stats,
    invoices,
    selectedInvoice,
    statusOptions,
    viewMode,
}: Props) {
    const selectedItems = useMemo(
        () => selectedInvoice?.confirmation?.items ?? [],
        [selectedInvoice?.confirmation?.items],
    );
    const canReceive =
        selectedInvoice?.confirmation !== null &&
        !['received_ok', 'received_discrepancy', 'closed'].includes(
            selectedInvoice?.status ?? '',
        );
    const hasDiscrepancy = selectedInvoice?.status === 'received_discrepancy';

    const form = useForm({
        notes: selectedInvoice?.confirmation?.notes ?? '',
        items: selectedItems.map((item) => ({
            id: item.id,
            received_qty:
                item.receivedQty > 0
                    ? String(item.receivedQty)
                    : String(item.expectedQty),
            condition_status: item.conditionStatus,
            discrepancy_notes: item.discrepancyNotes ?? '',
        })),
    });

    const closeForm = useForm({
        action: hasDiscrepancy ? 'supplier_contacted' : 'closed',
        notes: '',
    });

    useEffect(() => {
        form.setData({
            notes: selectedInvoice?.confirmation?.notes ?? '',
            items: selectedItems.map((item) => ({
                id: item.id,
                received_qty:
                    item.receivedQty > 0
                        ? String(item.receivedQty)
                        : String(item.expectedQty),
                condition_status: item.conditionStatus,
                discrepancy_notes: item.discrepancyNotes ?? '',
            })),
        });
        closeForm.setData({
            action:
                selectedInvoice?.status === 'received_discrepancy'
                    ? 'supplier_contacted'
                    : 'closed',
            notes: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedInvoice?.id]);

    const selectedItemRows = useMemo(
        () =>
            selectedItems.map((item) => {
                const formItem = form.data.items.find(
                    (row) => row.id === item.id,
                );

                return { item, formItem };
            }),
        [form.data.items, selectedItems],
    );

    const updateFilters = (next: Partial<Props['filters']>) => {
        router.get(
            '/compras/recepcion',
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    };

    const selectInvoice = (invoiceId: number) => {
        router.get(
            '/compras/recepcion',
            { ...filters, invoice: invoiceId },
            { preserveState: true, replace: true },
        );
    };

    const clearSelectedInvoice = () => {
        router.get(
            '/compras/recepcion',
            { ...filters },
            { preserveState: true, replace: true },
        );
    };

    const startReception = () => {
        if (!selectedInvoice?.confirmation) {
            return;
        }

        router.post(
            `/compras/recepcion/${selectedInvoice.confirmation.id}/iniciar`,
            {},
            { preserveScroll: true },
        );
    };

    const submitReception = () => {
        if (!selectedInvoice?.confirmation) {
            return;
        }

        form.post(
            `/compras/recepcion/${selectedInvoice.confirmation.id}/confirmar`,
            {
                preserveScroll: true,
            },
        );
    };

    const submitClose = () => {
        if (!selectedInvoice) {
            return;
        }

        closeForm.post(
            `/compras/recepcion/facturas/${selectedInvoice.id}/cerrar`,
            {
                preserveScroll: true,
            },
        );
    };

    if (viewMode === 'warehouse') {
        return (
            <WarehouseReceiptView
                canReceive={canReceive}
                form={form}
                invoices={invoices}
                rows={selectedItemRows}
                selectedInvoice={selectedInvoice}
                stats={stats}
                onClearSelection={clearSelectedInvoice}
                onSelectInvoice={selectInvoice}
                onStart={startReception}
                onSubmit={submitReception}
            />
        );
    }

    return (
        <>
            <Head title="Recepcion fisica de facturas" />

            <div className="flex flex-col gap-6 p-6 pb-32 font-sans">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Recepcion fisica de facturas
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-zinc-400">
                            Factura detectada, espera de bodega, novedades y
                            trazabilidad en un solo expediente.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() =>
                            updateFilters({ status: 'received_discrepancy' })
                        }
                    >
                        <ShieldAlert className="h-4 w-4" />
                        Ver novedades
                    </Button>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        icon={Inbox}
                        label="Detectadas hoy"
                        value={stats.detectedToday}
                        detail="Desde Gmail/XML"
                        tone="bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/20 dark:text-blue-300 dark:ring-blue-900/50"
                    />
                    <MetricCard
                        icon={Clock}
                        label="Esperando bodega"
                        value={stats.awaitingPhysical}
                        detail="Pendientes de llegada fisica"
                        tone="bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/20 dark:text-amber-300 dark:ring-amber-900/50"
                    />
                    <MetricCard
                        icon={AlertTriangle}
                        label="Con novedades"
                        value={stats.withDiscrepancy}
                        detail="Requieren revision"
                        tone="bg-red-50 text-red-700 ring-red-100 dark:bg-red-950/20 dark:text-red-300 dark:ring-red-900/50"
                    />
                    <MetricCard
                        icon={PackageCheck}
                        label="Espera promedio"
                        value={`${stats.averageWaitingHours}h`}
                        detail="Facturas abiertas"
                        tone="bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-300 dark:ring-emerald-900/50"
                    />
                </section>

                <section className="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="gap-4">
                            <div>
                                <CardTitle className="text-base font-medium tracking-[-0.02em]">
                                    Facturas recibidas
                                </CardTitle>
                                <p className="mt-1 text-sm text-neutral-500">
                                    Bandeja de eventos pendientes y cerrados.
                                </p>
                            </div>
                            <div className="grid gap-3">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                                    <Input
                                        className="pl-9"
                                        placeholder="Buscar proveedor o factura"
                                        defaultValue={filters.search}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                updateFilters({
                                                    search: event.currentTarget
                                                        .value,
                                                });
                                            }
                                        }}
                                    />
                                </div>
                                <Select
                                    value={filters.status}
                                    onValueChange={(value) =>
                                        updateFilters({ status: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardHeader>
                        <CardContent className="max-h-[min(560px,calc(100vh-24rem))] space-y-2 overflow-y-auto pr-3">
                            {invoices.length === 0 ? (
                                <EmptyState />
                            ) : (
                                invoices.map((invoice) => (
                                    <InvoiceButton
                                        key={invoice.id}
                                        invoice={invoice}
                                        selected={
                                            invoice.id === selectedInvoice?.id
                                        }
                                        onClick={() =>
                                            selectInvoice(invoice.id)
                                        }
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {selectedInvoice ? (
                        <div className="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_380px]">
                            <div className="space-y-4">
                                <InvoiceHeader invoice={selectedInvoice} />
                                <AdminReceiptStatusPanel
                                    invoice={selectedInvoice}
                                />
                            </div>

                            <aside className="space-y-4">
                                <Timeline events={selectedInvoice.events} />
                                <ReviewPanel
                                    invoice={selectedInvoice}
                                    form={closeForm}
                                    onSubmit={submitClose}
                                />
                            </aside>
                        </div>
                    ) : (
                        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                            <CardContent className="p-8">
                                <EmptyState />
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
    detail,
    tone = 'bg-white text-neutral-900 ring-neutral-200 dark:bg-zinc-900 dark:text-zinc-50 dark:ring-zinc-800',
}: {
    icon: typeof Inbox;
    label: string;
    value: string | number;
    detail: string;
    tone?: string;
}) {
    return (
        <Card className={`border-0 shadow-none ring-1 ${tone}`}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium opacity-80">
                    {label}
                </CardTitle>
                <Icon className="h-4 w-4 opacity-70" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tracking-[-0.02em]">
                    {value}
                </div>
                <span className="text-xs opacity-75">{detail}</span>
            </CardContent>
        </Card>
    );
}

function WarehouseReceiptView({
    canReceive,
    form,
    invoices,
    rows,
    selectedInvoice,
    stats,
    onClearSelection,
    onSelectInvoice,
    onStart,
    onSubmit,
}: {
    canReceive: boolean;
    form: ReturnType<
        typeof useForm<{
            notes: string;
            items: Array<{
                id: number;
                received_qty: string;
                condition_status: ReceiptItem['conditionStatus'];
                discrepancy_notes: string;
            }>;
        }>
    >;
    invoices: InvoiceListItem[];
    rows: Array<{
        item: ReceiptItem;
        formItem?: (typeof form.data.items)[number];
    }>;
    selectedInvoice: InvoiceDetail | null;
    stats: Props['stats'];
    onClearSelection: () => void;
    onSelectInvoice: (invoiceId: number) => void;
    onStart: () => void;
    onSubmit: () => void;
}) {
    return (
        <>
            <Head title="Recepciones pendientes" />

            <div className="flex w-full max-w-full flex-col gap-3 overflow-x-hidden p-3 pb-28 font-sans sm:gap-4 sm:p-6">
                <header className="rounded-xl border border-emerald-100 bg-emerald-50 p-4 sm:p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                    <p className="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        Bodega
                    </p>
                    <h1 className="mt-1 text-xl font-semibold tracking-[-0.02em] text-neutral-900 sm:text-2xl dark:text-zinc-50">
                        Recepciones pendientes
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-neutral-600 dark:text-zinc-400">
                        Confirma lo que llego fisicamente contra la factura
                        detectada.
                        <span className="hidden sm:inline">
                            {' '}
                            Solo marca diferencias cuando algo no cuadre.
                        </span>
                    </p>
                </header>

                <WarehouseQuickStats
                    pending={invoices.length}
                    receivedToday={stats.receivedToday}
                    discrepancies={stats.withDiscrepancy}
                />

                <section className="grid min-w-0 gap-3 xl:grid-cols-[320px_minmax(0,1fr)] xl:gap-4">
                    <Card
                        className={`min-w-0 overflow-hidden border-neutral-200 shadow-none xl:sticky xl:top-4 xl:order-1 xl:self-start dark:border-zinc-800 ${
                            selectedInvoice ? 'order-2' : 'order-1'
                        }`}
                    >
                        <CardHeader className="p-4 sm:p-6">
                            <CardTitle className="text-base font-medium tracking-[-0.02em]">
                                Pendientes de tu sucursal
                            </CardTitle>
                            <p className="text-sm text-neutral-500">
                                {invoices.length} facturas esperando revision.
                            </p>
                        </CardHeader>
                        <CardContent className="max-h-[44vh] space-y-2 overflow-y-auto p-4 pt-0 sm:max-h-[520px] sm:p-6 sm:pt-0">
                            {invoices.length === 0 ? (
                                <EmptyState />
                            ) : (
                                invoices.map((invoice) => (
                                    <WarehouseInvoiceButton
                                        key={invoice.id}
                                        invoice={invoice}
                                        selected={
                                            invoice.id === selectedInvoice?.id
                                        }
                                        onClick={() =>
                                            onSelectInvoice(invoice.id)
                                        }
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {selectedInvoice ? (
                        <div className="order-1 min-w-0 space-y-3 sm:space-y-4 xl:order-2">
                            <WarehouseInvoiceHeader
                                invoice={selectedInvoice}
                                onClearSelection={onClearSelection}
                            />
                            <ReceptionPanel
                                canReceive={canReceive}
                                form={form}
                                invoice={selectedInvoice}
                                rows={rows}
                                onStart={onStart}
                                onSubmit={onSubmit}
                            />
                        </div>
                    ) : (
                        <Card className="min-w-0 border-neutral-200 shadow-none dark:border-zinc-800">
                            <CardContent className="p-6">
                                <EmptyState />
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </>
    );
}

function WarehouseInvoiceButton({
    invoice,
    selected,
    onClick,
}: {
    invoice: InvoiceListItem;
    selected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`w-full min-w-0 touch-manipulation rounded-lg border p-3 text-left transition-colors sm:p-4 ${
                selected
                    ? 'border-neutral-900 bg-neutral-50 dark:border-zinc-50 dark:bg-zinc-800/50'
                    : 'border-neutral-200 bg-white hover:bg-neutral-100 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800/50'
            }`}
        >
            <div className="flex min-w-0 items-start justify-between gap-3">
                <div className="min-w-0 flex-1 overflow-hidden">
                    <p
                        className="overflow-hidden text-sm leading-5 font-medium break-words text-neutral-900 dark:text-zinc-50"
                        style={{
                            display: '-webkit-box',
                            WebkitBoxOrient: 'vertical',
                            WebkitLineClamp: 2,
                        }}
                    >
                        {invoice.supplier}
                    </p>
                    <p className="mt-1 text-xs text-neutral-500">
                        {invoice.invoiceNumber}
                    </p>
                </div>
                <span
                    className={`mt-1 h-2 w-2 shrink-0 rounded-full ${statusTone[invoice.status] ?? 'bg-neutral-400'}`}
                />
            </div>
            <div className="mt-3 flex min-w-0 items-center justify-between gap-3 text-xs text-neutral-500">
                <span>{invoice.statusLabel}</span>
                <span>{invoice.itemsCount} items</span>
            </div>
        </button>
    );
}

function WarehouseQuickStats({
    pending,
    receivedToday,
    discrepancies,
}: {
    pending: number;
    receivedToday: number;
    discrepancies: number;
}) {
    return (
        <section className="grid grid-cols-3 gap-2 sm:gap-3">
            <WarehouseStatCard
                label="Pendientes"
                value={pending}
                tone="bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/20 dark:text-amber-300 dark:ring-amber-900/50"
            />
            <WarehouseStatCard
                label="Listas hoy"
                value={receivedToday}
                tone="bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-300 dark:ring-emerald-900/50"
            />
            <WarehouseStatCard
                label="Novedades"
                value={discrepancies}
                tone="bg-red-50 text-red-700 ring-red-100 dark:bg-red-950/20 dark:text-red-300 dark:ring-red-900/50"
            />
        </section>
    );
}

function WarehouseStatCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: string;
}) {
    return (
        <div className={`rounded-xl p-3 ring-1 ${tone}`}>
            <p className="text-[11px] font-medium sm:text-xs">{label}</p>
            <p className="mt-1 text-2xl font-semibold tracking-[-0.02em]">
                {value}
            </p>
        </div>
    );
}

function WarehouseInvoiceHeader({
    invoice,
    onClearSelection,
}: {
    invoice: InvoiceDetail;
    onClearSelection: () => void;
}) {
    return (
        <Card className="min-w-0 overflow-hidden border-neutral-200 shadow-none dark:border-zinc-800">
            <CardContent className="p-4 sm:p-5">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onClearSelection}
                    className="mb-3 h-9 sm:hidden"
                >
                    Volver a pendientes
                </Button>
                <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <h2 className="text-base leading-6 font-semibold break-words text-neutral-900 sm:text-lg dark:text-zinc-50">
                        {invoice.supplier}
                    </h2>
                    <StatusDot
                        status={invoice.status}
                        label={invoice.statusLabel}
                    />
                </div>
                <p className="mt-2 text-sm leading-6 text-neutral-500">
                    Factura {invoice.invoiceNumber} · {invoice.itemsCount} items
                    por revisar
                </p>
            </CardContent>
        </Card>
    );
}

function InvoiceButton({
    invoice,
    selected,
    onClick,
}: {
    invoice: InvoiceListItem;
    selected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`w-full rounded-lg border p-4 text-left transition-colors ${
                selected
                    ? 'border-neutral-900 bg-neutral-50 dark:border-zinc-50 dark:bg-zinc-800/50'
                    : 'border-neutral-200 bg-white hover:bg-neutral-100 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800/50'
            }`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                        {invoice.supplier}
                    </p>
                    <p className="mt-1 text-xs text-neutral-500">
                        {invoice.invoiceNumber} · {invoice.detectedAt}
                    </p>
                </div>
                <span
                    className={`mt-1 h-2 w-2 shrink-0 rounded-full ${statusTone[invoice.status] ?? 'bg-neutral-400'}`}
                />
            </div>
            <div className="mt-3 flex items-center justify-between gap-3 text-xs text-neutral-500">
                <span>{invoice.statusLabel}</span>
                <span>{currencyFormatter.format(invoice.total)}</span>
            </div>
            {invoice.discrepanciesCount > 0 && (
                <div className="mt-3 text-xs font-medium text-red-600">
                    {invoice.discrepanciesCount} novedades pendientes
                </div>
            )}
        </button>
    );
}

function InvoiceHeader({ invoice }: { invoice: InvoiceDetail }) {
    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardContent className="grid gap-4 p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                <div>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            {invoice.supplier}
                        </h2>
                        <StatusDot
                            status={invoice.status}
                            label={invoice.statusLabel}
                        />
                    </div>
                    <p className="mt-2 text-sm text-neutral-500">
                        Factura {invoice.invoiceNumber} · Emision{' '}
                        {invoice.emissionDate ?? 'sin fecha'} · Detectada{' '}
                        {invoice.detectedAt ?? 'sin dato'}
                    </p>
                </div>
                <div className="rounded-lg border border-neutral-200 p-4 text-right dark:border-zinc-800">
                    <p className="text-xs text-neutral-500">Total factura</p>
                    <p className="text-2xl font-semibold tracking-[-0.02em]">
                        {currencyFormatter.format(invoice.total)}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

function ReceptionPanel({
    canReceive,
    invoice,
    rows,
    form,
    onStart,
    onSubmit,
}: {
    canReceive: boolean;
    invoice: InvoiceDetail;
    rows: Array<{
        item: ReceiptItem;
        formItem?: (typeof form.data.items)[number];
    }>;
    form: ReturnType<
        typeof useForm<{
            notes: string;
            items: Array<{
                id: number;
                received_qty: string;
                condition_status: ReceiptItem['conditionStatus'];
                discrepancy_notes: string;
            }>;
        }>
    >;
    onStart: () => void;
    onSubmit: () => void;
}) {
    const updateItem = (
        index: number,
        patch: Partial<(typeof form.data.items)[number]>,
    ) => {
        form.setData(
            'items',
            form.data.items.map((item, currentIndex) =>
                currentIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    return (
        <Card className="min-w-0 overflow-hidden border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader className="flex flex-col gap-3 p-4 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <CardTitle className="text-base font-medium tracking-[-0.02em]">
                        Recepcion de bodega
                    </CardTitle>
                    <p className="mt-1 text-sm text-neutral-500">
                        Confirmar contra lo que ya llego en el XML, sin digitar
                        la factura de nuevo.
                    </p>
                </div>
                {invoice.confirmation?.status === 'pending' && (
                    <Button
                        variant="outline"
                        onClick={onStart}
                        className="h-11 w-full sm:w-auto"
                    >
                        <PackageCheck className="h-4 w-4" />
                        Iniciar recepcion
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-4 p-4 pt-0 sm:p-6 sm:pt-0">
                <div className="grid min-w-0 gap-3">
                    {rows.map(({ item, formItem }, index) => (
                        <div
                            key={item.id}
                            className="min-w-0 rounded-xl border border-neutral-200 p-3 sm:p-4 dark:border-zinc-800"
                        >
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="min-w-0 overflow-hidden">
                                    <p className="text-sm leading-5 font-medium break-words text-neutral-900 sm:text-base dark:text-zinc-50">
                                        {item.description}
                                    </p>
                                    <p className="mt-1 text-sm leading-5 text-neutral-500">
                                        Codigo {item.code ?? 'sin codigo'} ·
                                        Esperado{' '}
                                        {quantityFormatter.format(
                                            item.expectedQty,
                                        )}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!canReceive}
                                    className="h-11 w-full shrink-0 sm:w-auto"
                                    onClick={() =>
                                        updateItem(index, {
                                            received_qty: String(
                                                item.expectedQty,
                                            ),
                                            condition_status: 'ok',
                                            discrepancy_notes: '',
                                        })
                                    }
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    Completo
                                </Button>
                            </div>

                            <div className="mt-4 grid min-w-0 gap-3 sm:grid-cols-2 md:grid-cols-[140px_180px_minmax(0,1fr)]">
                                <div className="space-y-2">
                                    <Label className="text-xs text-neutral-500">
                                        Cantidad recibida
                                    </Label>
                                    <Input
                                        className="h-11"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        disabled={!canReceive}
                                        value={formItem?.received_qty ?? ''}
                                        onChange={(event) =>
                                            updateItem(index, {
                                                received_qty:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-xs text-neutral-500">
                                        Estado
                                    </Label>
                                    <Select
                                        disabled={!canReceive}
                                        value={
                                            formItem?.condition_status ?? 'ok'
                                        }
                                        onValueChange={(value) =>
                                            updateItem(index, {
                                                condition_status:
                                                    value as ReceiptItem['conditionStatus'],
                                            })
                                        }
                                    >
                                        <SelectTrigger className="h-11">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(
                                                conditionLabels,
                                            ).map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2 sm:col-span-2 md:col-span-1">
                                    <Label className="text-xs text-neutral-500">
                                        Nota de novedad
                                    </Label>
                                    <Input
                                        className="h-11"
                                        disabled={!canReceive}
                                        placeholder="Ej. faltaron 2 cajas"
                                        value={
                                            formItem?.discrepancy_notes ?? ''
                                        }
                                        onChange={(event) =>
                                            updateItem(index, {
                                                discrepancy_notes:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="space-y-2">
                    <Label className="text-xs text-neutral-500">
                        Nota general de recepcion
                    </Label>
                    <textarea
                        className="min-h-24 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm transition-colors outline-none focus:border-neutral-400 dark:border-zinc-800 dark:bg-zinc-950"
                        disabled={!canReceive}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                </div>

                <div className="sticky bottom-3 z-10 flex flex-col gap-3 rounded-xl bg-white/95 pt-2 backdrop-blur sm:static sm:flex-row sm:justify-end sm:bg-transparent sm:pt-0 sm:backdrop-blur-none dark:bg-zinc-950/95 dark:sm:bg-transparent">
                    <Button
                        className="h-12 w-full bg-neutral-900 text-white hover:bg-neutral-800 sm:w-auto dark:bg-zinc-50 dark:text-zinc-950"
                        disabled={!canReceive || form.processing}
                        onClick={onSubmit}
                    >
                        <PackageCheck className="h-4 w-4" />
                        Confirmar recepcion fisica
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function AdminReceiptStatusPanel({ invoice }: { invoice: InvoiceDetail }) {
    const items = invoice.confirmation?.items ?? [];
    const receptionIsPending = ['pending', 'receiving'].includes(
        invoice.confirmation?.status ?? '',
    );

    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader>
                <CardTitle className="text-base font-medium tracking-[-0.02em]">
                    Estado de recepcion fisica
                </CardTitle>
                <p className="mt-1 text-sm text-neutral-500">
                    Bodega confirma la mercaderia desde su vista movil. Compras
                    y contabilidad solo revisan el resultado y atienden
                    novedades.
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-lg border border-neutral-200 p-4 text-sm text-neutral-500 dark:border-zinc-800">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span>Estado actual</span>
                        <StatusDot
                            status={invoice.status}
                            label={invoice.statusLabel}
                        />
                    </div>
                    {invoice.confirmation?.confirmedAt && (
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>Confirmado por bodega</span>
                            <span className="font-medium text-neutral-900 dark:text-zinc-50">
                                {invoice.confirmation.confirmedAt}
                            </span>
                        </div>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-neutral-200 dark:border-zinc-800">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                            <tr>
                                <th className="px-4 py-3 font-medium">Item</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Esperado
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Recibido
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Estado
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800">
                            {items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="hover:bg-neutral-50 dark:hover:bg-zinc-800/40"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                            {item.description}
                                        </p>
                                        <p className="mt-1 text-xs text-neutral-500">
                                            Codigo {item.code ?? 'sin codigo'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {quantityFormatter.format(
                                            item.expectedQty,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {item.receivedQty > 0
                                            ? quantityFormatter.format(
                                                  item.receivedQty,
                                              )
                                            : 'Pendiente'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={
                                                item.hasDiscrepancy
                                                    ? 'text-red-600'
                                                    : 'text-neutral-500'
                                            }
                                        >
                                            {receptionIsPending
                                                ? 'Pendiente'
                                                : conditionLabels[
                                                      item.conditionStatus
                                                  ]}
                                        </span>
                                        {item.discrepancyNotes && (
                                            <p className="mt-1 text-xs text-neutral-500">
                                                {item.discrepancyNotes}
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

function Timeline({ events }: { events: InvoiceDetail['events'] }) {
    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader>
                <CardTitle className="text-base font-medium tracking-[-0.02em]">
                    Trazabilidad
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {events.length === 0 ? (
                    <p className="text-sm text-neutral-500">Sin eventos aun.</p>
                ) : (
                    events.map((event) => (
                        <div key={event.id} className="flex gap-3">
                            <span className="mt-1.5 h-2 w-2 rounded-full bg-neutral-900 dark:bg-zinc-50" />
                            <div>
                                <p className="text-sm font-medium tracking-[-0.02em]">
                                    {event.title}
                                </p>
                                <p className="mt-1 text-xs text-neutral-500">
                                    {event.createdAt}
                                    {event.user ? ` · ${event.user}` : ''}
                                </p>
                                {event.body && (
                                    <p className="mt-2 text-sm text-neutral-500">
                                        {event.body}
                                    </p>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function ReviewPanel({
    invoice,
    form,
    onSubmit,
}: {
    invoice: InvoiceDetail;
    form: ReturnType<typeof useForm<{ action: string; notes: string }>>;
    onSubmit: () => void;
}) {
    const canClose = invoice.status !== 'closed';

    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader>
                <CardTitle className="text-base font-medium tracking-[-0.02em]">
                    Seguimiento compras
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-lg border border-neutral-200 p-4 text-sm text-neutral-500 dark:border-zinc-800">
                    Compras atiende solo novedades. Si todo esta conforme, el
                    expediente puede cerrarse como validado.
                </div>
                <div className="space-y-2">
                    <Label className="text-xs text-neutral-500">Accion</Label>
                    <Select
                        value={form.data.action}
                        disabled={!canClose}
                        onValueChange={(value) => form.setData('action', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="supplier_contacted">
                                Proveedor contactado
                            </SelectItem>
                            <SelectItem value="credit_note_requested">
                                Nota de credito solicitada
                            </SelectItem>
                            <SelectItem value="closed">
                                Cerrar expediente
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label className="text-xs text-neutral-500">Nota</Label>
                    <textarea
                        className="min-h-24 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm transition-colors outline-none focus:border-neutral-400 dark:border-zinc-800 dark:bg-zinc-950"
                        disabled={!canClose}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                </div>
                <Button
                    className="w-full bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-zinc-50 dark:text-zinc-950"
                    disabled={!canClose || form.processing}
                    onClick={onSubmit}
                >
                    <FileText className="h-4 w-4" />
                    Guardar seguimiento
                </Button>
            </CardContent>
        </Card>
    );
}

function StatusDot({ status, label }: { status: string; label: string }) {
    return (
        <Badge
            variant="outline"
            className="gap-2 rounded-lg border-neutral-200 bg-white text-neutral-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400"
        >
            <span
                className={`h-2 w-2 rounded-full ${statusTone[status] ?? 'bg-neutral-400'}`}
            />
            {label}
        </Badge>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-neutral-200 p-6 text-center dark:border-zinc-800">
            <FileText className="mx-auto h-5 w-5 text-neutral-400" />
            <p className="mt-3 text-sm font-medium tracking-[-0.02em]">
                No hay facturas para mostrar
            </p>
            <p className="mt-1 text-sm text-neutral-500">
                Cuando Gmail procese un XML, aparecera aqui como expediente.
            </p>
        </div>
    );
}

PurchasingReceiptIndex.layout = {
    breadcrumbs: [
        { title: 'Compras', href: '/compras' },
        { title: 'Recepcion fisica', href: '/compras/recepcion' },
    ],
};

import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileCheck2,
    PackageCheck,
    Plus,
    Search,
    Send,
    Trash2,
    Truck,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BranchOption = {
    id: number;
    name: string;
    displayName: string;
    code: string;
    warehouseName: string | null;
    warehouseCode: string | null;
    city: string | null;
};

type ProductOption = {
    id: number;
    code: string;
    name: string;
    unit: string | null;
    current_stock: number;
    isRecommended: boolean;
    branch: BranchOption;
};

type TransferItem = {
    id: number;
    productCode: string;
    productName: string;
    unit: string | null;
    sourceStockSnapshot: number | null;
    sourceStockVerified: boolean;
    requestedQty: number;
    preparedQty: number | null;
    receivedQty: number | null;
    hasDiscrepancy: boolean;
    preparationNotes: string | null;
    receptionNotes: string | null;
};

type TransferEvent = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    user: string | null;
    createdAt: string | null;
};

type Transfer = {
    id: number;
    status: string;
    statusLabel: string;
    sourceBranch: Pick<
        BranchOption,
        | 'id'
        | 'name'
        | 'displayName'
        | 'code'
        | 'warehouseName'
        | 'warehouseCode'
    >;
    destinationBranch: Pick<
        BranchOption,
        | 'id'
        | 'name'
        | 'displayName'
        | 'code'
        | 'warehouseName'
        | 'warehouseCode'
    >;
    requestedBy: string | null;
    requestNotes: string | null;
    createdAt: string | null;
    updatedAt: string | null;
    items: TransferItem[];
    events: TransferEvent[];
    permissions: {
        canPrepare: boolean;
        canReceive: boolean;
        canCompleteTini: boolean;
        canCancel: boolean;
    };
};

type PaginatedTransfers = {
    data: Transfer[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Props = {
    branches: BranchOption[];
    filters: {
        tab: string;
        search: string;
        source_branch_id: number | null;
        product_search: string;
    };
    permissions: {
        canCreate: boolean;
        activeBranchId: number | null;
    };
    productOptions: ProductOption[];
    stats: {
        open: number;
        preparing: number;
        inTransit: number;
        withDiscrepancy: number;
    };
    statusOptions: Array<{ value: string; label: string }>;
    transfers: PaginatedTransfers;
};

type DraftItem = {
    inventory_product_id: number | null;
    source_branch_id: number | null;
    source_branch_display_name: string | null;
    source_warehouse_code: string | null;
    source_stock: number | null;
    source_stock_verified: boolean;
    product_code: string;
    product_name: string;
    unit: string;
    requested_qty: string;
};

const quantityFormatter = new Intl.NumberFormat('es-EC', {
    maximumFractionDigits: 3,
});

const newIdempotencyKey = () => {
    if (
        typeof crypto !== 'undefined' &&
        'randomUUID' in crypto &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const tabs = [
    { value: 'requests', label: 'Solicitudes' },
    { value: 'preparation', label: 'Preparacion' },
    { value: 'transit', label: 'En transito' },
    { value: 'reception', label: 'Recepcion' },
    { value: 'closed', label: 'Cerrados' },
];

const statusTone: Record<string, string> = {
    requested:
        'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/20 dark:text-blue-300 dark:ring-blue-900/50',
    preparing:
        'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/20 dark:text-amber-300 dark:ring-amber-900/50',
    ready_to_ship:
        'bg-violet-50 text-violet-700 ring-violet-100 dark:bg-violet-950/20 dark:text-violet-300 dark:ring-violet-900/50',
    in_transit:
        'bg-orange-50 text-orange-700 ring-orange-100 dark:bg-orange-950/20 dark:text-orange-300 dark:ring-orange-900/50',
    received:
        'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-300 dark:ring-emerald-900/50',
    received_discrepancy:
        'bg-red-50 text-red-700 ring-red-100 dark:bg-red-950/20 dark:text-red-300 dark:ring-red-900/50',
    tini_completed:
        'bg-neutral-50 text-neutral-700 ring-neutral-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800',
    cancelled:
        'bg-neutral-50 text-neutral-500 ring-neutral-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-800',
};

const formatBranch = (
    branch?: Pick<
        BranchOption,
        'name' | 'displayName' | 'code' | 'warehouseCode'
    > | null,
) => {
    if (!branch) {
        return 'Sucursal no definida';
    }

    const code = branch.warehouseCode
        ? `Bodega ${branch.warehouseCode}`
        : branch.code;

    return `${branch.displayName ?? branch.name} (${code})`;
};

export default function TransfersIndex({
    branches,
    filters,
    permissions,
    productOptions,
    stats,
    transfers,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [workflowTransfer, setWorkflowTransfer] = useState<Transfer | null>(
        null,
    );
    const [workflowMode, setWorkflowMode] = useState<
        'prepare' | 'receive' | null
    >(null);
    const [productSearch, setProductSearch] = useState(filters.product_search);

    const createForm = useForm<{
        idempotency_key: string;
        notes: string;
        items: DraftItem[];
    }>({
        idempotency_key: newIdempotencyKey(),
        notes: '',
        items: [],
    });

    const workflowForm = useForm<{
        notes: string;
        items: Array<{
            id: number;
            prepared_qty?: string;
            received_qty?: string;
            preparation_notes?: string;
            reception_notes?: string;
        }>;
    }>({
        notes: '',
        items: [],
    });

    const activeBranch = branches.find(
        (branch) => branch.id === permissions.activeBranchId,
    );
    const hasProductSearch = filters.product_search.trim() !== '';
    const validDraftItems = createForm.data.items.filter(
        (item) =>
            item.source_branch_id !== null &&
            item.product_code.trim() !== '' &&
            item.product_name.trim() !== '' &&
            Number(item.requested_qty) > 0,
    );
    const selectedSourceCount = new Set(
        validDraftItems.map((item) => item.source_branch_id),
    ).size;

    const selectedTransferRows = useMemo(
        () =>
            workflowTransfer?.items.map((item) => {
                const formItem = workflowForm.data.items.find(
                    (row) => row.id === item.id,
                );

                return { item, formItem };
            }) ?? [],
        [workflowForm.data.items, workflowTransfer?.items],
    );

    const updateFilters = useCallback(
        (next: Partial<Props['filters']>) => {
            router.get(
                '/inventory/transfers',
                { ...filters, ...next },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const searchProducts = useCallback(
        (search = productSearch) => {
            const nextSearch = search.trim();

            if (nextSearch.length < 2) {
                return;
            }

            updateFilters({
                source_branch_id: null,
                product_search: nextSearch,
            });
        },
        [productSearch, updateFilters],
    );

    useEffect(() => {
        const search = productSearch.trim();

        if (!createOpen || search.length < 2) {
            return;
        }

        if (filters.product_search === search) {
            return;
        }

        const timeout = window.setTimeout(() => {
            searchProducts(search);
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [createOpen, filters.product_search, productSearch, searchProducts]);

    const addProduct = (product: ProductOption) => {
        if (
            createForm.data.items.some(
                (item) => item.inventory_product_id === product.id,
            )
        ) {
            return;
        }

        createForm.setData('items', [
            ...createForm.data.items,
            {
                inventory_product_id: product.id,
                source_branch_id: product.branch.id,
                source_branch_display_name: product.branch.displayName,
                source_warehouse_code: product.branch.warehouseCode,
                source_stock: product.current_stock,
                source_stock_verified: true,
                product_code: product.code,
                product_name: product.name,
                unit: product.unit ?? '',
                requested_qty: '1',
            },
        ]);
    };

    const updateDraftItem = (index: number, patch: Partial<DraftItem>) => {
        createForm.setData(
            'items',
            createForm.data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const removeDraftItem = (index: number) => {
        createForm.setData(
            'items',
            createForm.data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const submitCreate = () => {
        if (validDraftItems.length === 0) {
            return;
        }

        createForm.transform((data) => ({ ...data, items: validDraftItems }));
        createForm.post('/inventory/transfers', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                createForm.setData({
                    idempotency_key: newIdempotencyKey(),
                    notes: '',
                    items: [],
                });
            },
        });
    };

    const openWorkflow = (transfer: Transfer, mode: 'prepare' | 'receive') => {
        setWorkflowTransfer(transfer);
        setWorkflowMode(mode);
        workflowForm.setData({
            notes: '',
            items: transfer.items.map((item) => ({
                id: item.id,
                prepared_qty:
                    mode === 'prepare'
                        ? String(item.preparedQty ?? item.requestedQty)
                        : undefined,
                received_qty:
                    mode === 'receive'
                        ? String(
                              item.receivedQty ??
                                  item.preparedQty ??
                                  item.requestedQty,
                          )
                        : undefined,
                preparation_notes: item.preparationNotes ?? '',
                reception_notes: item.receptionNotes ?? '',
            })),
        });
    };

    const updateWorkflowItem = (
        index: number,
        patch: Partial<(typeof workflowForm.data.items)[number]>,
    ) => {
        workflowForm.setData(
            'items',
            workflowForm.data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const submitWorkflow = () => {
        if (!workflowTransfer || !workflowMode) {
            return;
        }

        workflowForm.post(
            workflowMode === 'prepare'
                ? `/inventory/transfers/${workflowTransfer.id}/start-preparing`
                : `/inventory/transfers/${workflowTransfer.id}/receive`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setWorkflowTransfer(null);
                    setWorkflowMode(null);
                    workflowForm.reset();
                },
            },
        );
    };

    const postAction = (transfer: Transfer, action: string) => {
        router.post(
            `/inventory/transfers/${transfer.id}/${action}`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Traspasos" />

            <div className="flex flex-col gap-5 p-4 pb-28 font-sans sm:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Traspasos inteligentes
                        </h1>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                            Solicitud, preparacion, envio, recepcion y cierre en
                            TINI sin modificar stock desde la capa inteligente.
                        </p>
                    </div>
                    {permissions.canCreate && (
                        <Button
                            onClick={() => setCreateOpen(true)}
                            className="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-zinc-50 dark:text-zinc-950"
                        >
                            <Plus className="h-4 w-4" />
                            Nuevo traspaso
                        </Button>
                    )}
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        icon={PackageCheck}
                        label="Abiertos"
                        value={stats.open}
                    />
                    <MetricCard
                        icon={Clock}
                        label="En preparacion"
                        value={stats.preparing}
                    />
                    <MetricCard
                        icon={Truck}
                        label="En envio"
                        value={stats.inTransit}
                    />
                    <MetricCard
                        icon={AlertTriangle}
                        label="Con novedades"
                        value={stats.withDiscrepancy}
                    />
                </section>

                <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex flex-wrap gap-2">
                                {tabs.map((tab) => (
                                    <Button
                                        key={tab.value}
                                        variant={
                                            filters.tab === tab.value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            updateFilters({ tab: tab.value })
                                        }
                                    >
                                        {tab.label}
                                    </Button>
                                ))}
                            </div>
                            <div className="relative w-full lg:w-80">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                                <Input
                                    className="pl-9"
                                    placeholder="Buscar producto o sucursal"
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
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {transfers.data.length === 0 ? (
                            <EmptyState />
                        ) : (
                            transfers.data.map((transfer) => (
                                <TransferPanel
                                    key={transfer.id}
                                    transfer={transfer}
                                    onCompleteTini={() =>
                                        postAction(transfer, 'complete-tini')
                                    }
                                    onPrepare={() =>
                                        openWorkflow(transfer, 'prepare')
                                    }
                                    onReadyToShip={() =>
                                        postAction(transfer, 'ready-to-ship')
                                    }
                                    onReceive={() =>
                                        openWorkflow(transfer, 'receive')
                                    }
                                    onShip={() => postAction(transfer, 'ship')}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Nuevo traspaso</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-300">
                            Solicitas para {formatBranch(activeBranch)}. Busca
                            el producto y te mostramos stock por bodega, con
                            sugerencia automatica segun mayor existencia.
                        </div>

                        <div className="space-y-2">
                            <Label>Buscar producto en todas las bodegas</Label>
                            <div className="flex gap-2">
                                <Input
                                    value={productSearch}
                                    placeholder="Codigo o nombre del producto"
                                    className="min-w-0"
                                    autoFocus
                                    onChange={(event) =>
                                        setProductSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            searchProducts();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => searchProducts()}
                                    disabled={productSearch.trim().length < 2}
                                >
                                    <Search className="h-4 w-4" />
                                    Buscar
                                </Button>
                            </div>
                        </div>

                        {productOptions.length > 0 && (
                            <div className="max-h-72 divide-y divide-neutral-200 overflow-y-auto rounded-lg border border-neutral-200 dark:divide-zinc-800 dark:border-zinc-800">
                                {productOptions.map((product) => (
                                    <button
                                        key={product.id}
                                        type="button"
                                        className="grid w-full gap-3 p-3 text-left transition-colors hover:bg-neutral-50 md:grid-cols-[minmax(0,1fr)_180px_120px_auto] md:items-center dark:hover:bg-zinc-800/40"
                                        onClick={() => addProduct(product)}
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="truncate text-sm font-medium">
                                                    {product.name}
                                                </p>
                                                {product.isRecommended && (
                                                    <Badge variant="outline">
                                                        Sugerido
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 text-xs text-neutral-500">
                                                {product.code}
                                            </p>
                                        </div>
                                        <div className="text-sm">
                                            <p className="font-medium">
                                                {product.branch.displayName}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {product.branch.warehouseCode
                                                    ? `Bodega ${product.branch.warehouseCode}`
                                                    : product.branch.code}
                                            </p>
                                        </div>
                                        <div className="text-sm">
                                            <p className="font-semibold">
                                                {quantityFormatter.format(
                                                    product.current_stock,
                                                )}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                stock
                                            </p>
                                        </div>
                                        <Plus className="h-4 w-4 shrink-0 text-neutral-500" />
                                    </button>
                                ))}
                            </div>
                        )}
                        {productOptions.length === 0 && (
                            <div className="flex min-h-24 items-center justify-center rounded-lg border border-neutral-200 px-4 text-center text-sm text-neutral-500 dark:border-zinc-800">
                                {hasProductSearch
                                    ? 'No encontramos productos con esa busqueda en otras bodegas.'
                                    : 'Escribe al menos 2 caracteres para ver coincidencias, stock por bodega y sugerencia de fuente.'}
                            </div>
                        )}

                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Label>Productos solicitados</Label>
                                {selectedSourceCount > 1 && (
                                    <Badge variant="outline">
                                        Se crearan solicitudes por bodega
                                    </Badge>
                                )}
                            </div>

                            {createForm.data.items.length === 0 ? (
                                <div className="rounded-lg border border-dashed border-neutral-200 p-4 text-sm text-neutral-500 dark:border-zinc-800">
                                    Busca productos cargados y selecciona la
                                    bodega desde la lista de stock sugerida.
                                </div>
                            ) : (
                                createForm.data.items.map((item, index) => (
                                    <div
                                        key={`${item.inventory_product_id ?? 'manual'}-${index}`}
                                        className="grid gap-3 rounded-lg border border-neutral-200 p-3 md:grid-cols-[minmax(0,1fr)_170px_110px_auto] md:items-center dark:border-zinc-800"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {item.product_name}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {item.product_code}
                                                {item.unit
                                                    ? ` / ${item.unit}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="text-sm">
                                            <p className="font-medium">
                                                {
                                                    item.source_branch_display_name
                                                }
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {item.source_warehouse_code
                                                    ? `Bodega ${item.source_warehouse_code}`
                                                    : 'Bodega fuente'}
                                                {item.source_stock !== null
                                                    ? ` / stock ${quantityFormatter.format(item.source_stock)}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <Input
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            aria-label="Cantidad solicitada"
                                            value={item.requested_qty}
                                            onChange={(event) =>
                                                updateDraftItem(index, {
                                                    requested_qty:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            onClick={() =>
                                                removeDraftItem(index)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Nota opcional</Label>
                            <textarea
                                className="min-h-24 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-zinc-800 dark:bg-zinc-950"
                                value={createForm.data.notes}
                                onChange={(event) =>
                                    createForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCreateOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            disabled={
                                createForm.processing ||
                                validDraftItems.length === 0
                            }
                            onClick={submitCreate}
                        >
                            Crear solicitud
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={workflowTransfer !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setWorkflowTransfer(null);
                        setWorkflowMode(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {workflowMode === 'prepare'
                                ? 'Preparar traspaso'
                                : 'Confirmar recepcion'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-3">
                        {selectedTransferRows.map(
                            ({ item, formItem }, index) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border border-neutral-200 p-3 dark:border-zinc-800"
                                >
                                    <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {item.productName}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {item.productCode} · Solicitado{' '}
                                                {quantityFormatter.format(
                                                    item.requestedQty,
                                                )}
                                            </p>
                                        </div>
                                        {!item.sourceStockVerified && (
                                            <Badge variant="outline">
                                                Stock no verificado
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="mt-3 grid gap-3 md:grid-cols-[160px_minmax(0,1fr)]">
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            value={
                                                workflowMode === 'prepare'
                                                    ? (formItem?.prepared_qty ??
                                                      '')
                                                    : (formItem?.received_qty ??
                                                      '')
                                            }
                                            onChange={(event) =>
                                                updateWorkflowItem(
                                                    index,
                                                    workflowMode === 'prepare'
                                                        ? {
                                                              prepared_qty:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : {
                                                              received_qty:
                                                                  event.target
                                                                      .value,
                                                          },
                                                )
                                            }
                                        />
                                        <Input
                                            placeholder="Nota de linea"
                                            value={
                                                workflowMode === 'prepare'
                                                    ? (formItem?.preparation_notes ??
                                                      '')
                                                    : (formItem?.reception_notes ??
                                                      '')
                                            }
                                            onChange={(event) =>
                                                updateWorkflowItem(
                                                    index,
                                                    workflowMode === 'prepare'
                                                        ? {
                                                              preparation_notes:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : {
                                                              reception_notes:
                                                                  event.target
                                                                      .value,
                                                          },
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            ),
                        )}

                        <div className="space-y-2">
                            <Label>Nota general</Label>
                            <textarea
                                className="min-h-24 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-zinc-800 dark:bg-zinc-950"
                                value={workflowForm.data.notes}
                                onChange={(event) =>
                                    workflowForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setWorkflowTransfer(null);
                                setWorkflowMode(null);
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            disabled={workflowForm.processing}
                            onClick={submitWorkflow}
                        >
                            {workflowMode === 'prepare'
                                ? 'Iniciar preparacion'
                                : 'Confirmar recepcion'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof PackageCheck;
    label: string;
    value: number;
}) {
    return (
        <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-neutral-500">
                    {label}
                </CardTitle>
                <Icon className="h-4 w-4 text-neutral-500" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tracking-[-0.02em]">
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}

function TransferPanel({
    transfer,
    onCompleteTini,
    onPrepare,
    onReadyToShip,
    onReceive,
    onShip,
}: {
    transfer: Transfer;
    onCompleteTini: () => void;
    onPrepare: () => void;
    onReadyToShip: () => void;
    onReceive: () => void;
    onShip: () => void;
}) {
    return (
        <div className="rounded-xl border border-neutral-200 p-4 dark:border-zinc-800">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-base font-semibold tracking-[-0.02em]">
                            Traspaso #{transfer.id}
                        </h2>
                        <Badge
                            variant="outline"
                            className={`ring-1 ${statusTone[transfer.status] ?? statusTone.requested}`}
                        >
                            {transfer.statusLabel}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-neutral-500">
                        {formatBranch(transfer.destinationBranch)} solicita a{' '}
                        {formatBranch(transfer.sourceBranch)} ·{' '}
                        {transfer.createdAt}
                    </p>
                    {transfer.requestNotes && (
                        <p className="mt-2 text-sm text-neutral-600 dark:text-zinc-400">
                            {transfer.requestNotes}
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    {transfer.status === 'requested' &&
                        transfer.permissions.canPrepare && (
                            <Button size="sm" onClick={onPrepare}>
                                <PackageCheck className="h-4 w-4" />
                                Preparar
                            </Button>
                        )}
                    {transfer.status === 'preparing' &&
                        transfer.permissions.canPrepare && (
                            <Button size="sm" onClick={onReadyToShip}>
                                <Truck className="h-4 w-4" />
                                Montado al camion
                            </Button>
                        )}
                    {transfer.status === 'ready_to_ship' &&
                        transfer.permissions.canPrepare && (
                            <Button size="sm" onClick={onShip}>
                                <Send className="h-4 w-4" />
                                Completar envio
                            </Button>
                        )}
                    {transfer.status === 'in_transit' &&
                        transfer.permissions.canReceive && (
                            <Button size="sm" onClick={onReceive}>
                                <CheckCircle2 className="h-4 w-4" />
                                Recibir
                            </Button>
                        )}
                    {['received', 'received_discrepancy'].includes(
                        transfer.status,
                    ) &&
                        transfer.permissions.canCompleteTini && (
                            <Button size="sm" onClick={onCompleteTini}>
                                <FileCheck2 className="h-4 w-4" />
                                Formalizado en TINI
                            </Button>
                        )}
                </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-lg border border-neutral-200 dark:border-zinc-800">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                        <tr>
                            <th className="px-3 py-2 font-medium">Producto</th>
                            <th className="px-3 py-2 text-right font-medium">
                                Solicitado
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Preparado
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Recibido
                            </th>
                            <th className="px-3 py-2 font-medium">Stock</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800">
                        {transfer.items.map((item) => (
                            <tr key={item.id}>
                                <td className="px-3 py-2">
                                    <p className="font-medium">
                                        {item.productName}
                                    </p>
                                    <p className="text-xs text-neutral-500">
                                        {item.productCode}
                                    </p>
                                </td>
                                <td className="px-3 py-2 text-right">
                                    {quantityFormatter.format(
                                        item.requestedQty,
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    {item.preparedQty === null
                                        ? 'Pendiente'
                                        : quantityFormatter.format(
                                              item.preparedQty,
                                          )}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    {item.receivedQty === null
                                        ? 'Pendiente'
                                        : quantityFormatter.format(
                                              item.receivedQty,
                                          )}
                                </td>
                                <td className="px-3 py-2">
                                    {item.sourceStockVerified ? (
                                        <span className="text-neutral-500">
                                            {item.sourceStockSnapshot === null
                                                ? 'Sin dato'
                                                : quantityFormatter.format(
                                                      item.sourceStockSnapshot,
                                                  )}
                                        </span>
                                    ) : (
                                        <span className="text-amber-600">
                                            No verificado
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-2">
                {transfer.events.map((event) => (
                    <div key={event.id} className="flex gap-3 text-sm">
                        <span className="mt-1.5 h-2 w-2 rounded-full bg-neutral-900 dark:bg-zinc-50" />
                        <div>
                            <p className="font-medium">{event.title}</p>
                            <p className="text-xs text-neutral-500">
                                {event.createdAt}
                                {event.user ? ` · ${event.user}` : ''}
                            </p>
                            {event.body && (
                                <p className="mt-1 text-neutral-500">
                                    {event.body}
                                </p>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-neutral-200 p-8 text-center dark:border-zinc-800">
            <Truck className="mx-auto h-6 w-6 text-neutral-400" />
            <p className="mt-3 text-sm font-medium">
                No hay traspasos en esta vista
            </p>
            <p className="mt-1 text-sm text-neutral-500">
                Cuando una sucursal solicite productos, la tarea aparecera aqui.
            </p>
        </div>
    );
}

TransfersIndex.layout = {
    breadcrumbs: [
        { title: 'Inventario', href: '/inventory/products' },
        { title: 'Traspasos', href: '/inventory/transfers' },
    ],
};

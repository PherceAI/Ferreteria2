import { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Mail,
    ChevronDown,
    Package,
    ArrowRight,
    Search,
    ShieldAlert,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

// --- MOCK DATA ---
const MOCK_PURCHASES = [
    {
        id: 'C-001',
        status: 'VALIDADA',
        supplier: 'Disensa Ecuador',
        invoice: '#001-001-000891',
        invoiceDate: '18/04/2026',
        receiptDate: '20/04/2026',
        currentStep: 4,
        products: [
            { name: 'Cemento Holcim 50Kg', oc: '100 qq', inv: '100 qq', rec: '100 qq', state: 'Coincide' },
            { name: 'Bloque 15cm', oc: '500 U', inv: '500 U', rec: '500 U', state: 'Coincide' },
        ],
        logs: [
            'OC generada por: Ana Castillo — 17/04/2026',
            'Factura detectada en Gmail: 18/04/2026 10:15',
            'Recepción registrada por: Juan Morocho — 20/04/2026 08:30',
            'Validación automática exitosa — 20/04/2026 08:31'
        ]
    },
    {
        id: 'C-002',
        status: 'CON DIFERENCIA',
        supplier: 'Ideal Alambrec',
        invoice: '#001-002-000456',
        invoiceDate: '19/04/2026',
        receiptDate: '21/04/2026 (hoy)',
        currentStep: 3,
        errorStep: 4,
        products: [
            { name: 'Varilla Corrugada 12mm', oc: '200 U', inv: '200 U', rec: '200 U', state: 'Coincide' },
            { name: 'Clavo Acero 2"', oc: '50 cjs', inv: '50 cjs', rec: '43 cjs', state: 'Diferencia' },
        ],
        logs: [
            'OC generada por: Ana Castillo — 17/04/2026',
            'Factura detectada en Gmail: 19/04/2026 14:23',
            'Recepción registrada por: Juan Morocho — 21/04/2026 09:30',
            'Diferencia reportada automáticamente — 21/04/2026 09:31'
        ]
    },
    {
        id: 'C-003',
        status: 'ESPERANDO PRODUCTO',
        supplier: 'Plastigama',
        invoice: '#002-001-000789',
        invoiceDate: '20/04/2026',
        receiptDate: 'Pendiente',
        currentStep: 2,
        pulsingStep: 3,
        products: [
            { name: 'Tubo PVC 1/2" Tigre', oc: '200 U', inv: '200 U', rec: '—', state: 'Esperando' },
            { name: 'Codo PVC 1/2"', oc: '100 U', inv: '100 U', rec: '—', state: 'Esperando' },
        ],
        logs: [
            'OC generada por: Ana Castillo — 18/04/2026',
            'Factura detectada en Gmail: 20/04/2026 09:15',
            'Alerta automática de producto no recibido — 21/04/2026 07:00'
        ]
    },
    {
        id: 'C-004',
        status: 'ESPERANDO CONFIRMACION',
        supplier: 'Sika Ecuador',
        invoice: '#001-003-000567',
        invoiceDate: '21/04/2026',
        receiptDate: '—',
        currentStep: 2,
        products: [
            { name: 'Sikaflex-1A Cartucho', oc: 'Pendiente', inv: '30 U × $8.50', rec: '—', state: 'Esperando' },
            { name: 'Impermeabilizante Sika 1', oc: 'Pendiente', inv: '10 gal × $22.00', rec: '—', state: 'Esperando' },
        ],
        logs: [
            'Factura detectada en Gmail: 21/04/2026 08:15',
            'En espera de confirmación de recepción física — 21/04/2026 08:16'
        ]
    }
];

const TIMELINE = [
    { time: '09:31', prefix: 'Diferencia detectada:', text: 'Clavo Acero 2" (7 cjs faltantes) — Ideal Alambrec', type: 'error' },
    { time: '09:30', prefix: 'Recepción registrada:', text: 'Juan Morocho confirmó ingreso parcial — Ideal Alambrec', type: 'receipt' },
    { time: '07:00', prefix: 'Alerta de tiempo:', text: 'Factura de Plastigama lleva 1 día sin recepción física', type: 'warning' },
    { time: 'Ayer 16:45', prefix: 'Compra validada:', text: 'Disensa Ecuador — 100 qq Cemento + 500 Bloques — Todo coincide', type: 'success' },
    { time: 'Ayer 14:23', prefix: 'Factura detectada en Gmail:', text: 'Ideal Alambrec — $325.00', type: 'invoice' },
];

// --- COMPONENTS ---
const StepIndicator = ({ stepNum, label, currentStep, pulsingStep, errorStep }: any) => {
    let circleClasses = "flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-semibold transition-all duration-300";
    let lineClasses = "h-1 w-12 sm:w-16 transition-colors duration-300 rounded-full";

    if (errorStep === stepNum) {
        circleClasses += " border-red-500 bg-red-50 text-red-600";
    } else if (stepNum <= currentStep) {
        circleClasses += " border-green-500 bg-green-500 text-white";
        lineClasses += " bg-green-500";
    } else if (pulsingStep === stepNum) {
        circleClasses += " border-amber-500 bg-amber-50 text-amber-600 animate-pulse";
        lineClasses += " bg-neutral-200 dark:bg-zinc-800";
    } else {
        circleClasses += " border-neutral-300 bg-white text-neutral-400 dark:border-zinc-700 dark:bg-zinc-900";
        lineClasses += " bg-neutral-200 dark:bg-zinc-800";
    }

    return (
        <div className="flex items-center">
            <div className="flex flex-col items-center">
                <div className={circleClasses}>
                    {errorStep === stepNum ? '✗' : (stepNum <= currentStep ? '✓' : stepNum)}
                </div>
                <span className="mt-2 text-xs font-medium text-neutral-500 hidden sm:block whitespace-nowrap">{label}</span>
            </div>
            {stepNum < 4 && (
                <div className={`mx-2 sm:mx-4 ${lineClasses} self-start mt-4`} />
            )}
        </div>
    );
};

export default function PurchasingReceiptIndex() {
    return (
        <>
            <Head title="Recepción y Validación de Compras" />

            <div className="flex flex-col gap-6 p-6 font-sans pb-32">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                        Recepción y Validación de Compras
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Cruce automático: Orden de Compra → Factura → Recepción Física
                    </p>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Facturas Detectadas</CardTitle>
                            <Mail className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em]">4</div>
                            <span className="text-xs text-neutral-500">Desde Gmail hoy</span>
                        </CardContent>
                    </Card>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Pendientes de Recepción</CardTitle>
                            <Clock className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em]">2</div>
                            <span className="text-xs text-neutral-500">Producto facturado sin llegar</span>
                        </CardContent>
                    </Card>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Con Diferencias</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em] text-red-600">1</div>
                            <span className="text-xs text-neutral-500">Requiere nota de crédito</span>
                        </CardContent>
                    </Card>
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Validadas Hoy</CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold tracking-[-0.02em] text-green-600">3</div>
                            <span className="text-xs text-neutral-500">Listas para registro en TINI</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Pipeline Section */}
                <div className="flex flex-col gap-4 mt-2">
                    <h2 className="text-lg font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">Órdenes en Validación</h2>
                    
                    {MOCK_PURCHASES.map((purchase) => (
                        <Collapsible key={purchase.id} className="group overflow-hidden rounded-xl border border-neutral-200 bg-white transition-all dark:border-zinc-800 dark:bg-zinc-900">
                            <CollapsibleTrigger className="flex w-full items-center justify-between p-5 hover:bg-neutral-50 dark:hover:bg-zinc-800/50">
                                <div className="flex flex-col gap-1 text-left md:flex-row md:items-center md:gap-4">
                                    <div className="w-48 font-medium text-neutral-900 dark:text-zinc-50">{purchase.supplier}</div>
                                    <div className="text-sm text-neutral-500">{purchase.invoice} • <span className="hidden sm:inline">Factura: </span>{purchase.invoiceDate}</div>
                                    <div className="mt-2 md:mt-0 md:ml-4">
                                        {purchase.status === 'VALIDADA' && <Badge className="bg-green-100 text-green-700 hover:bg-green-100 border-green-200">VALIDADO — Cruce de Mercancía Exitoso</Badge>}
                                        {purchase.status === 'CON DIFERENCIA' && <Badge className="bg-red-100 text-red-700 hover:bg-red-100 border-red-200">⚠️ DIFERENCIA FÍSICA — Requiere Nota de Crédito</Badge>}
                                        {purchase.status === 'ESPERANDO PRODUCTO' && <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100 border-amber-200">CONTABILIZADO — Esperando producto físico</Badge>}
                                        {purchase.status === 'ESPERANDO CONFIRMACION' && <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100 border-amber-200">CONTABILIZADO — Esperando revisión de bodega</Badge>}
                                    </div>
                                </div>
                                
                                <div className="flex items-center gap-6">
                                    <div className="hidden lg:flex items-center">
                                        <StepIndicator stepNum={1} label="OC" {...purchase} />
                                        <StepIndicator stepNum={2} label="Factura" {...purchase} />
                                        <StepIndicator stepNum={3} label="Recepción" {...purchase} />
                                        <StepIndicator stepNum={4} label="Validado" {...purchase} />
                                    </div>
                                    <ChevronDown className="h-5 w-5 text-neutral-400 transition-transform duration-300 group-data-[state=open]:rotate-180" />
                                </div>
                            </CollapsibleTrigger>
                            
                            <CollapsibleContent className="border-t border-neutral-100 bg-neutral-50/50 p-5 px-6 dark:border-zinc-800 dark:bg-zinc-950/50">
                                
                                {/* ALERTS BLOCK */}
                                {purchase.status === 'CON DIFERENCIA' && (
                                    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                                        <div className="flex items-start gap-3">
                                            <ShieldAlert className="mt-0.5 h-5 w-5 text-red-600" />
                                            <div className="flex flex-col gap-2">
                                                <p><strong>Discrepancia en Recepción Física:</strong> La factura electrónica registrada en TINI indica 50 cajas, pero bodega solo recibió 43. Faltan 7 cajas.</p>
                                                <div>
                                                    <strong>Atención — Descuadre en TINI:</strong>
                                                    <ul className="list-inside list-decimal mt-1">
                                                        <li>Actualmente, TINI refleja <strong>50 cajas</strong> disponibles en stock (según el documento legal).</li>
                                                        <li>Se debe contactar al proveedor ({purchase.supplier}) para solicitar una <strong>Nota de Crédito</strong> por las 7 cajas no recibidas.</li>
                                                        <li>Notifique a Ventas para evitar que ofrezcan este "stock fantasma" hasta que Contabilidad procese la corrección en TINI.</li>
                                                    </ul>
                                                </div>
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    <Button variant="outline" size="sm" className="border-red-200 text-red-700 hover:bg-red-100">Alertar Stock Fantasma a Ventas</Button>
                                                    <Button variant="outline" size="sm" className="border-neutral-200 text-neutral-600">Gestionar Reclamo con Proveedor</Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {purchase.status === 'VALIDADA' && (
                                    <div className="mb-6 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
                                        <p><strong>Cruce exitoso.</strong> La mercancía recibida físicamente en bodega coincide exactamente con la factura que contabilidad registró en TINI. El stock es real y seguro para la venta.</p>
                                        <Button className="bg-green-600 text-white hover:bg-green-700" size="sm">Archivar Validación</Button>
                                    </div>
                                )}

                                {purchase.status === 'ESPERANDO PRODUCTO' && (
                                    <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                                        <div className="flex items-start gap-3">
                                            <AlertTriangle className="mt-0.5 h-5 w-5 text-amber-600" />
                                            <div className="flex flex-col gap-2">
                                                <p><strong>Alerta de Desfase:</strong> Contabilidad ya registró la factura en TINI el {purchase.invoiceDate}, pero <strong>la mercancía aún no llega a bodega</strong>.</p>
                                                <p><strong>Tiempo transcurrido:</strong> 1 día, 8 horas sin ingreso físico.</p>
                                                <p className="text-amber-700"><strong>Importante:</strong> TINI muestra este stock como disponible. Se ha emitido una advertencia a Ventas para evitar que ofrezcan mercancía que físicamente no existe en percha.</p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    <Button className="bg-green-600 text-white hover:bg-green-700" size="sm">Confirmar Llegada Física</Button>
                                                    <Button variant="outline" className="border-amber-200 text-amber-800 hover:bg-amber-100" size="sm">Emitir Recordatorio a Bodega</Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {purchase.status === 'ESPERANDO CONFIRMACION' && (
                                    <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                                        <div className="flex items-start gap-3">
                                            <AlertTriangle className="mt-0.5 h-5 w-5 text-amber-600" />
                                            <div className="flex flex-col gap-2">
                                                <p><strong>Revisión Pendiente:</strong> La factura está registrada en TINI. Bodega debe confirmar si las cantidades físicas coinciden exactamente con lo facturado en cuanto se descargue la mercancía.</p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    <Button className="bg-green-600 text-white hover:bg-green-700" size="sm">Iniciar Verificación Física</Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* DATA TABLE */}
                                <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 mb-6">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">Producto</th>
                                                <th className="px-4 py-3 font-medium">OC (Lo que se pidió)</th>
                                                <th className="px-4 py-3 font-medium">Factura (Facturado)</th>
                                                <th className="px-4 py-3 font-medium">Recepción (Físico)</th>
                                                <th className="px-4 py-3 font-medium text-right">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                            {purchase.products.map((prod, idx) => (
                                                <tr key={idx} className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20">
                                                    <td className="px-4 py-3 font-medium text-neutral-900 dark:text-zinc-50">{prod.name}</td>
                                                    <td className={`px-4 py-3 ${prod.oc === 'No hay OC' ? 'text-red-500 font-semibold' : 'text-neutral-600 dark:text-neutral-400'}`}>{prod.oc}</td>
                                                    <td className="px-4 py-3 font-medium text-neutral-700 dark:text-zinc-300">{prod.inv}</td>
                                                    <td className={`px-4 py-3 font-bold ${prod.state === 'Diferencia' ? 'text-red-600' : 'text-neutral-900 dark:text-zinc-50'}`}>{prod.rec}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        {prod.state === 'Coincide' && <span className="flex items-center justify-end gap-1 text-green-600 font-medium"><CheckCircle2 className="h-4 w-4"/> {prod.state}</span>}
                                                        {prod.state === 'Diferencia' && <span className="flex items-center justify-end gap-1 text-red-600 font-bold"><AlertTriangle className="h-4 w-4"/> {prod.state}</span>}
                                                        {prod.state === 'Esperando' && <span className="flex items-center justify-end gap-1 text-amber-600 font-medium"><Clock className="h-4 w-4"/> {prod.state}</span>}
                                                        {prod.state === 'Sin autorización' && <span className="flex items-center justify-end gap-1 text-orange-600 font-medium"><Search className="h-4 w-4"/> {prod.state}</span>}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* LOGS / TIMELINE OF CARD */}
                                <div className="text-xs text-neutral-500 font-mono">
                                    <div className="font-semibold text-neutral-700 dark:text-neutral-300 mb-2 font-sans">Trazabilidad:</div>
                                    <ul className="space-y-1">
                                        {purchase.logs.map((log, idx) => (
                                            <li key={idx} className="flex items-start gap-2">
                                                <span className="mt-1 h-1.5 w-1.5 rounded-full bg-neutral-300 flex-shrink-0" />
                                                <span>{log}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                            </CollapsibleContent>
                        </Collapsible>
                    ))}
                </div>

                {/* Bottom Timeline Section */}
                <div className="mt-6 flex flex-col gap-4">
                    <h2 className="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-zinc-400">ACTIVIDAD RECIENTE</h2>
                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div className="flex flex-col gap-6">
                            {TIMELINE.map((item, idx) => (
                                <div key={idx} className="flex items-start gap-4">
                                    <div className="relative flex items-center justify-center">
                                        {idx !== TIMELINE.length - 1 && (
                                            <div className="absolute top-6 bottom-[-24px] w-px bg-neutral-200 dark:bg-zinc-800" />
                                        )}
                                        {item.type === 'error' && <div className="flex h-6 w-6 items-center justify-center rounded-full bg-red-50 text-red-500 z-10 ring-4 ring-white dark:ring-zinc-900 dark:bg-red-500/20"><AlertTriangle className="h-3 w-3" /></div>}
                                        {item.type === 'receipt' && <div className="flex h-6 w-6 items-center justify-center rounded-full bg-blue-50 text-blue-500 z-10 ring-4 ring-white dark:ring-zinc-900 dark:bg-blue-500/20"><Package className="h-3 w-3" /></div>}
                                        {item.type === 'invoice' && <div className="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 z-10 ring-4 ring-white dark:ring-zinc-900 dark:bg-zinc-800"><Mail className="h-3 w-3" /></div>}
                                        {item.type === 'warning' && <div className="flex h-6 w-6 items-center justify-center rounded-full bg-amber-50 text-amber-500 z-10 ring-4 ring-white dark:ring-zinc-900 dark:bg-amber-500/20"><Clock className="h-3 w-3" /></div>}
                                        {item.type === 'success' && <div className="flex h-6 w-6 items-center justify-center rounded-full bg-green-50 text-green-500 z-10 ring-4 ring-white dark:ring-zinc-900 dark:bg-green-500/20"><CheckCircle2 className="h-3 w-3" /></div>}
                                    </div>
                                    <div className="flex items-center gap-4 pt-0.5 w-full">
                                        <div className="w-16 text-xs font-medium text-neutral-500 shrink-0">{item.time}</div>
                                        <div className={`text-sm ${item.type === 'error' ? 'text-red-600 dark:text-red-400' : 'text-neutral-600 dark:text-zinc-300'}`}>
                                            <span className="font-medium">{item.prefix}</span> {item.text}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

            </div>
        </>
    );
}

PurchasingReceiptIndex.layout = {
    breadcrumbs: [
        { title: 'Compras', href: '/compras' },
        { title: 'Recepción y Validación', href: '/compras/recepcion' },
    ],
};

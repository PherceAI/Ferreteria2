import { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Info,
    Search,
    Truck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { MOCK_TRANSFERS } from '@/data/mock';

export default function TransfersIndex() {
    const [transfers, setTransfers] = useState(MOCK_TRANSFERS);
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
    const [isIncidentModalOpen, setIsIncidentModalOpen] = useState(false);
    const [selectedTransfer, setSelectedTransfer] = useState<any>(null);
    const [isExactAmount, setIsExactAmount] = useState(true);

    const handleConfirmClick = (transfer: any) => {
        setSelectedTransfer(transfer);
        setIsConfirmModalOpen(true);
        setIsExactAmount(true);
    };

    const handleResolveClick = (transfer: any) => {
        setSelectedTransfer(transfer);
        setIsIncidentModalOpen(true);
    };

    const confirmReceipt = () => {
        if (!selectedTransfer) return;
        setTransfers((prev) =>
            prev.map((t) =>
                t.id === selectedTransfer.id ? { ...t, status: 'Recibido' } : t
            )
        );
        setIsConfirmModalOpen(false);
    };

    const resolveIncident = () => {
        if (!selectedTransfer) return;
        setTransfers((prev) =>
            prev.map((t) =>
                t.id === selectedTransfer.id ? { ...t, status: 'Recibido' } : t
            )
        );
        setIsIncidentModalOpen(false);
    };

    return (
        <>
            <Head title="Traspasos" />

            <div className="flex flex-col gap-6 p-6 font-sans">
                {/* Cabecera */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-neutral-900 dark:text-zinc-50">
                            Gestión de Traspasos
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-zinc-400">
                            Control de movimientos entre sucursales con
                            confirmación obligatoria
                        </p>
                    </div>
                    <Button className="bg-red-600 font-semibold text-white hover:bg-red-700">
                        Nuevo Traspaso
                    </Button>
                </div>

                {/* Mensaje Clave */}
                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                    <Info className="mt-0.5 h-5 w-5 text-blue-600" />
                    <div className="flex flex-col gap-1">
                        <span className="text-sm font-semibold text-blue-900 dark:text-blue-400 tracking-tight">
                            Flujo de Validación Físico (Pre-TINI)
                        </span>
                        <p className="text-sm text-blue-800 dark:text-blue-300 leading-relaxed">
                            Para evitar descuadres, todo traspaso inicia por esta vía. Al generarlo, <strong>se enviará una notificación a la sucursal de destino</strong>. Solo cuando ellos validen físicamente la recepción de la mercancía, el movimiento debe ser ingresado al sistema TINI.
                        </p>
                    </div>
                </div>

                {/* 4 Summary Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Traspasos Hoy
                            </CardTitle>
                            <Truck className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">7</div>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                En Tránsito
                            </CardTitle>
                            <Clock className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">3</div>
                            <span className="mt-1 inline-flex items-center rounded-sm bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/50 dark:text-amber-400">
                                Pendiente confirmar
                            </span>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Completados (Semana)
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">23</div>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">
                                Incidencias
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">1</div>
                            <span className="mt-1 inline-flex items-center rounded-sm bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/50 dark:text-red-400">
                                Requiere atención
                            </span>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabla */}
                <div className="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="flex items-center justify-between">
                        <div className="relative w-72">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-neutral-500" />
                            <Input
                                type="text"
                                placeholder="Buscar traspaso..."
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-zinc-800">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                                <tr>
                                    <th className="px-4 py-3 font-medium">#</th>
                                    <th className="px-4 py-3 font-medium">
                                        Origen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Destino
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Producto
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Cant.
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Solicitado por
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Fecha
                                    </th>
                                    <th className="px-4 py-3 font-medium text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-zinc-800/50">
                                {transfers.map((t) => (
                                    <tr
                                        key={t.id}
                                        className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {t.id}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.origin}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.destination}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.product}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {t.qty}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.status === 'Recibido' && (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    Recibido
                                                </span>
                                            )}
                                            {t.status === 'En Tránsito' && (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                    <Clock className="h-3 w-3" />
                                                    En Tránsito
                                                </span>
                                            )}
                                            {t.status ===
                                                'Pendiente Envío' && (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                    <Clock className="h-3 w-3" />
                                                    Pendiente Envío
                                                </span>
                                            )}
                                            {t.status === 'Incidencia' && (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                    <AlertTriangle className="h-3 w-3" />
                                                    Incidencia
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.requestedBy}
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {t.date}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {t.status === 'En Tránsito' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-green-200 bg-green-50 text-green-700 hover:bg-green-100 hover:text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/40"
                                                    onClick={() =>
                                                        handleConfirmClick(t)
                                                    }
                                                >
                                                    Confirmar Recepción
                                                </Button>
                                            )}
                                            {t.status === 'Pendiente Envío' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Despachar
                                                </Button>
                                            )}
                                            {t.status === 'Incidencia' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-900 dark:bg-red-900/20 dark:text-red-400"
                                                    onClick={() =>
                                                        handleResolveClick(t)
                                                    }
                                                >
                                                    Resolver
                                                </Button>
                                            )}
                                            {t.status === 'Recibido' && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-blue-600"
                                                >
                                                    Ver detalle
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* MODAL: Confirmar Recepción */}
                <Dialog
                    open={isConfirmModalOpen}
                    onOpenChange={setIsConfirmModalOpen}
                >
                    <DialogContent className="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle>
                                Confirmar Recepción de Mercadería
                            </DialogTitle>
                            <DialogDescription>
                                Estás confirmando el ingreso físico a la bodega{' '}
                                {selectedTransfer?.destination}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-right font-medium">
                                    Producto
                                </Label>
                                <div className="col-span-3 text-sm font-semibold">
                                    {selectedTransfer?.product}
                                </div>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-right font-medium">
                                    Enviado
                                </Label>
                                <div className="col-span-3 text-sm font-semibold text-neutral-600">
                                    {selectedTransfer?.qty}
                                </div>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="received"
                                    className="text-right"
                                >
                                    Recibido
                                </Label>
                                <Input
                                    id="received"
                                    defaultValue={
                                        selectedTransfer?.qty.split(' ')[0]
                                    }
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-start gap-4">
                                <Label
                                    htmlFor="obs"
                                    className="mt-2 text-right"
                                >
                                    Obs.
                                </Label>
                                <textarea
                                    id="obs"
                                    placeholder="Novedades opcionales..."
                                    className="col-span-3 flex min-h-[80px] w-full rounded-md border border-neutral-200 bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-neutral-500 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-neutral-950 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:placeholder:text-zinc-400 dark:focus-visible:ring-zinc-300"
                                />
                            </div>
                            <div className="flex items-center space-x-2 pt-2 ml-4">
                                <Checkbox
                                    id="exact"
                                    checked={isExactAmount}
                                    onCheckedChange={(val) =>
                                        setIsExactAmount(!!val)
                                    }
                                />
                                <label
                                    htmlFor="exact"
                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    La cantidad recibida coincide con lo enviado
                                </label>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setIsConfirmModalOpen(false)}
                            >
                                Cancelar
                            </Button>
                            {isExactAmount ? (
                                <Button
                                    onClick={confirmReceipt}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    Confirmar Recepción
                                </Button>
                            ) : (
                                <Button
                                    onClick={() => alert('Diferencia reportada')}
                                    className="bg-amber-500 hover:bg-amber-600"
                                >
                                    Reportar Diferencia
                                </Button>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* MODAL: Resolver Incidencia */}
                <Dialog
                    open={isIncidentModalOpen}
                    onOpenChange={setIsIncidentModalOpen}
                >
                    <DialogContent className="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-red-600">
                                <AlertTriangle className="h-5 w-5" />
                                Incidencia en Traspaso {selectedTransfer?.id}
                            </DialogTitle>
                            <DialogDescription className="pt-2 text-sm text-neutral-700">
                                Se enviaron 40 unidades de Sellador Silicona.
                                Bodega {selectedTransfer?.destination} reportó
                                recibir solo 36 unidades.
                                <br />
                                <br />
                                <strong>Diferencia: 4 unidades</strong>
                                <br />
                                <span className="text-xs text-neutral-500">
                                    Registrado por: Carlos Pinto — 20/04/2026
                                    11:45
                                </span>
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="mt-4 gap-2 sm:justify-start">
                            <Button
                                onClick={resolveIncident}
                                className="bg-red-600 hover:bg-red-700"
                            >
                                Aprobar Ajuste
                            </Button>
                            <Button variant="outline">
                                Escalar a Gerencia
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

TransfersIndex.layout = {
    breadcrumbs: [
        { title: 'Inventario', href: '/inventory/products' },
        { title: 'Traspasos', href: '/inventory/transfers' },
    ],
};

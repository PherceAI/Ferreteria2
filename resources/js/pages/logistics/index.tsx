import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    RefreshCw,
    Truck,
    Zap,
    MapPin,
    Bell,
    BatteryFull,
    Activity,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';

// ─── Types ────────────────────────────────────────────────────────────────────
interface Vehicle {
    id: number;
    name: string;
    status: 'moving' | 'idle' | 'stopped' | 'offline';
    speed: number;
    stop_duration: string;
    stop_duration_sec: number;
    engine_on: boolean;
    battery_vehicle: string;
    satellites: number;
    lat: number;
    lng: number;
    total_distance_km: number;
    expiration_date: string | null;
}

interface FleetAlert {
    vehicleName: string;
    type: 'critical' | 'warning' | 'info';
    message: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function deriveAlerts(vehicles: Vehicle[]): FleetAlert[] {
    const alerts: FleetAlert[] = [];
    const now = new Date();

    vehicles.forEach((v) => {
        // Motor encendido sin moverse > 10 min
        if (v.status === 'idle' && v.stop_duration_sec > 600) {
            alerts.push({
                vehicleName: v.name,
                type: 'warning',
                message: `Motor encendido sin moverse hace ${v.stop_duration}. Posible desperdicio de combustible.`,
            });
        }
        // Velocidad excesiva
        if (v.speed > 80) {
            alerts.push({
                vehicleName: v.name,
                type: 'critical',
                message: `Circula a ${v.speed} km/h. Supera el límite recomendado para vehículos de carga.`,
            });
        }
        // Inactividad > 1 hora
        if (v.status === 'stopped' && v.stop_duration_sec > 3600) {
            alerts.push({
                vehicleName: v.name,
                type: 'info',
                message: `Detenido ${v.stop_duration} con motor apagado. ¿Novedad operativa?`,
            });
        }
        // GPS próximo a vencer (60 días)
        if (v.expiration_date) {
            const exp = new Date(v.expiration_date);
            const diffDays = Math.floor((exp.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
            if (diffDays <= 60) {
                alerts.push({
                    vehicleName: v.name,
                    type: 'warning',
                    message: `GPS vence en ${diffDays} días (${v.expiration_date}). Renovar antes de esa fecha.`,
                });
            }
        }
    });

    return alerts;
}

function StatusBadge({ status }: { status: Vehicle['status'] }) {
    const map: Record<Vehicle['status'], { label: string; classes: string; dot: string }> = {
        moving:  { label: 'En movimiento', classes: 'bg-green-100 text-green-700 border-green-200',   dot: 'bg-green-500' },
        idle:    { label: 'Ralentí',        classes: 'bg-amber-100 text-amber-700 border-amber-200',   dot: 'bg-amber-400 animate-pulse' },
        stopped: { label: 'Detenido',       classes: 'bg-red-100 text-red-700 border-red-200',         dot: 'bg-red-500' },
        offline: { label: 'Sin señal',      classes: 'bg-neutral-100 text-neutral-500 border-neutral-200', dot: 'bg-neutral-400' },
    };
    const cfg = map[status];
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${cfg.classes}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
            {cfg.label}
        </span>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function LogisticsIndex() {
    const [vehicles, setVehicles] = useState<Vehicle[]>([]);
    const [refreshedAt, setRefreshedAt] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [alertLoading, setAlertLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [alertMsg, setAlertMsg] = useState<string | null>(null);

    const alerts = deriveAlerts(vehicles);

    // KPIs
    const total   = vehicles.length;
    const moving  = vehicles.filter((v) => v.status === 'moving').length;
    const idle    = vehicles.filter((v) => v.status === 'idle').length;
    const stopped = vehicles.filter((v) => v.status === 'stopped').length;

    const handleRefresh = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch('/logistica/fleet/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.success) {
                setVehicles(data.vehicles);
                setRefreshedAt(data.refreshed_at);
            } else {
                setError(data.message ?? 'Error al actualizar.');
            }
        } catch {
            setError('No se pudo contactar con el servidor.');
        } finally {
            setLoading(false);
        }
    };

    const handleTestAlerts = async () => {
        setAlertLoading(true);
        setAlertMsg(null);
        try {
            const res = await fetch('/logistica/fleet/alert-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.sent) {
                setAlertMsg(`✓ ${data.notifications} alertas enviadas a ${data.recipients} dispositivo(s).`);
            } else {
                setAlertMsg(data.message ?? 'No se enviaron alertas.');
            }
        } catch {
            setAlertMsg('Error al enviar las alertas de prueba.');
        } finally {
            setAlertLoading(false);
        }
    };

    return (
        <>
            <Head title="Logística — Flota de Vehículos" />

            <div className="flex flex-col gap-6 p-6 font-sans pb-16">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Flota de Vehículos
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-zinc-400">
                            Rastreo GPS en tiempo real · Ubika Ecuador
                            {refreshedAt && (
                                <span className="ml-2 text-neutral-400">· Actualizado: {refreshedAt}</span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleTestAlerts}
                            disabled={alertLoading}
                            className="border-amber-200 text-amber-700 hover:bg-amber-50"
                        >
                            <Bell className="mr-2 h-4 w-4" />
                            {alertLoading ? 'Enviando...' : 'Probar Alertas GPS'}
                        </Button>
                        <Button
                            onClick={handleRefresh}
                            disabled={loading}
                            className="bg-red-600 font-semibold text-white hover:bg-red-700"
                        >
                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            {loading ? 'Actualizando...' : 'Actualizar Flota'}
                        </Button>
                    </div>
                </div>

                {/* Feedback banners */}
                {error && (
                    <div className="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <AlertTriangle className="h-4 w-4 shrink-0 text-red-600" />
                        {error}
                    </div>
                )}
                {alertMsg && (
                    <div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <Bell className="h-4 w-4 shrink-0 text-amber-600" />
                        {alertMsg}
                    </div>
                )}

                {/* Estado inicial — sin datos */}
                {vehicles.length === 0 && !loading && (
                    <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-neutral-200 bg-white py-20 dark:border-zinc-800 dark:bg-zinc-900">
                        <Truck className="h-12 w-12 text-neutral-300 dark:text-zinc-600" />
                        <div className="text-center">
                            <p className="font-medium text-neutral-600 dark:text-zinc-300">Sin datos de flota</p>
                            <p className="mt-1 text-sm text-neutral-400">Presiona "Actualizar Flota" para cargar la telemetría en tiempo real.</p>
                        </div>
                    </div>
                )}

                {vehicles.length > 0 && (
                    <>
                        {/* KPI Cards */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-neutral-500">Total Vehículos</CardTitle>
                                    <Truck className="h-4 w-4 text-neutral-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-semibold tracking-[-0.02em]">{total}</div>
                                    <span className="text-xs text-neutral-500">GPS activos con cobertura</span>
                                </CardContent>
                            </Card>

                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-neutral-500">En Movimiento</CardTitle>
                                    <Activity className="h-4 w-4 text-green-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-semibold tracking-[-0.02em] text-green-600">{moving}</div>
                                    <span className="text-xs text-neutral-500">Circulando ahora</span>
                                </CardContent>
                            </Card>

                            <Card className="border-amber-200 bg-amber-50/30 shadow-none dark:border-zinc-800 dark:bg-zinc-900">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-amber-700 dark:text-amber-400">Ralentí (Alerta)</CardTitle>
                                    <Zap className="h-4 w-4 text-amber-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-semibold tracking-[-0.02em] text-amber-600">{idle}</div>
                                    <span className="text-xs text-amber-600">Motor ON sin moverse</span>
                                </CardContent>
                            </Card>

                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-neutral-500">Detenidos</CardTitle>
                                    <Clock className="h-4 w-4 text-red-400" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-semibold tracking-[-0.02em]">{stopped}</div>
                                    <span className="text-xs text-neutral-500">Motor apagado</span>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Alertas Activas */}
                        {alerts.length > 0 && (
                            <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/40 p-5 dark:border-amber-900/40 dark:bg-amber-950/10">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="h-5 w-5 text-amber-600" />
                                    <h2 className="text-base font-semibold tracking-[-0.02em] text-amber-800 dark:text-amber-400">
                                        Alertas Operativas ({alerts.length})
                                    </h2>
                                </div>
                                <div className="flex flex-col gap-2">
                                    {alerts.map((a, i) => (
                                        <div key={i} className="flex items-start gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-neutral-200 dark:bg-zinc-900 dark:ring-zinc-800">
                                            <div className={`mt-0.5 h-2 w-2 shrink-0 rounded-full ${
                                                a.type === 'critical' ? 'bg-red-500 animate-ping' :
                                                a.type === 'warning'  ? 'bg-amber-400' : 'bg-blue-400'
                                            }`} />
                                            <div className="text-sm text-neutral-700 dark:text-zinc-300">
                                                <span className="font-semibold text-neutral-900 dark:text-zinc-100">{a.vehicleName}: </span>
                                                {a.message}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <Separator className="bg-neutral-100 dark:bg-zinc-800" />

                        {/* Tabla de Flota */}
                        <div>
                            <h2 className="mb-4 text-lg font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                Estado de la Flota
                            </h2>

                            {/* Mobile: cards — Desktop: table */}
                            <div className="block lg:hidden">
                                <div className="flex flex-col gap-3">
                                    {vehicles.map((v) => (
                                        <div key={v.id} className="rounded-xl border border-neutral-200 bg-white p-4 shadow-none dark:border-zinc-800 dark:bg-zinc-900">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-semibold text-sm text-neutral-900 dark:text-zinc-50">{v.name}</p>
                                                    <p className="text-xs text-neutral-500 mt-0.5">{v.total_distance_km.toLocaleString()} km totales</p>
                                                </div>
                                                <StatusBadge status={v.status} />
                                            </div>
                                            <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-zinc-400">
                                                <span className="flex items-center gap-1"><Activity className="h-3 w-3" /> {v.speed} km/h</span>
                                                <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> {v.stop_duration}</span>
                                                <span className="flex items-center gap-1"><BatteryFull className="h-3 w-3" /> {v.battery_vehicle}</span>
                                                <span className="flex items-center gap-1"><Zap className="h-3 w-3" /> Motor {v.engine_on ? 'ON' : 'OFF'}</span>
                                            </div>
                                            <div className="mt-3">
                                                <a
                                                    href={`https://maps.google.com/?q=${v.lat},${v.lng}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline"
                                                >
                                                    <MapPin className="h-3 w-3" /> Ver en mapa
                                                </a>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Desktop table */}
                            <div className="hidden lg:block overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Vehículo</th>
                                            <th className="px-4 py-3 font-medium">Estado</th>
                                            <th className="px-4 py-3 font-medium">Velocidad</th>
                                            <th className="px-4 py-3 font-medium">Tiempo Detenido</th>
                                            <th className="px-4 py-3 font-medium">Motor</th>
                                            <th className="px-4 py-3 font-medium">Batería Vehículo</th>
                                            <th className="px-4 py-3 font-medium">Odómetro</th>
                                            <th className="px-4 py-3 font-medium text-right">Ubicación</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-100 dark:divide-zinc-800/50">
                                        {vehicles.map((v) => (
                                            <tr key={v.id} className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20">
                                                <td className="px-4 py-3 font-medium text-neutral-900 dark:text-zinc-50">{v.name}</td>
                                                <td className="px-4 py-3"><StatusBadge status={v.status} /></td>
                                                <td className="px-4 py-3 font-semibold">
                                                    <span className={v.speed > 80 ? 'text-red-600' : 'text-neutral-700 dark:text-zinc-300'}>
                                                        {v.speed} km/h
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-neutral-500">{v.stop_duration}</td>
                                                <td className="px-4 py-3">
                                                    {v.engine_on
                                                        ? <span className="text-amber-600 font-medium">ON</span>
                                                        : <span className="text-neutral-400">OFF</span>
                                                    }
                                                </td>
                                                <td className="px-4 py-3 text-neutral-600 dark:text-zinc-400">{v.battery_vehicle}</td>
                                                <td className="px-4 py-3 text-neutral-500">{v.total_distance_km.toLocaleString()} km</td>
                                                <td className="px-4 py-3 text-right">
                                                    <a
                                                        href={`https://maps.google.com/?q=${v.lat},${v.lng}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-blue-600 hover:underline text-xs font-medium"
                                                    >
                                                        <MapPin className="h-3 w-3" /> Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

LogisticsIndex.layout = {
    breadcrumbs: [
        { title: 'Logística', href: '/logistica' },
        { title: 'Flota de Vehículos', href: '/logistica' },
    ],
};

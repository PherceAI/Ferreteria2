import { Head } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BatteryWarning,
    Bell,
    CalendarClock,
    CheckCircle2,
    Clock,
    Gauge,
    MapPin,
    RefreshCw,
    Route,
    ShieldAlert,
    Signal,
    TrendingUp,
    Truck,
    Wrench,
} from 'lucide-react';
import { lazy, Suspense, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

const FleetMapView = lazy(() => import('./fleet-map-view'));

interface Vehicle {
    id: number;
    external_id: string;
    unique_id: string;
    name: string;
    plate: string;
    status: 'moving' | 'idle' | 'stopped' | 'offline';
    online_raw: string;
    speed: number;
    course: number | null;
    stop_duration: string;
    stop_duration_sec: number;
    engine_on: boolean;
    blocked: boolean;
    battery_vehicle: string;
    vehicle_voltage: number | null;
    voltage_system: '12v' | '24v' | 'disconnected' | 'unknown';
    gps_battery_percent: number | null;
    satellites: number;
    lat: number;
    lng: number;
    total_distance_km: number;
    engine_hours_total: number | null;
    expiration_date: string | null;
    reported_at_label: string | null;
    data_age_sec: number | null;
    tail: Array<{ lat: number; lng: number }>;
    map_url: string;
    movement_threshold_kph: number;
}

interface FleetAlert {
    vehicleName: string;
    severity: 'critical' | 'warning' | 'info';
    category:
        | 'electrical'
        | 'gps'
        | 'security'
        | 'cost'
        | 'safety'
        | 'operations'
        | 'renewal';
    title: string;
    action: string;
}

interface FleetMaintenance {
    vehicle_name: string;
    current_km: number;
    next_service_km: number;
    remaining_km: number;
    status: 'due' | 'soon' | 'ok';
    action: string;
}

interface FleetRecommendation {
    title: string;
    body: string;
    type: 'critical' | 'warning' | 'info';
}

interface FleetHistoryItem {
    vehicle_name: string;
    km_7d: number;
    samples: number;
}

interface FleetHistory {
    activity_7d: FleetHistoryItem[];
    most_used: FleetHistoryItem | null;
    least_used: FleetHistoryItem | null;
}

interface FleetKpis {
    total: number;
    moving: number;
    idle: number;
    stopped: number;
    offline: number;
    critical_alerts: number;
    warning_alerts: number;
    electrical_risks: number;
    maintenance_due: number;
    avg_satellites: number;
    total_distance_km: number;
}

interface FleetResponse {
    success: boolean;
    message?: string;
    vehicles: Vehicle[];
    alerts: FleetAlert[];
    maintenance: FleetMaintenance[];
    recommendations: FleetRecommendation[];
    history: FleetHistory;
    kpis: FleetKpis;
    refreshed_at: string;
}

const severityStyles: Record<
    FleetAlert['severity'] | FleetRecommendation['type'],
    { dot: string; text: string; border: string; bg: string; label: string }
> = {
    critical: {
        dot: 'bg-red-500',
        text: 'text-red-700 dark:text-red-400',
        border: 'border-red-200 dark:border-red-900/50',
        bg: 'bg-red-50/60 dark:bg-red-950/10',
        label: 'Critico',
    },
    warning: {
        dot: 'bg-amber-400',
        text: 'text-amber-700 dark:text-amber-400',
        border: 'border-amber-200 dark:border-amber-900/50',
        bg: 'bg-amber-50/60 dark:bg-amber-950/10',
        label: 'Atencion',
    },
    info: {
        dot: 'bg-blue-400',
        text: 'text-blue-700 dark:text-blue-400',
        border: 'border-blue-200 dark:border-blue-900/50',
        bg: 'bg-blue-50/60 dark:bg-blue-950/10',
        label: 'Seguimiento',
    },
};

function formatNumber(value: number, digits = 0) {
    return new Intl.NumberFormat('es-EC', {
        maximumFractionDigits: digits,
        minimumFractionDigits: digits,
    }).format(value);
}

function formatAge(seconds: number | null) {
    if (seconds === null) {
        return 'Sin dato';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}min`;
    }

    return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}min`;
}

function statusConfig(status: Vehicle['status']) {
    return {
        moving: { label: 'En movimiento', dot: 'bg-green-500' },
        idle: { label: 'Ralenti', dot: 'bg-amber-400' },
        stopped: { label: 'Detenido', dot: 'bg-red-500' },
        offline: { label: 'Sin senal', dot: 'bg-neutral-400' },
    }[status];
}

function StatusLabel({ status }: { status: Vehicle['status'] }) {
    const cfg = statusConfig(status);

    return (
        <span className="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-zinc-400">
            <span className={`h-2 w-2 rounded-full ${cfg.dot}`} />
            {cfg.label}
        </span>
    );
}

function KpiCard({
    title,
    value,
    detail,
    icon: Icon,
    tone = 'neutral',
}: {
    title: string;
    value: string | number;
    detail: string;
    icon: typeof Truck;
    tone?: 'neutral' | 'green' | 'amber' | 'red';
}) {
    const toneClasses = {
        neutral: 'border-neutral-200 dark:border-zinc-800',
        green: 'border-green-200 bg-green-50/40 dark:border-green-900/40 dark:bg-green-950/10',
        amber: 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/10',
        red: 'border-red-200 bg-red-50/40 dark:border-red-900/40 dark:bg-red-950/10',
    };

    return (
        <Card className={`shadow-none ${toneClasses[tone]}`}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium tracking-[-0.02em] text-neutral-500 dark:text-zinc-400">
                    {title}
                </CardTitle>
                <Icon className="h-4 w-4 text-neutral-500 dark:text-zinc-400" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                    {value}
                </div>
                <span className="text-xs text-neutral-500 dark:text-zinc-400">
                    {detail}
                </span>
            </CardContent>
        </Card>
    );
}

function EmptyState({ loading }: { loading: boolean }) {
    return (
        <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-neutral-200 bg-white py-20 dark:border-zinc-800 dark:bg-zinc-900">
            <Truck className="h-12 w-12 text-neutral-300 dark:text-zinc-600" />
            <div className="text-center">
                <p className="font-medium tracking-[-0.02em] text-neutral-600 dark:text-zinc-300">
                    {loading ? 'Consultando GPS...' : 'Sin datos de flota'}
                </p>
                <p className="mt-1 text-sm text-neutral-400">
                    Presiona Actualizar Flota para cargar telemetria y analisis.
                </p>
            </div>
        </div>
    );
}

export default function LogisticsIndex() {
    const [vehicles, setVehicles] = useState<Vehicle[]>([]);
    const [alerts, setAlerts] = useState<FleetAlert[]>([]);
    const [maintenance, setMaintenance] = useState<FleetMaintenance[]>([]);
    const [recommendations, setRecommendations] = useState<
        FleetRecommendation[]
    >([]);
    const [history, setHistory] = useState<FleetHistory | null>(null);
    const [kpis, setKpis] = useState<FleetKpis | null>(null);
    const [refreshedAt, setRefreshedAt] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [alertLoading, setAlertLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [alertMsg, setAlertMsg] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<'summary' | 'map'>('summary');

    const topAlerts = useMemo(() => alerts.slice(0, 8), [alerts]);
    const maintenanceFocus = useMemo(
        () => maintenance.filter((item) => item.status !== 'ok').slice(0, 5),
        [maintenance],
    );

    const handleRefresh = async () => {
        setLoading(true);
        setError(null);

        try {
            const res = await fetch('/logistica/fleet/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = (await res.json()) as FleetResponse;

            if (data.success) {
                setVehicles(data.vehicles);
                setAlerts(data.alerts);
                setMaintenance(data.maintenance);
                setRecommendations(data.recommendations);
                setHistory(data.history);
                setKpis(data.kpis);
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
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();

            setAlertMsg(
                data.sent
                    ? `${data.notifications} alertas enviadas a ${data.recipients} dispositivo(s).`
                    : (data.message ?? 'No se enviaron alertas.'),
            );
        } catch {
            setAlertMsg('Error al enviar las alertas de prueba.');
        } finally {
            setAlertLoading(false);
        }
    };

    return (
        <>
            <Head title="Logistica - Flota de Vehiculos" />

            <div className="flex flex-col gap-6 p-6 pb-16 font-sans">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Flota de Vehiculos
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-zinc-400">
                            Centro de decision GPS · Ubika Ecuador
                            {refreshedAt && (
                                <span className="ml-2 text-neutral-400">
                                    · Actualizado: {refreshedAt}
                                </span>
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
                            <Bell className="h-4 w-4" />
                            {alertLoading
                                ? 'Enviando...'
                                : 'Probar Alertas GPS'}
                        </Button>
                        <Button
                            onClick={handleRefresh}
                            disabled={loading}
                            className="bg-red-600 font-semibold text-white hover:bg-red-700"
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                            />
                            {loading ? 'Analizando...' : 'Actualizar Flota'}
                        </Button>
                    </div>
                </div>

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

                {vehicles.length === 0 && <EmptyState loading={loading} />}

                <div className="flex w-full flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-2 sm:w-fit sm:flex-row dark:border-zinc-800 dark:bg-zinc-900">
                    <Button
                        variant={activeTab === 'summary' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setActiveTab('summary')}
                        className={
                            activeTab === 'summary'
                                ? 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-zinc-50 dark:text-zinc-950'
                                : 'text-neutral-500 hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-50'
                        }
                    >
                        <Activity className="h-4 w-4" />
                        Resumen ejecutivo
                    </Button>
                    <Button
                        variant={activeTab === 'map' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setActiveTab('map')}
                        className={
                            activeTab === 'map'
                                ? 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-zinc-50 dark:text-zinc-950'
                                : 'text-neutral-500 hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-50'
                        }
                    >
                        <MapPin className="h-4 w-4" />
                        Mapa GPS
                    </Button>
                </div>

                {vehicles.length > 0 && kpis && activeTab === 'summary' && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <KpiCard
                                title="Riesgos criticos"
                                value={kpis.critical_alerts}
                                detail="Atender antes de despacho"
                                icon={ShieldAlert}
                                tone={
                                    kpis.critical_alerts > 0 ? 'red' : 'neutral'
                                }
                            />
                            <KpiCard
                                title="Riesgo electrico"
                                value={kpis.electrical_risks}
                                detail="GPS/bateria a revisar"
                                icon={BatteryWarning}
                                tone={
                                    kpis.electrical_risks > 0
                                        ? 'amber'
                                        : 'neutral'
                                }
                            />
                            <KpiCard
                                title="En movimiento"
                                value={kpis.moving}
                                detail={`${kpis.total} unidades monitoreadas`}
                                icon={Activity}
                                tone="green"
                            />
                            <KpiCard
                                title="Mantenimiento"
                                value={kpis.maintenance_due}
                                detail="Cerca de hito preventivo"
                                icon={Wrench}
                                tone={
                                    kpis.maintenance_due > 0
                                        ? 'amber'
                                        : 'neutral'
                                }
                            />
                            <KpiCard
                                title="Kilometraje flota"
                                value={formatNumber(kpis.total_distance_km)}
                                detail={`Prom. satelites ${kpis.avg_satellites}`}
                                icon={Gauge}
                            />
                        </div>

                        <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                                        <TrendingUp className="h-4 w-4 text-neutral-500" />
                                        Acciones sugeridas para gerencia
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {recommendations.length === 0 ? (
                                        <div className="flex items-center gap-3 rounded-lg border border-neutral-200 p-4 text-sm text-neutral-500 dark:border-zinc-800">
                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            Sin acciones urgentes con la ultima
                                            telemetria.
                                        </div>
                                    ) : (
                                        recommendations.map((item) => {
                                            const style =
                                                severityStyles[item.type];

                                            return (
                                                <div
                                                    key={item.title}
                                                    className={`rounded-lg border p-4 ${style.border} ${style.bg}`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`h-2 w-2 rounded-full ${style.dot}`}
                                                        />
                                                        <p
                                                            className={`text-sm font-medium tracking-[-0.02em] ${style.text}`}
                                                        >
                                                            {item.title}
                                                        </p>
                                                    </div>
                                                    <p className="mt-2 text-sm text-neutral-600 dark:text-zinc-400">
                                                        {item.body}
                                                    </p>
                                                </div>
                                            );
                                        })
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                                        <Route className="h-4 w-4 text-neutral-500" />
                                        Uso reciente
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-lg border border-neutral-200 p-4 dark:border-zinc-800">
                                            <p className="text-xs text-neutral-500">
                                                Mas usada 7 dias
                                            </p>
                                            <p className="mt-2 truncate text-sm font-medium tracking-[-0.02em]">
                                                {history?.most_used
                                                    ?.vehicle_name ??
                                                    'Sin historial'}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {formatNumber(
                                                    history?.most_used?.km_7d ??
                                                        0,
                                                    1,
                                                )}{' '}
                                                km
                                            </p>
                                        </div>
                                        <div className="rounded-lg border border-neutral-200 p-4 dark:border-zinc-800">
                                            <p className="text-xs text-neutral-500">
                                                Menor uso 7 dias
                                            </p>
                                            <p className="mt-2 truncate text-sm font-medium tracking-[-0.02em]">
                                                {history?.least_used
                                                    ?.vehicle_name ??
                                                    'Sin historial'}
                                            </p>
                                            <p className="text-xs text-neutral-500">
                                                {formatNumber(
                                                    history?.least_used
                                                        ?.km_7d ?? 0,
                                                    1,
                                                )}{' '}
                                                km
                                            </p>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        {history?.activity_7d
                                            .slice(0, 5)
                                            .map((item) => (
                                                <div
                                                    key={item.vehicle_name}
                                                    className="flex items-center justify-between gap-3 text-sm"
                                                >
                                                    <span className="truncate text-neutral-600 dark:text-zinc-400">
                                                        {item.vehicle_name}
                                                    </span>
                                                    <span className="font-medium text-neutral-900 dark:text-zinc-50">
                                                        {formatNumber(
                                                            item.km_7d,
                                                            1,
                                                        )}{' '}
                                                        km
                                                    </span>
                                                </div>
                                            ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {topAlerts.length > 0 && (
                            <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/40 p-5 dark:border-amber-900/40 dark:bg-amber-950/10">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                                        <h2 className="text-base font-semibold tracking-[-0.02em] text-amber-800 dark:text-amber-400">
                                            Alertas operativas ({alerts.length})
                                        </h2>
                                    </div>
                                    <span className="text-xs text-amber-700 dark:text-amber-400">
                                        Priorizadas por impacto
                                    </span>
                                </div>
                                <div className="grid gap-2">
                                    {topAlerts.map((alert, index) => {
                                        const style =
                                            severityStyles[alert.severity];

                                        return (
                                            <div
                                                key={`${alert.vehicleName}-${alert.title}-${index}`}
                                                className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
                                            >
                                                <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className={`h-2 w-2 rounded-full ${style.dot}`}
                                                            />
                                                            <p className="text-sm font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                                                {
                                                                    alert.vehicleName
                                                                }
                                                            </p>
                                                            <span
                                                                className={`text-xs ${style.text}`}
                                                            >
                                                                {alert.title}
                                                            </span>
                                                        </div>
                                                        <p className="mt-2 text-sm text-neutral-600 dark:text-zinc-400">
                                                            {alert.action}
                                                        </p>
                                                    </div>
                                                    <span className="text-xs text-neutral-500">
                                                        {style.label}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <div className="grid gap-4 xl:grid-cols-[1fr_0.85fr]">
                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                                        <Truck className="h-4 w-4 text-neutral-500" />
                                        Estado de la flota
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="hidden overflow-x-auto rounded-xl border border-neutral-200 bg-white lg:block dark:border-zinc-800 dark:bg-zinc-900">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-neutral-50 text-neutral-500 dark:bg-zinc-800/50">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        Vehiculo
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Estado
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Electrico
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        GPS
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Parada
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Odometro
                                                    </th>
                                                    <th className="px-4 py-3 text-right font-medium">
                                                        Mapa
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-neutral-100 dark:divide-zinc-800/50">
                                                {vehicles.map((vehicle) => (
                                                    <tr
                                                        key={vehicle.id}
                                                        className="transition-colors hover:bg-neutral-50 dark:hover:bg-zinc-800/20"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                                                {vehicle.name}
                                                            </p>
                                                            <p className="text-xs text-neutral-500">
                                                                Reporte{' '}
                                                                {formatAge(
                                                                    vehicle.data_age_sec,
                                                                )}
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <StatusLabel
                                                                status={
                                                                    vehicle.status
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium text-neutral-900 dark:text-zinc-50">
                                                                {
                                                                    vehicle.battery_vehicle
                                                                }
                                                            </p>
                                                            <p className="text-xs text-neutral-500">
                                                                GPS{' '}
                                                                {vehicle.gps_battery_percent ??
                                                                    '-'}
                                                                %
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="inline-flex items-center gap-1 text-neutral-600 dark:text-zinc-400">
                                                                <Signal className="h-3 w-3" />
                                                                {
                                                                    vehicle.satellites
                                                                }
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-neutral-500">
                                                            {
                                                                vehicle.stop_duration
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-neutral-500">
                                                            {formatNumber(
                                                                vehicle.total_distance_km,
                                                                1,
                                                            )}{' '}
                                                            km
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <a
                                                                href={
                                                                    vehicle.map_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline"
                                                            >
                                                                <MapPin className="h-3 w-3" />
                                                                Ver
                                                            </a>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="grid gap-3 lg:hidden">
                                        {vehicles.map((vehicle) => (
                                            <div
                                                key={vehicle.id}
                                                className="rounded-xl border border-neutral-200 p-4 dark:border-zinc-800"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-semibold tracking-[-0.02em]">
                                                            {vehicle.name}
                                                        </p>
                                                        <p className="text-xs text-neutral-500">
                                                            {formatNumber(
                                                                vehicle.total_distance_km,
                                                                1,
                                                            )}{' '}
                                                            km
                                                        </p>
                                                    </div>
                                                    <StatusLabel
                                                        status={vehicle.status}
                                                    />
                                                </div>
                                                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-zinc-400">
                                                    <span className="flex items-center gap-1">
                                                        <Gauge className="h-3 w-3" />
                                                        {vehicle.speed} km/h
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        {vehicle.stop_duration}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <BatteryWarning className="h-3 w-3" />
                                                        {
                                                            vehicle.battery_vehicle
                                                        }
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Signal className="h-3 w-3" />
                                                        {vehicle.satellites}{' '}
                                                        sat.
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                                        <CalendarClock className="h-4 w-4 text-neutral-500" />
                                        Mantenimiento preventivo
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(maintenanceFocus.length > 0
                                        ? maintenanceFocus
                                        : maintenance.slice(0, 5)
                                    ).map((item) => (
                                        <div
                                            key={item.vehicle_name}
                                            className="rounded-lg border border-neutral-200 p-4 dark:border-zinc-800"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                                        {item.vehicle_name}
                                                    </p>
                                                    <p className="mt-1 text-xs text-neutral-500">
                                                        Actual{' '}
                                                        {formatNumber(
                                                            item.current_km,
                                                        )}{' '}
                                                        km · siguiente{' '}
                                                        {formatNumber(
                                                            item.next_service_km,
                                                        )}{' '}
                                                        km
                                                    </p>
                                                </div>
                                                <span
                                                    className={`text-xs ${
                                                        item.status === 'ok'
                                                            ? 'text-green-600'
                                                            : item.status ===
                                                                'soon'
                                                              ? 'text-amber-600'
                                                              : 'text-red-600'
                                                    }`}
                                                >
                                                    {item.status === 'ok'
                                                        ? 'OK'
                                                        : item.status === 'soon'
                                                          ? `${formatNumber(item.remaining_km)} km`
                                                          : 'Vencido'}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-xs text-neutral-500">
                                                {item.action}
                                            </p>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>

                        <Separator className="bg-neutral-100 dark:bg-zinc-800" />
                    </>
                )}

                {vehicles.length > 0 && activeTab === 'map' && (
                    <Suspense
                        fallback={
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-500 dark:border-zinc-800 dark:bg-zinc-900">
                                Cargando mapa GPS...
                            </div>
                        }
                    >
                        <FleetMapView
                            vehicles={vehicles}
                            alerts={alerts}
                            refreshedAt={refreshedAt}
                        />
                    </Suspense>
                )}
            </div>
        </>
    );
}

LogisticsIndex.layout = {
    breadcrumbs: [
        { title: 'Logistica', href: '/logistica' },
        { title: 'Flota de Vehiculos', href: '/logistica' },
    ],
};

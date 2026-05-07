import type {
    LatLngBoundsExpression,
    LatLngExpression,
    Map as LeafletMap,
    Marker,
} from 'leaflet';
import L from 'leaflet';
import {
    BatteryWarning,
    Bell,
    Clock,
    Gauge,
    MapPin,
    Navigation,
    Route,
    Satellite,
    Settings2,
    ShieldAlert,
    Truck,
} from 'lucide-react';
import 'leaflet/dist/leaflet.css';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppearance } from '@/hooks/use-appearance';

type Vehicle = {
    id: number;
    external_id: string;
    name: string;
    plate: string;
    status: 'moving' | 'idle' | 'stopped' | 'offline';
    speed: number;
    course: number | null;
    stop_duration: string;
    stop_duration_sec: number;
    engine_on: boolean;
    blocked: boolean;
    battery_vehicle: string;
    vehicle_voltage: number | null;
    gps_battery_percent: number | null;
    satellites: number;
    lat: number;
    lng: number;
    total_distance_km: number;
    reported_at_label: string | null;
    data_age_sec: number | null;
    tail: Array<{ lat: number; lng: number }>;
    map_url: string;
};

type FleetAlert = {
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
};

type AlertSettings = {
    scope: 'global' | 'vehicle';
    stoppedMinutes: number;
    idleMinutes: number;
    speedLimit: number;
    minVoltage12: number;
    minVoltage24: number;
    staleMinutes: number;
    gpsSignal: boolean;
    voltage: boolean;
    routeStop: boolean;
};

type Props = {
    vehicles: Vehicle[];
    alerts: FleetAlert[];
    refreshedAt: string | null;
};

const statusStyles: Record<
    Vehicle['status'],
    { label: string; dot: string; ring: string; text: string }
> = {
    moving: {
        label: 'En movimiento',
        dot: 'bg-green-500',
        ring: 'ring-green-500/20',
        text: 'text-green-700',
    },
    idle: {
        label: 'Ralenti',
        dot: 'bg-amber-400',
        ring: 'ring-amber-400/25',
        text: 'text-amber-700',
    },
    stopped: {
        label: 'Detenido',
        dot: 'bg-red-500',
        ring: 'ring-red-500/20',
        text: 'text-red-700',
    },
    offline: {
        label: 'Sin senal',
        dot: 'bg-neutral-400',
        ring: 'ring-neutral-400/25',
        text: 'text-neutral-500',
    },
};

const numberFormatter = new Intl.NumberFormat('es-EC', {
    maximumFractionDigits: 1,
});

const CHIMBORAZO_BOUNDS: LatLngBoundsExpression = [
    [-2.45, -79.35],
    [-0.85, -77.75],
];

const CHIMBORAZO_LAT_LNG_BOUNDS = L.latLngBounds(CHIMBORAZO_BOUNDS);

function formatNumber(value: number, digits = 1) {
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

function vehicleAlerts(vehicle: Vehicle, alerts: FleetAlert[]) {
    return alerts.filter((alert) => alert.vehicleName === vehicle.name);
}

function statusLabel(status: Vehicle['status']) {
    return statusStyles[status] ?? statusStyles.stopped;
}

export default function FleetMapView({ vehicles, alerts, refreshedAt }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(
        vehicles[0]?.id ?? null,
    );
    const [settings, setSettings] = useState<AlertSettings>({
        scope: 'global',
        stoppedMinutes: 60,
        idleMinutes: 10,
        speedLimit: 80,
        minVoltage12: 11.8,
        minVoltage24: 23.5,
        staleMinutes: 30,
        gpsSignal: true,
        voltage: true,
        routeStop: true,
    });
    const [savedMessage, setSavedMessage] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const selectedVehicle =
        vehicles.find((vehicle) => vehicle.id === selectedId) ?? vehicles[0];

    const selectedAlerts = useMemo(
        () =>
            selectedVehicle !== undefined
                ? vehicleAlerts(selectedVehicle, alerts)
                : [],
        [alerts, selectedVehicle],
    );

    const routePoints = useMemo(() => {
        if (selectedVehicle === undefined) {
            return [];
        }

        const points = [...selectedVehicle.tail];
        const current = {
            lat: selectedVehicle.lat,
            lng: selectedVehicle.lng,
        };

        if (
            points.length === 0 ||
            points[points.length - 1].lat !== current.lat ||
            points[points.length - 1].lng !== current.lng
        ) {
            points.push(current);
        }

        return points.filter((point) => point.lat !== 0 && point.lng !== 0);
    }, [selectedVehicle]);

    if (selectedVehicle === undefined) {
        return null;
    }

    const selectedStatus = statusLabel(selectedVehicle.status);

    const handleSave = async () => {
        setSaving(true);
        setSavedMessage(null);

        try {
            const response = await fetch('/logistica/fleet/alert-settings', {
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
                body: JSON.stringify({
                    scope: settings.scope,
                    vehicle_external_id: selectedVehicle.external_id,
                    vehicle_name: selectedVehicle.name,
                    stopped_minutes: settings.stoppedMinutes,
                    idle_minutes: settings.idleMinutes,
                    speed_limit_kph: settings.speedLimit,
                    min_voltage_12: settings.minVoltage12,
                    min_voltage_24: settings.minVoltage24,
                    stale_minutes: settings.staleMinutes,
                    gps_signal_enabled: settings.gpsSignal,
                    voltage_enabled: settings.voltage,
                    route_stop_enabled: settings.routeStop,
                }),
            });
            const data = (await response.json()) as {
                saved?: boolean;
                message?: string;
            };

            setSavedMessage(
                data.message ??
                    (data.saved
                        ? 'Configuracion guardada.'
                        : 'No se pudo guardar la configuracion.'),
            );
        } catch {
            setSavedMessage('No se pudo guardar la configuracion.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_340px]">
            <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                <CardHeader className="pb-3">
                    <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                        <Truck className="h-4 w-4 text-neutral-500" />
                        Unidades GPS
                    </CardTitle>
                    <p className="text-xs text-neutral-500">
                        {vehicles.length} vehiculos con posicion activa
                    </p>
                </CardHeader>
                <CardContent className="space-y-2">
                    {vehicles.map((vehicle) => {
                        const style = statusLabel(vehicle.status);
                        const isSelected = vehicle.id === selectedVehicle.id;

                        return (
                            <button
                                key={vehicle.id}
                                type="button"
                                onClick={() => setSelectedId(vehicle.id)}
                                className={`w-full rounded-lg border p-3 text-left transition-colors ${
                                    isSelected
                                        ? 'border-neutral-900 bg-neutral-50 dark:border-zinc-50 dark:bg-zinc-800/50'
                                        : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800/50'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                            {vehicle.name}
                                        </p>
                                        <p className="mt-1 text-xs text-neutral-500">
                                            Reporte{' '}
                                            {formatAge(vehicle.data_age_sec)}
                                        </p>
                                    </div>
                                    <span
                                        className={`mt-1 h-2 w-2 shrink-0 rounded-full ${style.dot}`}
                                    />
                                </div>
                                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-neutral-500">
                                    <span className="flex items-center gap-1">
                                        <Gauge className="h-3 w-3" />
                                        {formatNumber(vehicle.speed)} km/h
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <Satellite className="h-3 w-3" />
                                        {vehicle.satellites} sat.
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </CardContent>
            </Card>

            <Card className="flex min-h-[calc(100vh-220px)] flex-col overflow-hidden border-neutral-200 shadow-none dark:border-zinc-800">
                <div className="flex flex-col gap-3 border-b border-neutral-200 p-4 md:flex-row md:items-center md:justify-between dark:border-zinc-800">
                    <div>
                        <h2 className="text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                            Mapa operativo Chimborazo
                        </h2>
                        <p className="text-xs text-neutral-500">
                            Puntos por ubicacion GPS y ruta reciente del equipo.
                            {refreshedAt ? ` Actualizado ${refreshedAt}.` : ''}
                        </p>
                    </div>
                </div>

                <LiveFleetMap
                    vehicles={vehicles}
                    selectedVehicle={selectedVehicle}
                    routePoints={routePoints}
                    refreshedAt={refreshedAt}
                    onSelectVehicle={setSelectedId}
                />
            </Card>

            <div className="space-y-4">
                <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                            <MapPin className="h-4 w-4 text-neutral-500" />
                            Detalle del vehiculo
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <p className="text-lg font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                {selectedVehicle.name}
                            </p>
                            <p className="text-xs text-neutral-500">
                                {selectedVehicle.plate}
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <Metric
                                icon={Navigation}
                                label="Estado"
                                value={selectedStatus.label}
                                valueClassName={selectedStatus.text}
                            />
                            <Metric
                                icon={Gauge}
                                label="Velocidad"
                                value={`${formatNumber(selectedVehicle.speed)} km/h`}
                            />
                            <Metric
                                icon={BatteryWarning}
                                label="Bateria"
                                value={selectedVehicle.battery_vehicle}
                            />
                            <Metric
                                icon={Satellite}
                                label="GPS"
                                value={`${selectedVehicle.satellites} sat.`}
                            />
                            <Metric
                                icon={Clock}
                                label="Parada"
                                value={selectedVehicle.stop_duration}
                            />
                            <Metric
                                icon={Route}
                                label="Odometro"
                                value={`${numberFormatter.format(selectedVehicle.total_distance_km)} km`}
                            />
                        </div>

                        <div className="rounded-lg border border-neutral-200 p-3 text-xs text-neutral-500 dark:border-zinc-800">
                            <div className="flex items-center justify-between gap-3">
                                <span>Ultimo reporte</span>
                                <span className="font-medium text-neutral-900 dark:text-zinc-50">
                                    {selectedVehicle.reported_at_label ??
                                        'Sin dato'}
                                </span>
                            </div>
                            <div className="mt-2 flex items-center justify-between gap-3">
                                <span>Motor</span>
                                <span className="font-medium text-neutral-900 dark:text-zinc-50">
                                    {selectedVehicle.engine_on
                                        ? 'Encendido'
                                        : 'Apagado'}
                                </span>
                            </div>
                        </div>

                        <Button variant="outline" className="w-full" asChild>
                            <a
                                href={selectedVehicle.map_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MapPin className="h-4 w-4" />
                                Abrir ubicacion exacta
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                            <ShieldAlert className="h-4 w-4 text-neutral-500" />
                            Alertas del vehiculo
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {selectedAlerts.length === 0 ? (
                            <div className="rounded-lg border border-neutral-200 p-3 text-sm text-neutral-500 dark:border-zinc-800">
                                Sin alertas activas para esta unidad.
                            </div>
                        ) : (
                            selectedAlerts.slice(0, 3).map((alert) => (
                                <div
                                    key={`${alert.title}-${alert.category}`}
                                    className="rounded-lg border border-neutral-200 p-3 dark:border-zinc-800"
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`h-2 w-2 rounded-full ${
                                                alert.severity === 'critical'
                                                    ? 'bg-red-500'
                                                    : alert.severity ===
                                                        'warning'
                                                      ? 'bg-amber-400'
                                                      : 'bg-blue-400'
                                            }`}
                                        />
                                        <p className="text-sm font-medium tracking-[-0.02em]">
                                            {alert.title}
                                        </p>
                                    </div>
                                    <p className="mt-2 text-xs text-neutral-500">
                                        {alert.action}
                                    </p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card className="border-neutral-200 shadow-none dark:border-zinc-800">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base font-medium tracking-[-0.02em]">
                            <Settings2 className="h-4 w-4 text-neutral-500" />
                            Configurar alertas
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label>Aplicar a</Label>
                            <Select
                                value={settings.scope}
                                onValueChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        scope: value as AlertSettings['scope'],
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="global">
                                        Toda la flota
                                    </SelectItem>
                                    <SelectItem value="vehicle">
                                        Solo este vehiculo
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <NumberField
                                label="Parada min."
                                value={settings.stoppedMinutes}
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        stoppedMinutes: value,
                                    }))
                                }
                            />
                            <NumberField
                                label="Ralenti min."
                                value={settings.idleMinutes}
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        idleMinutes: value,
                                    }))
                                }
                            />
                            <NumberField
                                label="Velocidad km/h"
                                value={settings.speedLimit}
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        speedLimit: value,
                                    }))
                                }
                            />
                            <NumberField
                                label="Sin reporte min."
                                value={settings.staleMinutes}
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        staleMinutes: value,
                                    }))
                                }
                            />
                            <NumberField
                                label="Min. 12V"
                                value={settings.minVoltage12}
                                step="0.1"
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        minVoltage12: value,
                                    }))
                                }
                            />
                            <NumberField
                                label="Min. 24V"
                                value={settings.minVoltage24}
                                step="0.1"
                                onChange={(value) =>
                                    setSettings((current) => ({
                                        ...current,
                                        minVoltage24: value,
                                    }))
                                }
                            />
                        </div>

                        <div className="space-y-3 rounded-lg border border-neutral-200 p-3 dark:border-zinc-800">
                            <ToggleRow
                                checked={settings.voltage}
                                label="Voltaje critico"
                                onCheckedChange={(checked) =>
                                    setSettings((current) => ({
                                        ...current,
                                        voltage: checked,
                                    }))
                                }
                            />
                            <ToggleRow
                                checked={settings.gpsSignal}
                                label="GPS sin senal"
                                onCheckedChange={(checked) =>
                                    setSettings((current) => ({
                                        ...current,
                                        gpsSignal: checked,
                                    }))
                                }
                            />
                            <ToggleRow
                                checked={settings.routeStop}
                                label="Parada fuera de plan"
                                onCheckedChange={(checked) =>
                                    setSettings((current) => ({
                                        ...current,
                                        routeStop: checked,
                                    }))
                                }
                            />
                        </div>

                        {savedMessage && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-xs text-green-700 dark:border-green-900/50 dark:bg-green-950/10">
                                {savedMessage}
                            </div>
                        )}

                        <Button
                            className="w-full bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-zinc-50 dark:text-zinc-950"
                            onClick={handleSave}
                            disabled={saving}
                        >
                            <Bell className="h-4 w-4" />
                            {saving ? 'Guardando...' : 'Aplicar configuracion'}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    valueClassName = 'text-neutral-900 dark:text-zinc-50',
}: {
    icon: typeof Truck;
    label: string;
    value: string;
    valueClassName?: string;
}) {
    return (
        <div className="rounded-lg border border-neutral-200 p-3 dark:border-zinc-800">
            <div className="flex items-center gap-2 text-xs text-neutral-500">
                <Icon className="h-3 w-3" />
                {label}
            </div>
            <p
                className={`mt-2 truncate text-sm font-medium tracking-[-0.02em] ${valueClassName}`}
            >
                {value}
            </p>
        </div>
    );
}

function LiveFleetMap({
    vehicles,
    selectedVehicle,
    routePoints,
    refreshedAt,
    onSelectVehicle,
}: {
    vehicles: Vehicle[];
    selectedVehicle: Vehicle;
    routePoints: Array<{ lat: number; lng: number }>;
    refreshedAt: string | null;
    onSelectVehicle: (id: number) => void;
}) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<LeafletMap | null>(null);
    const markersRef = useRef<Marker[]>([]);
    const routeRef = useRef<L.Polyline | null>(null);
    const tileRef = useRef<L.TileLayer | null>(null);
    const hasFitFleetRef = useRef(false);
    const initialCenterRef = useRef<LatLngExpression>([
        selectedVehicle.lat || -1.6636,
        selectedVehicle.lng || -78.6546,
    ]);
    const { resolvedAppearance } = useAppearance();

    useEffect(() => {
        if (containerRef.current === null || mapRef.current !== null) {
            return;
        }

        const map = L.map(containerRef.current, {
            center: initialCenterRef.current,
            zoom: 13,
            minZoom: 9,
            maxZoom: 18,
            zoomControl: false,
            attributionControl: false,
            dragging: true,
            scrollWheelZoom: true,
            touchZoom: true,
            maxBounds: CHIMBORAZO_BOUNDS,
            maxBoundsViscosity: 0.7,
            preferCanvas: true,
        });

        L.control
            .zoom({
                position: 'bottomright',
            })
            .addTo(map);
        L.control
            .attribution({
                position: 'bottomleft',
                prefix: false,
            })
            .addAttribution('OpenStreetMap')
            .addTo(map);

        mapRef.current = map;
        window.setTimeout(() => {
            map.invalidateSize(false);
        }, 0);

        return () => {
            markersRef.current.forEach((marker) => marker.remove());
            markersRef.current = [];
            routeRef.current?.remove();
            tileRef.current?.remove();
            map.remove();
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        if (tileLayerExists(map)) {
            return;
        }

        tileRef.current = L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                crossOrigin: true,
                attribution: '&copy; OpenStreetMap',
            },
        ).addTo(map);
    }, []);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        map.getContainer().classList.toggle(
            'fleet-map-dark',
            resolvedAppearance === 'dark',
        );
    }, [resolvedAppearance]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        markersRef.current.forEach((marker) => marker.remove());
        markersRef.current = vehicles
            .filter((vehicle) => vehicle.lat !== 0 && vehicle.lng !== 0)
            .map((vehicle) => {
                const isSelected = vehicle.id === selectedVehicle.id;
                const marker = L.marker([vehicle.lat, vehicle.lng], {
                    icon: L.divIcon({
                        className: '',
                        html: vehicleMarkerHtml(vehicle, isSelected),
                        iconSize: [40, 40],
                        iconAnchor: [20, 20],
                    }),
                })
                    .on('click', () => onSelectVehicle(vehicle.id))
                    .addTo(map);

                return marker;
            });
    }, [onSelectVehicle, selectedVehicle.id, vehicles]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null || hasFitFleetRef.current) {
            return;
        }

        const fleetPoints = vehicles
            .filter(
                (vehicle) =>
                    vehicle.lat !== 0 &&
                    vehicle.lng !== 0 &&
                    CHIMBORAZO_LAT_LNG_BOUNDS.contains([
                        vehicle.lat,
                        vehicle.lng,
                    ]),
            )
            .map((vehicle) => [vehicle.lat, vehicle.lng] as LatLngExpression);

        if (fleetPoints.length === 0) {
            return;
        }

        hasFitFleetRef.current = true;
        map.fitBounds(L.latLngBounds(fleetPoints), {
            animate: false,
            maxZoom: 13,
            padding: [70, 70],
        });

        window.setTimeout(() => {
            map.invalidateSize(false);
        }, 250);
    }, [vehicles]);

    useEffect(() => {
        const map = mapRef.current;

        if (
            map === null ||
            selectedVehicle.lat === 0 ||
            selectedVehicle.lng === 0 ||
            !CHIMBORAZO_LAT_LNG_BOUNDS.contains([
                selectedVehicle.lat,
                selectedVehicle.lng,
            ])
        ) {
            return;
        }

        map.flyTo([selectedVehicle.lat, selectedVehicle.lng], 16, {
            animate: true,
            duration: 0.65,
        });
    }, [selectedVehicle.id, selectedVehicle.lat, selectedVehicle.lng]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        routeRef.current?.remove();
        routeRef.current = null;

        if (routePoints.length > 1) {
            routeRef.current = L.polyline(
                routePoints.map((point) => [point.lat, point.lng]),
                {
                    color: '#2563eb',
                    weight: 4,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round',
                },
            ).addTo(map);
        }

        map.invalidateSize(false);
    }, [routePoints]);

    useEffect(() => {
        const map = mapRef.current;

        if (map === null) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            map.invalidateSize(false);
        });
        const timeout = window.setTimeout(() => {
            map.invalidateSize(false);
        }, 300);

        return () => {
            window.cancelAnimationFrame(frame);
            window.clearTimeout(timeout);
        };
    }, [selectedVehicle.id, routePoints.length]);

    return (
        <div className="relative min-h-[720px] flex-1 overflow-hidden bg-neutral-100 dark:bg-zinc-950">
            <div
                ref={containerRef}
                className="absolute inset-0 [&_.leaflet-control-attribution]:text-[10px] [&_.leaflet-control-zoom]:border-neutral-200 [&_.leaflet-control-zoom]:shadow-sm [&_.leaflet-tile-pane]:transition-[filter]"
            />

            <div className="pointer-events-none absolute top-4 left-4 rounded-lg border border-neutral-200 bg-white/95 px-3 py-2 text-xs text-neutral-600 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 dark:text-zinc-400">
                <span className="font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                    Riobamba / Chimborazo
                </span>
                <span className="ml-2">Mapa real GPS</span>
            </div>

            {refreshedAt && (
                <div className="pointer-events-none absolute top-4 right-4 hidden rounded-lg border border-neutral-200 bg-white/95 px-3 py-2 text-xs text-neutral-500 shadow-sm backdrop-blur md:block dark:border-zinc-800 dark:bg-zinc-900/95">
                    {refreshedAt}
                </div>
            )}

            {routePoints.length > 1 && (
                <div className="pointer-events-none absolute bottom-4 left-4 rounded-lg border border-neutral-200 bg-white/95 px-3 py-2 text-xs text-neutral-600 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 dark:text-zinc-400">
                    Ruta reciente: {routePoints.length} puntos GPS
                </div>
            )}
        </div>
    );
}

function vehicleMarkerHtml(vehicle: Vehicle, selected: boolean) {
    const style = statusLabel(vehicle.status);
    const border = selected
        ? 'border-red-600 ring-red-500/30'
        : 'border-white ring-white/70';

    return `
        <div class="flex h-10 w-10 items-center justify-center rounded-full border bg-white shadow-sm ring-4 ${border}">
            <span class="h-3 w-3 rounded-full ${style.dot}"></span>
        </div>
    `;
}

function tileLayerExists(map: LeafletMap) {
    let exists = false;

    map.eachLayer((layer) => {
        if (layer instanceof L.TileLayer) {
            exists = true;
        }
    });

    return exists;
}

function NumberField({
    label,
    value,
    step = '1',
    onChange,
}: {
    label: string;
    value: number;
    step?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="space-y-2">
            <Label className="text-xs text-neutral-500">{label}</Label>
            <Input
                type="number"
                min={0}
                step={step}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
            />
        </div>
    );
}

function ToggleRow({
    checked,
    label,
    onCheckedChange,
}: {
    checked: boolean;
    label: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex items-center justify-between gap-3 text-sm">
            <span className="text-neutral-600 dark:text-zinc-400">{label}</span>
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
        </label>
    );
}

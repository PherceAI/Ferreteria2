import { router, usePage } from '@inertiajs/react';
import { Bell, MapPin, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type {
    BreadcrumbItem as BreadcrumbItemType,
    OperationalAlert,
    SharedData,
} from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, operationalAlerts = [] } = usePage<SharedData>().props;
    const [readAlertIds, setReadAlertIds] = useState<string[]>([]);

    const alerts = useMemo(
        () =>
            operationalAlerts.map((alert) => ({
                ...alert,
                isRead: alert.isRead || readAlertIds.includes(alert.id),
            })),
        [operationalAlerts, readAlertIds],
    );

    const unreadCount = alerts.filter((alert) => !alert.isRead).length;
    const branches = auth.branches ?? [];
    const activeBranch = auth.activeBranch;

    const markAsRead = (id: string) => {
        setReadAlertIds((current) =>
            current.includes(id) ? current : [...current, id],
        );
    };

    const handleBranchSelect = (value: string) => {
        const branchId = Number(value);

        if (!branchId || branchId === activeBranch?.id) {
            return;
        }

        router.put(
            '/branch/switch',
            { branch_id: branchId },
            { preserveScroll: true, preserveState: false },
        );
    };

    const handleAlertAction = (alert: OperationalAlert) => {
        markAsRead(alert.id);
        router.visit(alert.href);
    };

    const handleTestNotification = async () => {
        try {
            const csrfToken = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');

            const response = await fetch('/push/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken
                        ? decodeURIComponent(csrfToken)
                        : '',
                },
            });

            if (!response.ok) {
                throw new Error('Error al enviar test');
            }

            toast.success('Notificaciones de prueba enviadas.');
        } catch {
            toast.error('No se pudo iniciar la prueba de notificaciones');
        }
    };

    return (
        <header className="flex h-16 w-full shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-4">
                <div className="hidden items-center gap-2 md:flex">
                    <MapPin className="h-4 w-4 text-neutral-500" />
                    <Select
                        value={activeBranch ? String(activeBranch.id) : ''}
                        onValueChange={handleBranchSelect}
                    >
                        <SelectTrigger className="h-8 w-[220px] text-sm">
                            <SelectValue placeholder="Seleccionar sucursal" />
                        </SelectTrigger>
                        <SelectContent>
                            {branches.map((branch) => (
                                <SelectItem
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
                                    {branch.display_name ?? branch.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Sheet>
                    <SheetTrigger asChild>
                        <button className="relative flex h-8 w-8 items-center justify-center rounded-full hover:bg-neutral-100 dark:hover:bg-zinc-800">
                            <Bell className="h-5 w-5 text-neutral-600 dark:text-zinc-300" />
                            {unreadCount > 0 && (
                                <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-zinc-950">
                                    {unreadCount}
                                </span>
                            )}
                        </button>
                    </SheetTrigger>
                    <SheetContent className="w-full overflow-y-auto border-l border-neutral-200 bg-white p-6 shadow-none sm:max-w-md dark:border-zinc-800 dark:bg-zinc-900">
                        <SheetHeader className="mb-6 flex flex-row items-center justify-between">
                            <SheetTitle className="text-lg font-semibold text-neutral-900 dark:text-zinc-50">
                                Centro de Alertas
                            </SheetTitle>
                            <button
                                onClick={handleTestNotification}
                                className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-900/30"
                            >
                                <Send className="h-3.5 w-3.5" />
                                Probar
                            </button>
                        </SheetHeader>
                        <div className="flex flex-col gap-0">
                            <div className="mb-2 px-2 text-xs font-medium text-neutral-500 uppercase dark:text-zinc-400">
                                Alertas operativas
                            </div>
                            {alerts.length > 0 ? (
                                alerts.map((alert) => (
                                    <div
                                        key={alert.id}
                                        className={`group relative flex flex-col gap-2 border-b border-neutral-200 p-4 transition-colors last:border-0 dark:border-zinc-800 ${
                                            alert.isRead
                                                ? 'opacity-60'
                                                : 'bg-white hover:bg-neutral-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/50'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className={`flex h-2 w-2 shrink-0 rounded-full ${
                                                        alert.type ===
                                                        'critical'
                                                            ? 'bg-red-500'
                                                            : alert.type ===
                                                                'high'
                                                              ? 'bg-amber-500'
                                                              : alert.type ===
                                                                  'medium'
                                                                ? 'bg-yellow-400'
                                                                : 'bg-blue-500'
                                                    }`}
                                                />
                                                <h4 className="text-base font-medium text-neutral-900 dark:text-zinc-50">
                                                    {alert.title}
                                                </h4>
                                            </div>
                                            <div className="flex shrink-0 flex-col items-end gap-1">
                                                <span className="text-xs whitespace-nowrap text-neutral-400 dark:text-zinc-500">
                                                    {alert.timestamp}
                                                </span>
                                                {!alert.isRead && (
                                                    <span className="h-2 w-2 animate-pulse rounded-full bg-blue-500" />
                                                )}
                                            </div>
                                        </div>
                                        <p className="pr-6 text-sm leading-relaxed text-neutral-500 dark:text-zinc-400">
                                            {alert.message}
                                        </p>
                                        <div className="mt-1 flex items-center justify-between">
                                            <button
                                                onClick={() =>
                                                    handleAlertAction(alert)
                                                }
                                                className="text-sm font-medium text-neutral-900 transition-colors hover:text-neutral-600 dark:text-zinc-50 dark:hover:text-zinc-300"
                                            >
                                                {alert.actionText}
                                            </button>
                                            {!alert.isRead && (
                                                <button
                                                    onClick={() =>
                                                        markAsRead(alert.id)
                                                    }
                                                    className="text-xs font-medium text-neutral-400 opacity-0 transition-all group-hover:opacity-100 hover:text-neutral-900 dark:text-zinc-500 dark:hover:text-zinc-300"
                                                >
                                                    Marcar como leida
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="rounded-lg border border-neutral-200 p-4 text-sm text-neutral-500 dark:border-zinc-800">
                                    No hay alertas pendientes.
                                </div>
                            )}
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </header>
    );
}

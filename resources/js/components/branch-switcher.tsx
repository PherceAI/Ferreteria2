import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { Branch } from '@/types/auth';

export function BranchSwitcher() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const branches: Branch[] = auth.branches ?? [];
    const activeBranch: Branch | null = auth.activeBranch ?? null;
    const canViewAll = auth.canViewAllBranches ?? false;

    if (branches.length === 0 && !activeBranch) {
        return null;
    }

    const showDropdown = branches.length > 1 || canViewAll;

    const handleSelect = (branchId: number) => {
        if (branchId === activeBranch?.id) {
            return;
        }

        router.put(
            '/branch/switch',
            { branch_id: branchId },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const branchLabel = (branch: Branch) => branch.display_name ?? branch.name;
    const branchCode = (branch: Branch) =>
        branch.warehouse_code
            ? `Bodega ${branch.warehouse_code} · ${branch.code}`
            : branch.code;

    const activeLabel = activeBranch
        ? branchLabel(activeBranch)
        : 'Sin sucursal';
    const activeCode = activeBranch ? branchCode(activeBranch) : '';

    if (!showDropdown) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        className="cursor-default"
                        data-test="sidebar-branch-static"
                    >
                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-accent-foreground">
                            <Building2 className="size-4" />
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-medium">
                                {activeLabel}
                            </span>
                            {activeCode && (
                                <span className="truncate text-xs text-muted-foreground">
                                    {activeCode}
                                </span>
                            )}
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            data-test="sidebar-branch-switcher"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                <Building2 className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {activeLabel}
                                </span>
                                {activeCode && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {activeCode}
                                    </span>
                                )}
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Sucursales
                        </DropdownMenuLabel>
                        {branches.map((branch) => (
                            <DropdownMenuItem
                                key={branch.id}
                                onSelect={() => handleSelect(branch.id)}
                                className="gap-2"
                            >
                                <Building2 className="size-4 opacity-70" />
                                <div className="flex flex-1 flex-col">
                                    <span className="text-sm">
                                        {branchLabel(branch)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {branchCode(branch)}
                                    </span>
                                </div>
                                {branch.id === activeBranch?.id && (
                                    <Check className="ml-auto size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        {canViewAll && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    Acceso global
                                </DropdownMenuLabel>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

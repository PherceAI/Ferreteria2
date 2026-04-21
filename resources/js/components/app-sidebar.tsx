import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    LayoutGrid,
    Package,
    ShoppingCart,
    Truck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { BranchSwitcher } from '@/components/branch-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Contabilidad',
        href: '#',
        icon: BookOpen,
        disabled: true,
    },
    {
        title: 'Inventario',
        href: '#',
        icon: Package,
        disabled: false,
        items: [
            {
                title: 'Productos',
                href: '/inventory/products',
            },
            {
                title: 'Traspasos',
                href: '#', // TODO: route for transfers
            },
        ],
    },
    {
        title: 'Compras',
        href: '#',
        icon: ShoppingCart,
        disabled: true,
    },
    {
        title: 'Logística',
        href: '#',
        icon: Truck,
        disabled: true,
    },
    {
        title: 'Empleados',
        href: '#',
        icon: Users,
        disabled: true,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Módulos" />
            </SidebarContent>

            <SidebarFooter>
                <BranchSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

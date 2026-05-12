import { Link, usePage } from '@inertiajs/react';
import {
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, SharedData } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isWarehouseOnly =
        auth.roles.includes('Bodeguero') && !auth.canViewAllBranches;

    const mainNavItems: NavItem[] = isWarehouseOnly
        ? [
              {
                  title: 'Recepcion fisica',
                  href: '/compras/recepcion',
                  icon: ShoppingCart,
              },
          ]
        : [
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
                          title: 'Alertas',
                          href: '/inventory/alerts',
                      },
                      {
                          title: 'Traspasos',
                          href: '/inventory/transfers',
                      },
                  ],
              },
              {
                  title: 'Compras',
                  href: '#',
                  icon: ShoppingCart,
                  disabled: false,
                  items: [
                      {
                          title: 'Sugerencias',
                          href: '/compras',
                      },
                      {
                          title: 'Recepcion fisica',
                          href: '/compras/recepcion',
                      },
                  ],
              },
              {
                  title: 'Logística',
                  href: '#',
                  icon: Truck,
                  disabled: false,
                  items: [
                      {
                          title: 'Flota de Vehículos',
                          href: '/logistica',
                      },
                  ],
              },
          ];

    if (auth.canViewAllBranches) {
        mainNavItems.push({
            title: 'Administración',
            href: '#',
            icon: Users,
            disabled: false,
            items: [
                {
                    title: 'Empleados',
                    href: '/equipo/empleados',
                },
            ],
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    isWarehouseOnly
                                        ? '/compras/recepcion'
                                        : dashboard()
                                }
                                prefetch
                            >
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

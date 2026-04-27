import { FormEventHandler, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    Check,
    Shield,
    Users,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Role {
    id: number;
    name: string;
}

interface Branch {
    id: number;
    name: string;
    is_headquarters: boolean;
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    branches: Branch[];
}

interface Props {
    users: User[];
    branches: Branch[];
    roles: Role[];
}

export default function EmployeesIndex({ users, branches, roles }: Props) {
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [isBranchModalOpen, setIsBranchModalOpen] = useState(false);
    const [isRoleModalOpen, setIsRoleModalOpen] = useState(false);

    const branchForm = useForm({
        branch_ids: [] as number[],
    });

    const roleForm = useForm({
        role_names: [] as string[],
    });

    const openBranchModal = (user: User) => {
        setSelectedUser(user);
        branchForm.setData('branch_ids', user.branches.map((b) => b.id));
        setIsBranchModalOpen(true);
    };

    const openRoleModal = (user: User) => {
        setSelectedUser(user);
        roleForm.setData('role_names', user.roles.map((r) => r.name));
        setIsRoleModalOpen(true);
    };

    const handleBranchSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!selectedUser) return;

        branchForm.put(`/equipo/empleados/${selectedUser.id}/branches`, {
            onSuccess: () => setIsBranchModalOpen(false),
        });
    };

    const handleRoleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!selectedUser) return;

        roleForm.put(`/equipo/empleados/${selectedUser.id}/roles`, {
            onSuccess: () => setIsRoleModalOpen(false),
        });
    };

    const toggleBranch = (branchId: number) => {
        const current = branchForm.data.branch_ids;
        const updated = current.includes(branchId)
            ? current.filter((id) => id !== branchId)
            : [...current, branchId];
        branchForm.setData('branch_ids', updated);
    };

    const toggleRole = (roleName: string) => {
        const current = roleForm.data.role_names;
        const updated = current.includes(roleName)
            ? current.filter((name) => name !== roleName)
            : [...current, roleName];
        roleForm.setData('role_names', updated);
    };

    return (
        <>
            <Head title="Gestión de Empleados" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                        Equipo de Trabajo
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-zinc-400">
                        Gestiona el acceso a sucursales y roles operativos para todo el personal.
                    </p>
                </div>

                <div className="grid gap-6">
                    {users.map((user) => (
                        <Card key={user.id} className="border-neutral-200 shadow-none dark:border-zinc-800">
                            <CardContent className="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
                                {/* Información del Usuario */}
                                <div className="flex items-center gap-4 lg:w-[300px] shrink-0">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        <Users className="h-6 w-6" />
                                    </div>
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate text-base font-medium tracking-[-0.02em] text-neutral-900 dark:text-zinc-50">
                                            {user.name}
                                        </span>
                                        <span className="truncate text-sm text-neutral-500 dark:text-zinc-400">
                                            {user.email}
                                        </span>
                                    </div>
                                </div>

                                {/* Bloque Central: Sucursales y Roles */}
                                <div className="flex flex-1 flex-col gap-6 sm:flex-row sm:items-start lg:justify-end lg:pr-8">
                                    <div className="flex flex-1 flex-col gap-2 max-w-[350px]">
                                        <span className="text-xs font-medium text-neutral-500 dark:text-zinc-400">
                                            SUCURSALES ASIGNADAS
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            {user.branches.length > 0 ? (
                                                user.branches.map((branch) => (
                                                    <Badge
                                                        key={branch.id}
                                                        variant="secondary"
                                                        className="flex items-center gap-1 rounded-lg bg-neutral-100 font-normal hover:bg-neutral-100 dark:bg-zinc-800"
                                                    >
                                                        <Building2 className="h-3 w-3 text-neutral-500" />
                                                        {branch.name}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span className="flex items-center gap-2 text-sm text-amber-600">
                                                    <span className="h-2 w-2 rounded-full bg-amber-500" />
                                                    Sin sucursal
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2 sm:w-[180px] shrink-0">
                                        <span className="text-xs font-medium text-neutral-500 dark:text-zinc-400">
                                            ROLES OPERATIVOS
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            {user.roles.length > 0 ? (
                                                user.roles.map((role) => (
                                                    <Badge
                                                        key={role.id}
                                                        variant="outline"
                                                        className="flex items-center gap-1 rounded-lg font-normal border-neutral-200 dark:border-zinc-800"
                                                    >
                                                        <Shield className="h-3 w-3 text-neutral-500" />
                                                        {role.name}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span className="flex items-center gap-2 text-sm text-red-600">
                                                    <span className="h-2 w-2 rounded-full bg-red-500" />
                                                    Sin rol asignado
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Botones de Acción */}
                                <div className="flex items-center gap-2 shrink-0 lg:ml-auto">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openBranchModal(user)}
                                        className="rounded-lg border-neutral-200 dark:border-zinc-800"
                                    >
                                        <Building2 className="mr-2 h-4 w-4" />
                                        Sucursales
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openRoleModal(user)}
                                        className="rounded-lg border-neutral-200 dark:border-zinc-800"
                                    >
                                        <Shield className="mr-2 h-4 w-4" />
                                        Roles
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>

            {/* Modal de Sucursales */}
            <Dialog open={isBranchModalOpen} onOpenChange={setIsBranchModalOpen}>
                <DialogContent className="sm:max-w-[425px] rounded-xl border-neutral-200 shadow-sm dark:border-zinc-800">
                    <form onSubmit={handleBranchSubmit}>
                        <DialogHeader>
                            <DialogTitle className="text-lg font-medium tracking-[-0.02em]">
                                Asignar Sucursales
                            </DialogTitle>
                            <DialogDescription className="text-sm">
                                Selecciona a qué sucursales tendrá acceso {selectedUser?.name}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-6">
                            {branches.map((branch) => (
                                <div key={branch.id} className="flex items-center space-x-3">
                                    <Checkbox
                                        id={`branch-${branch.id}`}
                                        checked={branchForm.data.branch_ids.includes(branch.id)}
                                        onCheckedChange={() => toggleBranch(branch.id)}
                                    />
                                    <label
                                        htmlFor={`branch-${branch.id}`}
                                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        {branch.name}
                                        {branch.is_headquarters && (
                                            <Badge variant="secondary" className="ml-2 text-[10px]">
                                                MATRIZ
                                            </Badge>
                                        )}
                                    </label>
                                </div>
                            ))}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsBranchModalOpen(false)}
                                className="rounded-lg"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={branchForm.processing} className="rounded-lg">
                                {branchForm.processing ? 'Guardando...' : 'Guardar Cambios'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de Roles */}
            <Dialog open={isRoleModalOpen} onOpenChange={setIsRoleModalOpen}>
                <DialogContent className="sm:max-w-[425px] rounded-xl border-neutral-200 shadow-sm dark:border-zinc-800">
                    <form onSubmit={handleRoleSubmit}>
                        <DialogHeader>
                            <DialogTitle className="text-lg font-medium tracking-[-0.02em]">
                                Asignar Roles
                            </DialogTitle>
                            <DialogDescription className="text-sm">
                                Define los permisos y el rol operativo para {selectedUser?.name}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-6">
                            {roles.map((role) => (
                                <div key={role.id} className="flex items-center space-x-3">
                                    <Checkbox
                                        id={`role-${role.id}`}
                                        checked={roleForm.data.role_names.includes(role.name)}
                                        onCheckedChange={() => toggleRole(role.name)}
                                    />
                                    <label
                                        htmlFor={`role-${role.id}`}
                                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                        {role.name}
                                    </label>
                                </div>
                            ))}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsRoleModalOpen(false)}
                                className="rounded-lg"
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={roleForm.processing} className="rounded-lg">
                                {roleForm.processing ? 'Guardando...' : 'Guardar Cambios'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Equipo',
            href: '#',
        },
        {
            title: 'Empleados',
            href: '/equipo/empleados',
        },
    ],
};

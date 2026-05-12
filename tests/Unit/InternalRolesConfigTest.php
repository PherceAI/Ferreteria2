<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class InternalRolesConfigTest extends TestCase
{
    public function test_role_sets_do_not_have_duplicates(): void
    {
        foreach ($this->roleSets() as $key => $roles) {
            $this->assertSame(
                array_values(array_unique($roles)),
                $roles,
                "El set internal.{$key} contiene roles duplicados."
            );
        }
    }

    public function test_operational_role_sets_are_complete(): void
    {
        $expected = [
            'owner_roles' => ['Dueño', 'Owner'],
            'warehouse_roles' => ['Bodeguero'],
            'transfer_create_roles' => ['Dueño', 'Owner', 'Vendedor', 'Encargado Inventario', 'Encargada Compras'],
            'transfer_manage_roles' => ['Dueño', 'Owner', 'Encargada Compras', 'Encargado Inventario'],
            'receipt_view_roles' => ['Dueño', 'Owner', 'Contadora', 'Encargada Compras', 'Bodeguero'],
            'receipt_review_roles' => ['Dueño', 'Owner', 'Contadora', 'Encargada Compras'],
        ];

        foreach ($expected as $key => $roles) {
            $this->assertEqualsCanonicalizing($roles, config("internal.{$key}"));
        }
    }

    public function test_role_names_are_not_mojibake_encoded(): void
    {
        foreach ($this->roleSets() as $key => $roles) {
            foreach ($roles as $role) {
                $this->assertStringNotContainsString('Ã', $role, "El rol {$role} en internal.{$key} parece mojibakeado.");
                $this->assertStringNotContainsString('DueÃ', $role, "El rol {$role} en internal.{$key} no debe reemplazar Dueño.");
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function roleSets(): array
    {
        return collect(config('internal'))
            ->filter(fn (mixed $value): bool => is_array($value) && collect($value)->every(fn (mixed $item): bool => is_string($item)))
            ->all();
    }
}

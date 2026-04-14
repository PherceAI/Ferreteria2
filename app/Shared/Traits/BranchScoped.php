<?php

declare(strict_types=1);

namespace App\Shared\Traits;

/**
 * Placeholder — se implementa en el módulo Branches.
 *
 * Propósito: al montar este trait en un Model de `pherce_intel`, todas las queries
 * se filtran automáticamente por el `branch_id` activo en la sesión del usuario.
 * El rol Dueño bypasea este filtro para ver datos globales.
 *
 * Regla de negocio (ver Documentacion/business-rules.md):
 * toda data operacional está ligada a una sucursal y nunca se expone sin filtrar.
 */
trait BranchScoped
{
    //
}

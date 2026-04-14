<?php

declare(strict_types=1);

namespace App\Shared\Traits;

/**
 * Placeholder — se implementa en el módulo Audit.
 *
 * Propósito: marcar un Model para que todas sus operaciones write (create/update/delete)
 * se registren en `activity_log` vía Spatie Activity Log.
 *
 * Regla LOPDP (ver Documentacion/security-rules.md):
 * todas las escrituras en `pherce_intel` deben generar entradas de auditoría inmutables.
 * Sin excepciones.
 */
trait Auditable
{
    //
}

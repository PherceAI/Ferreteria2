<?php

declare(strict_types=1);

namespace App\Shared\Traits;

/**
 * Placeholder — se implementa en el módulo Audit / security layer.
 *
 * Propósito: encriptar at-rest (AES-256-CBC) los campos sensibles marcados en
 * `$encryptable` del Model. Descifra al acceder, cifra al guardar.
 *
 * Regla LOPDP (ver Documentacion/security-rules.md):
 * - Proveedores: RUC, dirección fiscal, datos bancarios, contacto directo
 * - Empleados: cédula, teléfono personal, dirección domiciliaria
 * - Clientes: RUC/cédula, dirección, contacto
 * Clave vive en `.env` (ENCRYPTABLE_KEY), nunca en código ni DB.
 */
trait Encryptable
{
    //
}

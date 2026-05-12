<?php

$ownerRoles = ['Dueño', 'Owner'];
$purchasingAdminRoles = [...$ownerRoles, 'Contadora', 'Encargada Compras'];
$inventoryControlRoles = ['Encargado Inventario'];
$warehouseRoles = ['Bodeguero'];

return [
    'allow_public_registration' => env('ALLOW_PUBLIC_REGISTRATION', false),

    'observability_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', (string) env('OBSERVABILITY_EMAILS', '')),
    ))),
    'owner_roles' => $ownerRoles,
    'observability_roles' => $ownerRoles,
    'team_admin_roles' => $ownerRoles,
    'warehouse_roles' => $warehouseRoles,
    'inventory_control_roles' => $inventoryControlRoles,
    'inventory_alert_roles' => [...$ownerRoles, 'Encargada Compras'],
    'purchasing_roles' => [...$purchasingAdminRoles, ...$inventoryControlRoles],
    'gmail_oauth_roles' => [...$ownerRoles, 'Encargada Compras'],
    'logistics_roles' => [...$ownerRoles, ...$inventoryControlRoles],
    'notification_test_roles' => $ownerRoles,
    'receipt_view_roles' => [...$purchasingAdminRoles, ...$warehouseRoles],
    'receipt_review_roles' => $purchasingAdminRoles,
    'transfer_create_roles' => [...$ownerRoles, 'Vendedor', ...$inventoryControlRoles, 'Encargada Compras'],
    'transfer_manage_roles' => [...$ownerRoles, 'Encargada Compras', ...$inventoryControlRoles],
];

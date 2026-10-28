/**
 * One permission for all of MyParcel. The admin routes check the privilege
 * myparcel:access. Through those routes the PDK reads orders and products and
 * writes product settings, so the role carries those rights too.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'myparcel',
    roles: {
        access: {
            privileges: [
                'myparcel:access',
                'order:read',
                'order_line_item:read',
                'order_address:read',
                'order_delivery:read',
                'order_customer:read',
                'currency:read',
                'state_machine_state:read',
                'product:read',
                'product:update',
            ],
            dependencies: [],
        },
    },
});

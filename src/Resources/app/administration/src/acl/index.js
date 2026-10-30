/**
 * One permission for all of MyParcel. The admin routes check the privilege
 * myparcel:access. The order settings list the order states, so the role can
 * read them.
 *
 * @TODO INT-1959: add the order and product rights together with the order and product repositories.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'myparcel',
    roles: {
        access: {
            privileges: [
                'myparcel:access',
                'state_machine_state:read',
            ],
            dependencies: [],
        },
    },
});

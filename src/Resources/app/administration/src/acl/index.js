/**
 * One permission for all of MyParcel. The admin routes check the privilege
 * myparcel:access.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'myparcel',
    roles: {
        access: {
            privileges: ['myparcel:access'],
            dependencies: [],
        },
    },
});

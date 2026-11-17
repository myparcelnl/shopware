Shopware.Component.register('myparcel-settings-index', () => import('./page/myparcel-settings-index'));

Shopware.Module.register('myparcel-settings', {
    type: 'plugin',
    name: 'myparcel-settings',
    title: 'myparcel.plugin_title_short',
    description: 'myparcel.shopware_admin_plugin_description',
    color: '#0f5c47',
    icon: 'regular-truck',

    routes: {
        index: {
            component: 'myparcel-settings-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'myparcel.access',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'myparcel.settings.index',
        icon: 'regular-truck',
        privilege: 'myparcel.access',
    },

    // Makes "Open extension" in the context menu of the extension list open this page.
    extensionEntryRoute: {
        extensionName: 'MyParcelShopware',
        route: 'myparcel.settings.index',
    },
});

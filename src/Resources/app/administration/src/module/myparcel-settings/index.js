Shopware.Component.register('myparcel-settings-index', () => import('./page/myparcel-settings-index'));

Shopware.Module.register('myparcel-settings', {
    type: 'plugin',
    name: 'myparcel-settings',
    title: 'myparcel-settings.general.title',
    description: 'myparcel-settings.general.description',
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

    // Makes the "Configure" button in the extension list open this page.
    extensionEntryRoute: {
        extensionName: 'MyParcelShopware',
        route: 'myparcel.settings.index',
    },
});

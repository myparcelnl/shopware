Shopware.Component.register('myparcel-settings-index', () => import('./page/myparcel-settings-index'));

// @TODO INT-2021: build the snippets in ./snippet from a Shopware tab of the PDK translations sheet.
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

    // Makes "Open extension" in the context menu of the extension list open this page.
    extensionEntryRoute: {
        extensionName: 'MyParcelShopware',
        route: 'myparcel.settings.index',
    },
});

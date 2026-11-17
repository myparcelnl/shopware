import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';

const loader = {load: vi.fn(), close: vi.fn(), session: null};
const createSessionLoader = vi.fn(() => loader);
const saveProductSettings = vi.fn();

vi.mock('../../../../service/session-loader', () => ({createSessionLoader: (...args) => createSessionLoader(...args)}));
vi.mock('../../../../service/pdk-loader', () => ({
    notifyShopware: () => 'notify',
    saveProductSettings: (...args) => saveProductSettings(...args),
}));

describe('myparcel-product-settings', () => {
    let component;
    let productLoading;
    let rulesLoading;

    beforeAll(async () => {
        globalThis.Shopware = {
            Mixin: {getByName: () => ({})},
            Store: {get: () => ({loading: {product: productLoading, rules: rulesLoading}})},
        };
        ({default: component} = await import('./index'));
    });

    const createSettings = (overrides = {}) => ({
        ...component.data(),
        $refs: {pdkRoot: document.createElement('div')},
        $route: {params: {id: 'AABB'}},
        $tc: (key) => key,
        createNotificationError: vi.fn(),
        get isProductLoading() {
            return component.computed.isProductLoading.call(this);
        },
        load() {
            return component.methods.load.call(this);
        },
        ...overrides,
    });

    beforeEach(() => {
        productLoading = false;
        rulesLoading = false;
        loader.load.mockReset();
        loader.close.mockReset();
        loader.session = null;
        createSessionLoader.mockClear();
        saveProductSettings.mockReset();
    });

    it('creates a loader on its root and loads the product of the route', () => {
        const settings = createSettings();
        loader.load.mockResolvedValue({});

        component.mounted.call(settings);

        const [{root, isActive, onNotification}] = createSessionLoader.mock.calls[0];

        expect(root).toBe(settings.$refs.pdkRoot);
        expect(isActive()).toBe(true);
        expect(onNotification).toBe('notify');
        expect(loader.load).toHaveBeenCalledWith('product', {productId: 'aabb'});
    });

    it('waits for the product page to finish loading', () => {
        productLoading = true;
        const settings = createSettings();

        component.mounted.call(settings);
        expect(loader.load).not.toHaveBeenCalled();

        loader.load.mockResolvedValue({});
        component.watch.isProductLoading.call(settings, false);
        expect(loader.load).toHaveBeenCalledOnce();
    });

    it('does not load again when another loading key of the product page changes', () => {
        const settings = createSettings({load: vi.fn()});

        rulesLoading = true;
        expect(settings.isProductLoading).toBe(false);
        rulesLoading = false;

        productLoading = true;
        expect(settings.isProductLoading).toBe(true);
    });

    it('keeps the changes of a failed save until the next save or another product', () => {
        const settings = createSettings({load: vi.fn(), keepChanges: true});

        component.watch.isProductLoading.call(settings, false);
        expect(settings.load).not.toHaveBeenCalled();

        component.watch['$route.params.id'].call(settings, 'ccdd');
        expect(settings.keepChanges).toBe(false);
        expect(settings.load).toHaveBeenCalledOnce();
    });

    it('loads for another product only while the product page is not loading', () => {
        const settings = createSettings({load: vi.fn()});
        const watcher = component.watch['$route.params.id'];

        watcher.call(settings, 'ccdd');
        productLoading = true;
        watcher.call(settings, 'eeff');
        watcher.call(settings, undefined);

        expect(settings.load).toHaveBeenCalledOnce();
    });

    it('stops loading when the product settings loaded, and not for a replaced load', async () => {
        const settings = createSettings({loader});

        loader.load.mockResolvedValueOnce(null);
        await settings.load();
        expect(settings.isLoading).toBe(true);

        loader.load.mockResolvedValueOnce({});
        await settings.load();
        expect(settings.isLoading).toBe(false);
    });

    it('shows a notification when the product settings cannot load', async () => {
        const settings = createSettings({loader});
        loader.load.mockRejectedValue(new Error('network'));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        await settings.load();

        expect(settings.createNotificationError).toHaveBeenCalledWith({message: 'myparcel.shopware_admin_product_load_error'});
        expect(settings.isLoading).toBe(false);
    });

    it('saves the changed settings for the product of the route', async () => {
        const settings = createSettings({loader});
        loader.session = {getChangedFormValues: vi.fn().mockResolvedValue({fitInMailbox: 2})};

        settings.keepChanges = true;

        await expect(component.methods.submit.call(settings)).resolves.toBe(true);

        expect(saveProductSettings).toHaveBeenCalledWith('aabb', {fitInMailbox: 2});
        expect(settings.keepChanges).toBe(false);
    });

    it('gives the notifications of a failed save to the pdk, without a second error', async () => {
        const settings = createSettings({loader});
        const notifications = [{category: 'action', variant: 'error', title: 'Not saved'}];
        loader.session = {
            getChangedFormValues: vi.fn().mockResolvedValue({fitInMailbox: 2}),
            addNotifications: vi.fn(),
        };
        saveProductSettings.mockRejectedValue({response: {data: {notifications}}});

        await expect(component.methods.submit.call(settings)).resolves.toBe(false);

        expect(loader.session.addNotifications).toHaveBeenCalledWith(notifications);
        expect(settings.keepChanges).toBe(true);
    });

    it('rejects a failed save that has no notifications', async () => {
        const settings = createSettings({loader});
        loader.session = {
            getChangedFormValues: vi.fn().mockResolvedValue({fitInMailbox: 2}),
            addNotifications: vi.fn(),
        };
        saveProductSettings.mockRejectedValue(new Error('network'));

        await expect(component.methods.submit.call(settings)).rejects.toThrow('network');
        expect(loader.session.addNotifications).not.toHaveBeenCalled();
        expect(settings.keepChanges).toBe(true);
    });

    it('rejects and keeps the changes when a changed form is not valid', async () => {
        const settings = createSettings({loader});
        loader.session = {getChangedFormValues: vi.fn().mockRejectedValue(new Error('not valid'))};

        await expect(component.methods.submit.call(settings)).rejects.toThrow('not valid');
        expect(settings.keepChanges).toBe(true);
    });

    it('knows when the settings have unsaved changes', () => {
        expect(component.methods.hasChanges.call(createSettings())).toBe(false);

        loader.session = {hasChanges: () => true};
        expect(component.methods.hasChanges.call(createSettings({loader}))).toBe(true);
    });

    it('saves nothing without changes or without a session', async () => {
        const settings = createSettings({loader});

        await expect(component.methods.submit.call(settings)).resolves.toBe(true);
        loader.session = {getChangedFormValues: vi.fn().mockResolvedValue(null)};
        await expect(component.methods.submit.call(settings)).resolves.toBe(true);

        expect(saveProductSettings).not.toHaveBeenCalled();
    });

    it('closes the session when the page closes', () => {
        const settings = createSettings({loader});

        component.beforeUnmount.call(settings);

        expect(settings.isActive).toBe(false);
        expect(loader.close).toHaveBeenCalled();
    });
});

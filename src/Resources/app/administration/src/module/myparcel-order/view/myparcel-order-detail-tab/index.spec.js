import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';

const loader = {load: vi.fn(), close: vi.fn(), session: null};
const createSessionLoader = vi.fn(() => loader);

vi.mock('../../../../service/session-loader', () => ({createSessionLoader: (...args) => createSessionLoader(...args)}));
vi.mock('../../../../service/pdk-loader', () => ({notifyShopware: () => 'notify'}));

describe('myparcel-order-detail-tab', () => {
    let component;

    beforeAll(async () => {
        globalThis.Shopware = {Mixin: {getByName: () => ({})}};
        ({default: component} = await import('./index'));
    });

    const createTab = () => ({
        ...component.data(),
        $refs: {pdkRoot: document.createElement('div')},
        $route: {params: {id: 'AABB'}},
        $tc: (key) => key,
        createNotificationError: vi.fn(),
        load() {
            return component.methods.load.call(this);
        },
    });

    beforeEach(() => {
        loader.load.mockReset();
        loader.close.mockReset();
        createSessionLoader.mockClear();
    });

    it('creates a loader on its root and loads the order of the route', async () => {
        const tab = createTab();
        loader.load.mockResolvedValue({});

        component.mounted.call(tab);

        const [{root, isActive, onNotification}] = createSessionLoader.mock.calls[0];

        expect(root).toBe(tab.$refs.pdkRoot);
        expect(isActive()).toBe(true);
        expect(onNotification).toBe('notify');
        expect(loader.load).toHaveBeenCalledWith('order', {orderId: 'aabb'});
    });

    it('stops loading when the order box loaded', async () => {
        const tab = {...createTab(), loader};
        loader.load.mockResolvedValue({});

        await tab.load();

        expect(tab.isLoading).toBe(false);
    });

    it('leaves the loading state to the load that replaced this one', async () => {
        const tab = {...createTab(), loader};
        loader.load.mockResolvedValue(null);

        await tab.load();

        expect(tab.isLoading).toBe(true);
        expect(tab.createNotificationError).not.toHaveBeenCalled();
    });

    it('shows a notification when the order box cannot load', async () => {
        const tab = {...createTab(), loader};
        loader.load.mockRejectedValue(new Error('network'));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        await tab.load();

        expect(tab.createNotificationError).toHaveBeenCalledWith({message: 'myparcel-order.detail.loadError'});
        expect(tab.isLoading).toBe(false);
    });

    it('loads again for another order, not for an empty id', () => {
        const tab = {load: vi.fn()};
        const watcher = component.watch['$route.params.id'];

        watcher.call(tab, 'ccdd');
        watcher.call(tab, undefined);

        expect(tab.load).toHaveBeenCalledOnce();
    });

    it('closes the session when the tab closes', () => {
        const tab = {...createTab(), loader};

        component.beforeUnmount.call(tab);

        expect(tab.isActive).toBe(false);
        expect(loader.close).toHaveBeenCalled();
    });
});

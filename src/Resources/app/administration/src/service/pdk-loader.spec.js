import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();
const mountPdk = vi.fn();
let token = 'one';

const response = {
    data: {
        html: '<span id="myparcel-pdk-boot"></span><div data-pdk-component="PluginSettings" id="pdk-1"></div>',
        scripts: ['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1'],
        styles: ['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1'],
    },
};

const loadModule = () => import('./pdk-loader');

describe('pdk-loader', () => {
    beforeEach(() => {
        vi.resetModules();
        get.mockReset();
        mountPdk.mockReset();
        token = 'one';

        globalThis.Shopware = {
            Application: {getContainer: () => ({httpClient: {get}})},
            Service: () => ({getToken: () => token}),
            Context: {api: {languageId: 'language-id'}},
        };
        window.MyParcelShopwarePdk = {mount: mountPdk};

        // happy-dom does not fetch scripts; report every asset as loaded.
        vi.spyOn(document.head, 'appendChild').mockImplementation((element) => {
            queueMicrotask(() => element.dispatchEvent(new Event('load')));

            return element;
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('inserts the view html and mounts the pdk app', async () => {
        const unmount = vi.fn();
        get.mockResolvedValue(response);
        mountPdk.mockResolvedValue(unmount);
        const {mountView} = await loadModule();
        const root = document.createElement('div');

        expect(await mountView('pluginSettings', root, () => true)).toBe(unmount);
        expect(root.innerHTML).toBe(response.data.html);
        expect(get).toHaveBeenCalledWith('_action/myparcel/view', expect.objectContaining({params: {view: 'pluginSettings'}}));
        expect(mountPdk).toHaveBeenCalledWith(root, expect.objectContaining({getRequestHeaders: expect.any(Function)}));
    });

    it('loads each asset only once, also on a second visit', async () => {
        get.mockResolvedValue(response);
        mountPdk.mockResolvedValue(() => {});
        const {mountView} = await loadModule();

        await mountView('pluginSettings', document.createElement('div'), () => true);
        await mountView('pluginSettings', document.createElement('div'), () => true);

        expect(document.head.appendChild).toHaveBeenCalledTimes(2);
        expect(mountPdk).toHaveBeenCalledTimes(2);
    });

    it('does not mount when the page closed while loading', async () => {
        get.mockResolvedValue(response);
        const {mountView} = await loadModule();
        const root = document.createElement('div');

        const unmount = await mountView('pluginSettings', root, () => false);

        expect(mountPdk).not.toHaveBeenCalled();
        expect(root.innerHTML).toBe('');
        expect(() => unmount()).not.toThrow();
    });

    it('fails when the view is empty', async () => {
        get.mockResolvedValue({data: {html: '', scripts: [], styles: []}});
        const {mountView} = await loadModule();

        await expect(mountView('pluginSettings', document.createElement('div'), () => true)).rejects.toThrow('is empty');
        expect(mountPdk).not.toHaveBeenCalled();
    });

    it('reads the token again for every request', async () => {
        const {getRequestHeaders} = await loadModule();

        expect(getRequestHeaders()).toEqual({Authorization: 'Bearer one', 'sw-language-id': 'language-id'});

        token = 'two';

        expect(getRequestHeaders().Authorization).toBe('Bearer two');
    });

    it('passes the context params to the view route', async () => {
        get.mockResolvedValue(response);
        const {fetchView} = await loadModule();

        const data = await fetchView('orderList', {orderIds: ['a', 'b']});

        expect(data).toBe(response.data);
        expect(get).toHaveBeenCalledWith(
            '_action/myparcel/view',
            expect.objectContaining({params: {view: 'orderList', orderIds: ['a', 'b']}}),
        );
    });

    it('opens a session in the root', async () => {
        const session = {render: vi.fn(), dispatchBulkAction: vi.fn(), unmount: vi.fn()};
        const createSession = vi.fn().mockResolvedValue(session);
        const onNotification = vi.fn();
        get.mockResolvedValue(response);
        window.MyParcelShopwarePdk = {mount: mountPdk, createSession};
        const {openSession} = await loadModule();
        const root = document.createElement('div');

        const result = await openSession('order', {orderId: 'o1'}, root, () => true, {onNotification});

        expect(result).toEqual({session, data: response.data});
        expect(root.innerHTML).toBe(response.data.html);
        expect(createSession).toHaveBeenCalledWith(
            root,
            expect.objectContaining({getRequestHeaders: expect.any(Function), onNotification}),
        );
    });

    it('does not open a session when the page closed', async () => {
        const createSession = vi.fn();
        get.mockResolvedValue(response);
        window.MyParcelShopwarePdk = {mount: mountPdk, createSession};
        const {openSession} = await loadModule();
        const root = document.createElement('div');

        const result = await openSession('order', {orderId: 'o1'}, root, () => false, {});

        expect(result).toEqual({session: null, data: response.data});
        expect(root.innerHTML).toBe('');
        expect(createSession).not.toHaveBeenCalled();
    });

    it('maps each variant to a shopware notification', async () => {
        const {notifyShopware} = await loadModule();
        const component = {
            createNotificationSuccess: vi.fn(),
            createNotificationError: vi.fn(),
            createNotificationWarning: vi.fn(),
            createNotificationInfo: vi.fn(),
        };
        const notify = notifyShopware(component);

        notify({variant: 'success', title: 'Done', message: 'm1'});
        notify({variant: 'error', message: 'm2'});
        notify({variant: 'warning', message: 'm3'});
        notify({variant: 'info', message: 'm4'});

        expect(component.createNotificationSuccess).toHaveBeenCalledWith({title: 'Done', message: 'm1'});
        expect(component.createNotificationError).toHaveBeenCalledWith({message: 'm2'});
        expect(component.createNotificationWarning).toHaveBeenCalledWith({message: 'm3'});
        expect(component.createNotificationInfo).toHaveBeenCalledWith({message: 'm4'});
    });
});

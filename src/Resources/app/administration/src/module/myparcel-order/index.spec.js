import {beforeAll, describe, expect, it, vi} from 'vitest';

vi.mock('./extension/sw-bulk-edit-modal', () => ({}));
vi.mock('./extension/sw-order-list', () => ({}));
vi.mock('./extension/sw-order-detail', () => ({}));

const register = vi.fn();
const registerModule = vi.fn();

describe('myparcel-order module', () => {
    let module;
    let registered;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {register}, Module: {register: registerModule}, Mixin: {getByName: () => ({})}};
        await import('./index');
        [[, module]] = registerModule.mock.calls;
        registered = [...register.mock.calls];
    });

    it('registers the order detail tab, which loads when it is used', async () => {
        const [, load] = registered.find(([name]) => name === 'myparcel-order-detail-tab');

        await expect(load()).resolves.toHaveProperty('default.template');
    });

    it('adds the MyParcel tab route to the order detail route', () => {
        const next = vi.fn();
        const route = {name: 'sw.order.detail', children: []};

        module.routeMiddleware(next, route);

        expect(route.children).toEqual([expect.objectContaining({
            name: 'sw.order.detail.myparcel',
            path: 'myparcel',
            component: 'myparcel-order-detail-tab',
            meta: expect.objectContaining({privilege: 'myparcel.access'}),
        })]);
        expect(next).toHaveBeenCalledWith(route);
    });

    it('leaves other routes as they are', () => {
        const next = vi.fn();
        const route = {name: 'sw.order.index', children: []};

        module.routeMiddleware(next, route);

        expect(route.children).toEqual([]);
        expect(next).toHaveBeenCalledWith(route);
    });
});

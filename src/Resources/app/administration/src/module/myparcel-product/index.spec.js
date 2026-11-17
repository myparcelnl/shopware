import {beforeAll, describe, expect, it, vi} from 'vitest';

vi.mock('./extension/sw-product-detail', () => ({}));

const register = vi.fn();
const registerModule = vi.fn();

describe('myparcel-product module', () => {
    let module;
    let registered;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {register}, Module: {register: registerModule}, Mixin: {getByName: () => ({})}};
        await import('./index');
        [[, module]] = registerModule.mock.calls;
        registered = [...register.mock.calls];
    });

    it('registers the product settings, which load when they are used, and the component of the tab route', async () => {
        const [, load] = registered.find(([name]) => name === 'myparcel-product-settings');

        await expect(load()).resolves.toHaveProperty('default.template');
        expect(registered).toContainEqual(['myparcel-product-detail-tab', {template: expect.any(String)}]);
    });

    it('adds the MyParcel tab route to the product detail route', () => {
        const next = vi.fn();
        const route = {name: 'sw.product.detail', children: []};

        module.routeMiddleware(next, route);

        expect(route.children).toEqual([expect.objectContaining({
            name: 'sw.product.detail.myparcel',
            path: 'myparcel',
            component: 'myparcel-product-detail-tab',
            meta: expect.objectContaining({privilege: 'myparcel.access'}),
        })]);
        expect(next).toHaveBeenCalledWith(route);
    });

    it('leaves other routes as they are', () => {
        const next = vi.fn();
        const route = {name: 'sw.product.index', children: []};

        module.routeMiddleware(next, route);

        expect(route.children).toEqual([]);
        expect(next).toHaveBeenCalledWith(route);
    });
});

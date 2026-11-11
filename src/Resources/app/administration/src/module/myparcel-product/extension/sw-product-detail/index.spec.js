import {beforeAll, describe, expect, it, vi} from 'vitest';

const override = vi.fn();

describe('sw-product-detail override', () => {
    let component;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {override}};
        await import('./index');
        [[, component]] = override.mock.calls;
    });

    const createPage = (customFields, settings = {submit: vi.fn(() => Promise.resolve())}) => {
        const origin = {customFields: customFields && {...customFields}};

        return {
            product: {customFields, getOrigin: () => origin},
            origin,
            $refs: {myparcelProductSettings: settings},
            $tc: (key) => key,
            createNotificationError: vi.fn(),
            $super: vi.fn(() => Promise.resolve('saved')),
        };
    };

    const saveProduct = (page) => component.methods.saveProduct.call(page);

    it('submits the MyParcel settings before the product save', async () => {
        const page = createPage({});

        await expect(saveProduct(page)).resolves.toBe('saved');

        const [submitOrder] = page.$refs.myparcelProductSettings.submit.mock.invocationCallOrder;
        const [saveOrder] = page.$super.mock.invocationCallOrder;

        expect(submitOrder).toBeLessThan(saveOrder);
        expect(page.$super).toHaveBeenCalledWith('saveProduct');
    });

    it('removes the MyParcel custom fields from the draft and the origin before the save', async () => {
        const page = createPage({myparcel_fit_in_mailbox: 2, myparcel_country_of_origin: 'NL', other_field: 'kept'});

        page.$super.mockImplementation(() => {
            expect(page.product.customFields).toEqual({other_field: 'kept'});
            expect(page.origin.customFields).toEqual({other_field: 'kept'});

            return Promise.resolve('saved');
        });

        await expect(saveProduct(page)).resolves.toBe('saved');
    });

    it('shows an error and still saves the product when the MyParcel save fails', async () => {
        const page = createPage({}, {submit: vi.fn(() => Promise.reject(new Error('pdk')))});

        vi.spyOn(console, 'error').mockImplementation(() => {});

        await expect(saveProduct(page)).resolves.toBe('saved');
        expect(page.createNotificationError).toHaveBeenCalledWith({message: 'myparcel.shopware_admin_product_save_error'});
    });

    it('saves a product when the MyParcel tab was not opened', async () => {
        const page = createPage(null);

        page.$refs = {};

        await expect(saveProduct(page)).resolves.toBe('saved');
    });

    it('opens the MyParcel settings on the first visit of the tab and keeps them', () => {
        const page = {myparcelSettingsOpened: false};
        const {handler} = component.watch['$route.name'];

        handler.call(page, 'sw.product.detail.base');
        expect(page.myparcelSettingsOpened).toBe(false);

        handler.call(page, 'sw.product.detail.myparcel');
        handler.call(page, 'sw.product.detail.base');
        expect(page.myparcelSettingsOpened).toBe(true);
    });
});

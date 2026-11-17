import {beforeAll, describe, expect, it, vi} from 'vitest';

const override = vi.fn();
const setLoading = vi.fn();

describe('sw-product-detail override', () => {
    let component;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {override}, Store: {get: () => ({setLoading})}};
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
        expect(page.myparcelSaveFailed).toBe(false);

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

    it('keeps the MyParcel value that the PDK saved when the merchant also changes another custom field', async () => {
        // The product as loaded: an old MyParcel value and another custom field.
        const page = createPage({myparcel_fit_in_mailbox: 2, other_field: 'old'});
        const saved = {};

        page.product.customFields.other_field = 'new';
        page.$refs.myparcelProductSettings.submit = vi.fn(() => {
            // The PDK writes the new MyParcel value directly.
            saved.myparcel_fit_in_mailbox = 5;

            return Promise.resolve(true);
        });
        page.$super.mockImplementation(() => {
            // Shopware writes each custom field key on its own: a key that is not in the save keeps its value.
            Object.assign(saved, page.product.customFields);

            return Promise.resolve('saved');
        });

        await saveProduct(page);

        expect(page.origin.customFields).toEqual({other_field: 'old'});
        expect(saved).toEqual({myparcel_fit_in_mailbox: 5, other_field: 'new'});
    });

    it('disables the Save button before the MyParcel save starts', async () => {
        const page = createPage({}, {submit: vi.fn(() => {
            expect(setLoading).toHaveBeenCalledWith(['product', true]);

            return Promise.resolve(true);
        })});

        setLoading.mockClear();
        await saveProduct(page);
        expect(page.$refs.myparcelProductSettings.submit).toHaveBeenCalled();
    });

    it('shows an error and still saves the product when the MyParcel save fails', async () => {
        const page = createPage({}, {submit: vi.fn(() => Promise.reject(new Error('pdk')))});

        vi.spyOn(console, 'error').mockImplementation(() => {});

        await expect(saveProduct(page)).resolves.toBe('saved');
        expect(page.myparcelSaveFailed).toBe(true);
        expect(page.createNotificationError).toHaveBeenCalledWith({message: 'myparcel.shopware_admin_product_save_error'});
    });

    it('knows a MyParcel save that the PDK rejected with its own notifications', async () => {
        const page = createPage({}, {submit: vi.fn(() => Promise.resolve(false))});

        await expect(saveProduct(page)).resolves.toBe('saved');
        expect(page.myparcelSaveFailed).toBe(true);
        expect(page.createNotificationError).not.toHaveBeenCalled();
    });

    it('shows no success for a Save without product changes whose MyParcel save failed', () => {
        const onSaveFinished = (page, response) => component.methods.onSaveFinished.call(page, response);
        const failed = {myparcelSaveFailed: true, $super: vi.fn()};
        const saved = {myparcelSaveFailed: false, $super: vi.fn()};

        onSaveFinished(failed, 'empty');
        expect(failed.$super).not.toHaveBeenCalled();

        onSaveFinished(failed, 'success');
        onSaveFinished(saved, 'empty');
        expect(failed.$super).toHaveBeenCalledWith('onSaveFinished', 'success');
        expect(saved.$super).toHaveBeenCalledWith('onSaveFinished', 'empty');
    });

    it('warns before a language change when the MyParcel settings have unsaved changes', () => {
        const abort = (product, settings) => component.methods.abortOnLanguageChange
            .call({$super: () => product, $refs: settings ? {myparcelProductSettings: settings} : {}});

        expect(abort(true)).toBe(true);
        expect(abort(false)).toBe(false);
        expect(abort(false, {hasChanges: () => true})).toBe(true);
        expect(abort(false, {hasChanges: () => false})).toBe(false);
    });

    it('saves a product when the MyParcel tab was not opened', async () => {
        const page = createPage(null);

        page.$refs = {};

        await expect(saveProduct(page)).resolves.toBe('saved');
    });

    it('opens the MyParcel settings on the first visit of the tab and keeps them', () => {
        const page = {myparcelSettingsOpened: false};
        const {handler} = component.watch.isMyparcelRoute;

        handler.call(page, false);
        expect(page.myparcelSettingsOpened).toBe(false);

        handler.call(page, true);
        handler.call(page, false);
        expect(page.myparcelSettingsOpened).toBe(true);
    });

    it('knows the route of the MyParcel tab and when it is open', () => {
        const page = {$route: {name: 'sw.product.detail.myparcel', params: {id: 'aabb'}}};

        expect(component.computed.myparcelTabRoute.call(page))
            .toEqual({name: 'sw.product.detail.myparcel', params: {id: 'aabb'}});
        expect(component.computed.isMyparcelRoute.call(page)).toBe(true);
        expect(component.computed.isMyparcelRoute.call({$route: {name: 'sw.product.detail.base'}})).toBe(false);
    });
});

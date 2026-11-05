import {beforeAll, describe, expect, it, vi} from 'vitest';

const override = vi.fn();

describe('sw-product-detail override', () => {
    let saveProduct;

    beforeAll(async () => {
        globalThis.Shopware = {Component: {override}};
        await import('./index');
        saveProduct = override.mock.calls[0][1].methods.saveProduct;
    });

    const createPage = (customFields) => {
        const origin = {customFields: customFields && {...customFields}};

        return {
            product: {customFields, getOrigin: () => origin},
            origin,
            $super: vi.fn(() => Promise.resolve()),
        };
    };

    it('removes the MyParcel custom fields from the draft and the origin before the save', async () => {
        const page = createPage({myparcel_fit_in_mailbox: 2, myparcel_country_of_origin: 'NL', other_field: 'kept'});

        page.$super.mockImplementation(() => {
            expect(page.product.customFields).toEqual({other_field: 'kept'});
            expect(page.origin.customFields).toEqual({other_field: 'kept'});

            return Promise.resolve('saved');
        });

        await expect(saveProduct.call(page)).resolves.toBe('saved');
        expect(page.$super).toHaveBeenCalledWith('saveProduct');
    });

    it('saves a product without custom fields', async () => {
        const page = createPage(null);

        await saveProduct.call(page);

        expect(page.$super).toHaveBeenCalledWith('saveProduct');
    });
});

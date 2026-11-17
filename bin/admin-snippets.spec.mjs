import {describe, expect, it} from 'vitest';
import {buildSnippet, isAdminKey} from './admin-snippets.mjs';

const english = {
    plugin_title_short: 'MyParcel',
    shopware_admin_permission_access: 'Use MyParcel',
    shopware_admin_order_load_error: 'Could not load the order.',
    order_export: 'Export',
};

describe('admin snippets', () => {
    it('takes the shopware_admin_ keys and plugin_title_short only', () => {
        expect(['plugin_title_short', 'shopware_admin_x', 'order_export', 'plugin_title'].filter(isAdminKey))
            .toEqual(['plugin_title_short', 'shopware_admin_x']);
    });

    it('puts the admin texts under myparcel with their sheet key, and the privilege on its fixed path', () => {
        expect(buildSnippet(english, english)).toEqual({
            myparcel: {
                plugin_title_short: 'MyParcel',
                shopware_admin_permission_access: 'Use MyParcel',
                shopware_admin_order_load_error: 'Could not load the order.',
            },
            'sw-privileges': {additional_permissions: {myparcel: {label: 'MyParcel', access: 'Use MyParcel'}}},
        });
    });

    it('gives a key that the language does not have the English text', () => {
        const dutch = {shopware_admin_permission_access: 'MyParcel gebruiken'};

        const snippet = buildSnippet(dutch, english);

        expect(snippet.myparcel.shopware_admin_permission_access).toBe('MyParcel gebruiken');
        expect(snippet.myparcel.shopware_admin_order_load_error).toBe('Could not load the order.');
        expect(snippet['sw-privileges'].additional_permissions.myparcel.access).toBe('MyParcel gebruiken');
    });

    it('stops when English does not have a key of the privilege', () => {
        const {shopware_admin_permission_access: _, ...withoutAccess} = english;

        expect(() => buildSnippet(withoutAccess, withoutAccess)).toThrow('shopware_admin_permission_access');
    });
});

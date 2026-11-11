/**
 * The admin texts of the plug-in: the keys in the tab app:shopware of the PDK
 * translations sheet that start with shopware_admin_, and plugin_title_short.
 */
export const isAdminKey = (key) => key.startsWith('shopware_admin_') || key === 'plugin_title_short';

// Shopware reads the label of a privilege from this fixed path, so it cannot use the sheet key.
const PRIVILEGE_KEYS = {
    label: 'plugin_title_short',
    access: 'shopware_admin_permission_access',
};

/**
 * Build the admin snippet of one language. The admin texts go under "myparcel"
 * with their sheet key, so a new text needs only a key in the sheet. A key that
 * the language does not have gets the English text.
 *
 * @param {Record<string, unknown>} translations the PDK translations of the language
 * @param {Record<string, unknown>} fallback the English PDK translations
 * @returns {object} the content of snippet/<language>.json
 * @throws {Error} when English does not have a key that the snippet needs
 */
export const buildSnippet = (translations, fallback) => {
    const keys = Object.keys(fallback).filter(isAdminKey);
    const missing = Object.values(PRIVILEGE_KEYS).filter((key) => typeof fallback[key] !== 'string');

    if (missing.length) {
        throw new Error(`Add these keys to the tab app:shopware of the translations sheet: ${missing.join(', ')}`);
    }

    const text = (key) => (typeof translations[key] === 'string' ? translations[key] : fallback[key]);

    return {
        myparcel: Object.fromEntries(keys.map((key) => [key, text(key)])),
        'sw-privileges': {
            additional_permissions: {
                myparcel: Object.fromEntries(Object.entries(PRIVILEGE_KEYS).map(([name, key]) => [name, text(key)])),
            },
        },
    };
};

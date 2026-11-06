#!/usr/bin/env node
/**
 * Writes the snippet files of the admin modules from the PDK translations in
 * config/pdk/translations. Run it after `pnpm translations:import`, which runs it
 * for you. The texts are in the tab app:shopware of the PDK translations sheet.
 *
 * A key that is missing in English stops the build. A key that is missing in
 * another language gets the English text.
 */
import {mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const translationsDir = join(root, 'config/pdk/translations');
const modulesDir = join(root, 'src/Resources/app/administration/src/module');

const LANGUAGES = ['en', 'nl', 'de', 'fr', 'it'];
const FALLBACK_LANGUAGE = 'en';

// For each admin module: the snippet path and the key in the translations sheet.
const SNIPPETS = {
    'myparcel-settings': {
        'myparcel-settings.general.title': 'plugin_title_short',
        'myparcel-settings.general.description': 'shopware_admin_plugin_description',
        'myparcel-settings.index.loadError': 'shopware_admin_settings_load_error',
        'sw-privileges.additional_permissions.myparcel.label': 'plugin_title_short',
        'sw-privileges.additional_permissions.myparcel.access': 'shopware_admin_permission_access',
    },
    'myparcel-order': {
        'myparcel-order.detail.tab': 'plugin_title_short',
        'myparcel-order.detail.loadError': 'shopware_admin_order_load_error',
        'myparcel-order.list.column': 'plugin_title_short',
        'myparcel-order.list.loadError': 'shopware_admin_order_list_load_error',
        'myparcel-order.list.actionError': 'shopware_admin_order_list_action_error',
    },
    'myparcel-product': {
        'myparcel-product.detail.tab': 'plugin_title_short',
        'myparcel-product.detail.loadError': 'shopware_admin_product_load_error',
        'myparcel-product.detail.saveError': 'shopware_admin_product_save_error',
    },
};

const readTranslations = (language) => {
    const file = join(translationsDir, `${language}.json`);

    try {
        return JSON.parse(readFileSync(file, 'utf8'));
    } catch (error) {
        throw new Error(`Cannot read ${file}. Run "pnpm translations:import" first.`, {cause: error});
    }
};

const setPath = (target, path, value) => {
    const parts = path.split('.');
    const last = parts.pop();
    const parent = parts.reduce((node, part) => (node[part] ??= {}), target);

    parent[last] = value;
};

const fallback = readTranslations(FALLBACK_LANGUAGE);
const missing = Object.values(SNIPPETS)
    .flatMap((snippets) => Object.values(snippets))
    .filter((key) => typeof fallback[key] !== 'string');

if (missing.length) {
    throw new Error(`Add these keys to the tab app:shopware of the translations sheet: ${[...new Set(missing)].join(', ')}`);
}

for (const language of LANGUAGES) {
    const translations = language === FALLBACK_LANGUAGE ? fallback : readTranslations(language);

    for (const [module, snippets] of Object.entries(SNIPPETS)) {
        const snippet = {};

        for (const [path, key] of Object.entries(snippets)) {
            setPath(snippet, path, translations[key] ?? fallback[key]);
        }

        const dir = join(modulesDir, module, 'snippet');

        mkdirSync(dir, {recursive: true});
        writeFileSync(join(dir, `${language}.json`), `${JSON.stringify(snippet, null, 4)}\n`);
    }
}

console.log(`Wrote the admin snippets for ${LANGUAGES.join(', ')}.`);

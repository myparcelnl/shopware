#!/usr/bin/env node
/**
 * Writes the snippet files of the admin module from the PDK translations in
 * config/pdk/translations. The texts are in the tab app:shopware of the PDK
 * translations sheet.
 *
 * `pnpm translations:import` fetches the translations and then runs this script.
 * Run it on its own, with `node bin/build-admin-snippets.mjs`, to write the
 * snippets again from the translations in config/pdk/translations.
 */
import {mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {buildSnippet} from './admin-snippets.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const translationsDir = join(root, 'config/pdk/translations');
const snippetDir = join(root, 'src/Resources/app/administration/src/module/myparcel-settings/snippet');

const LANGUAGES = ['en', 'nl', 'de', 'fr', 'it'];

const readTranslations = (language) => {
    const file = join(translationsDir, `${language}.json`);

    try {
        return JSON.parse(readFileSync(file, 'utf8'));
    } catch (error) {
        throw new Error(`Cannot read ${file}. Run "pnpm translations:import" first.`, {cause: error});
    }
};

const fallback = readTranslations('en');

mkdirSync(snippetDir, {recursive: true});

for (const language of LANGUAGES) {
    const translations = language === 'en' ? fallback : readTranslations(language);

    writeFileSync(
        join(snippetDir, `${language}.json`),
        `${JSON.stringify(buildSnippet(translations, fallback), null, 4)}\n`,
    );
}

console.log(`Wrote the admin snippets for ${LANGUAGES.join(', ')}.`);

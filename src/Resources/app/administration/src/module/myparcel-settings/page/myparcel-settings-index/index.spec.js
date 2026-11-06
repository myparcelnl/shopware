import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';

const loader = {load: vi.fn(), close: vi.fn()};
const createSessionLoader = vi.fn(() => loader);

vi.mock('../../../../service/session-loader', () => ({createSessionLoader: (...args) => createSessionLoader(...args)}));

describe('myparcel-settings-index', () => {
    let component;

    beforeAll(async () => {
        globalThis.Shopware = {Mixin: {getByName: () => ({})}};
        ({default: component} = await import('./index'));
    });

    const createPage = () => ({
        ...component.data(),
        $refs: {pdkRoot: document.createElement('div')},
        $tc: (key) => key,
        createNotificationError: vi.fn(),
    });

    beforeEach(() => {
        loader.load.mockReset();
        loader.close.mockReset();
        createSessionLoader.mockClear();
    });

    it('loads the plugin settings in its root, with the notifications in the pdk', async () => {
        const page = createPage();
        loader.load.mockResolvedValue({});

        await component.mounted.call(page);

        const [options] = createSessionLoader.mock.calls[0];

        expect(options.root).toBe(page.$refs.pdkRoot);
        expect(options.isActive()).toBe(true);
        expect(options).not.toHaveProperty('onNotification');
        expect(loader.load).toHaveBeenCalledWith('pluginSettings', {});
        expect(page.isLoading).toBe(false);
    });

    it('shows a notification when the settings cannot load', async () => {
        const page = createPage();
        loader.load.mockRejectedValue(new Error('network'));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        await component.mounted.call(page);

        expect(page.createNotificationError).toHaveBeenCalledWith({message: 'myparcel-settings.index.loadError'});
        expect(page.isLoading).toBe(false);
    });

    it('does nothing more when the page closed while the view loaded', async () => {
        const page = createPage();
        loader.load.mockResolvedValue(null);

        await component.mounted.call(page);

        expect(page.createNotificationError).not.toHaveBeenCalled();
    });

    it('closes the session when the page closes', () => {
        const page = {...createPage(), loader};

        component.beforeUnmount.call(page);

        expect(page.isActive).toBe(false);
        expect(loader.close).toHaveBeenCalled();
    });
});

import {afterEach, describe, expect, it, vi} from 'vitest';
import {createPdkAdmin} from '@myparcel-dev/pdk-admin';
import {mount} from './mount';

vi.mock('@myparcel-dev/pdk-admin', () => ({createPdkAdmin: vi.fn()}));
vi.mock('./config', () => ({createConfig: vi.fn((getRequestHeaders) => ({getRequestHeaders}))}));

const createRoot = (): HTMLElement => {
  const root = document.createElement('div');

  root.innerHTML = `
    <span id="myparcel-pdk-boot" data-pdk-context="{}"></span>
    <div data-pdk-component="PluginSettings" data-pdk-context="{}" id="pdk-plugin-settings-1"></div>
    <div data-pdk-component="Notifications" data-pdk-context="{}" id="pdk-notifications-2"></div>`;
  document.body.append(root);

  return root;
};

describe('mount', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.mocked(createPdkAdmin).mockReset();
  });

  it('renders every component in the root and unmounts them all', async () => {
    const apps = [{unmount: vi.fn()}, {unmount: vi.fn()}];
    const render = vi.fn().mockResolvedValueOnce(apps[0]).mockResolvedValueOnce(apps[1]);
    vi.mocked(createPdkAdmin).mockReturnValue({render} as never);
    const getRequestHeaders = () => ({Authorization: 'Bearer x'});

    const unmount = await mount(createRoot(), {getRequestHeaders});

    expect(createPdkAdmin).toHaveBeenCalledWith({getRequestHeaders});
    expect(render.mock.calls).toEqual([
      ['PluginSettings', '#pdk-plugin-settings-1'],
      ['Notifications', '#pdk-notifications-2'],
    ]);

    unmount();

    expect(apps[0].unmount).toHaveBeenCalledOnce();
    expect(apps[1].unmount).toHaveBeenCalledOnce();
  });

  it('skips an app that did not mount', async () => {
    vi.mocked(createPdkAdmin).mockReturnValue({render: vi.fn().mockResolvedValue(undefined)} as never);

    const unmount = await mount(createRoot(), {getRequestHeaders: () => ({})});

    expect(() => unmount()).not.toThrow();
  });

  it('mounts again after an unmount, for a second visit to the page', async () => {
    const render = vi.fn().mockResolvedValue({unmount: vi.fn()});
    vi.mocked(createPdkAdmin).mockReturnValue({render} as never);

    (await mount(createRoot(), {getRequestHeaders: () => ({})}))();
    document.body.innerHTML = '';
    await mount(createRoot(), {getRequestHeaders: () => ({})});

    expect(createPdkAdmin).toHaveBeenCalledTimes(2);
    expect(render).toHaveBeenCalledTimes(4);
  });

  it('fails when the pdk admin does not start', async () => {
    vi.mocked(createPdkAdmin).mockReturnValue(undefined);

    await expect(mount(createRoot(), {getRequestHeaders: () => ({})})).rejects.toThrow('did not start');
  });
});

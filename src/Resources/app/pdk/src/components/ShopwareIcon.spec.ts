import {afterEach, describe, expect, it} from 'vitest';
import {type App, createApp} from 'vue';
import {AdminIcon} from '@myparcel-dev/pdk-admin';
import ShopwareIcon from './ShopwareIcon.vue';

let app: App | undefined;

// The PDK app registers v-test. The test app has no use for it.
const render = (icon: string): HTMLElement => {
  const container = document.createElement('div');

  app = createApp(ShopwareIcon, {icon});
  app.directive('test', {});
  app.mount(container);

  return container;
};

describe('ShopwareIcon', () => {
  afterEach(() => {
    app?.unmount();
    app = undefined;
  });

  it.each(Object.values(AdminIcon))('renders an svg for the icon %s', (icon) => {
    const element = render(icon).querySelector('.mypa-icon');

    expect(element?.getAttribute('role')).toBe('none');
    expect(element?.querySelector('svg path')?.getAttribute('d')).toBeTruthy();
  });

  it.each(['package', 'STANDARD_DELIVERY', 'constructor', 'toString', '__proto__'])('renders nothing for the name %s', (icon) => {
    expect(render(icon).querySelector('.mypa-icon')).toBeNull();
  });

  it('renders the svg file of the icon kit as it is', () => {
    const svg = render(AdminIcon.Add).querySelector('svg');

    expect(svg?.getAttribute('viewBox')).toBe('0 0 20 20');
    expect(svg?.querySelector('path')?.getAttribute('d')).toMatch(/^M9 9V1C9/);
  });

  it('lets only the spinner spin', () => {
    expect(render(AdminIcon.Spinner).querySelector('.mypa-icon')?.classList.contains('mypa-icon--spin')).toBe(true);
    expect(render(AdminIcon.Save).querySelector('.mypa-icon')?.classList.contains('mypa-icon--spin')).toBe(false);
  });
});

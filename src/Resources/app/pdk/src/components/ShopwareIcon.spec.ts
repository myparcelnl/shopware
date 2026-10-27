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
    const svg = render(icon).querySelector('svg');

    expect(svg).not.toBeNull();
    expect(svg?.getAttribute('role')).toBe('none');
    expect(svg?.querySelector('path')?.getAttribute('d')).toBeTruthy();
  });

  it.each(['package', 'STANDARD_DELIVERY'])('renders nothing for the name %s', (icon) => {
    expect(render(icon).querySelector('svg')).toBeNull();
  });

  it('lets only the spinner spin', () => {
    expect(render(AdminIcon.Spinner).querySelector('svg')?.classList.contains('mypa-icon--spin')).toBe(true);
    expect(render(AdminIcon.Save).querySelector('svg')?.classList.contains('mypa-icon--spin')).toBe(false);
  });
});

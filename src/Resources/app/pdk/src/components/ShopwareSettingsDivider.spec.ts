import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareSettingsDivider from './ShopwareSettingsDivider.vue';

// Only the key "known" has a translation.
vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {SettingsDivider: 'SettingsDivider'},
  useLanguage: () => ({translate: (key: string) => `[${key}]`, has: (key: string) => key === 'known'}),
}));

let app: App | undefined;
let container: HTMLElement;

const mount = (props: Record<string, unknown>) => {
  app = createApp({render: () => h(ShopwareSettingsDivider, {element: {props}})});
  app.directive('test', {});
  app.component(
    'PdkHeading',
    defineComponent({props: ['level'], setup: (p, {slots}) => () => h('b', {'data-level': p.level}, slots.default?.())}),
  );
  app.mount(container);
};

describe('ShopwareSettingsDivider', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the heading with the level and the content', () => {
    mount({heading: 'head', level: 3, content: 'known'});

    const heading = container.querySelector('.mypa-settings-divider b');

    expect(heading?.getAttribute('data-level')).toBe('3');
    expect(heading?.textContent).toBe('[head]');
    expect(container.querySelector('.mypa-settings-divider__content')?.textContent).toBe('[known]');
  });

  it('shows no content without a translation', () => {
    mount({heading: 'head', content: 'unknown'});

    expect(container.querySelector('p')).toBeNull();
  });

  it('shows no heading without a heading', () => {
    mount({content: 'known'});

    expect(container.querySelector('b')).toBeNull();
  });
});

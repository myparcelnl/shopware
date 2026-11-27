import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareLoader from './ShopwareLoader.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {Loader: 'Loader'},
  Size: {ExtraSmall: 'xs', Small: 'sm', Medium: 'md', Large: 'lg'},
  useLanguage: () => ({translate: (key: string) => `[${key}]`}),
}));

let app: App | undefined;
let container: HTMLElement;

const mount = (props: Record<string, unknown> = {}) => {
  app = createApp({render: () => h(ShopwareLoader, props)});
  app.directive('test', {});
  app.mount(container);
};

describe('ShopwareLoader', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the tag with the spinner and the loading text', () => {
    mount({tag: 'span'});

    const loader = container.querySelector('span.mypa-loader');

    expect(loader?.querySelector('.mypa-loader__spinner')).not.toBeNull();
    expect(loader?.querySelector('.mypa-loader__text')?.textContent).toBe('[loading]');
  });

  it('is a div by default', () => {
    mount();

    expect(container.querySelector('div.mypa-loader')).not.toBeNull();
  });

  it.each([
    ['xs', true],
    ['sm', true],
    ['md', false],
    ['lg', false],
  ])('size %s gives the small loader: %s', (size, small) => {
    mount({size});

    expect(container.querySelector('.mypa-loader')?.classList.contains('mypa-loader--sm')).toBe(small);
  });
});

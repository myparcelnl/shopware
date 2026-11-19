import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareBox from './ShopwareBox.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {Box: 'Box'},
  Size: {Medium: 'md'},
  useLanguage: () => ({translate: (key: string) => `[${key}]`}),
}));

let app: App | undefined;
let container: HTMLElement;
const onClickHeader = vi.fn();

const mount = (props: Record<string, unknown> = {}, slots: Record<string, () => unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareBox, {onClickHeader, ...props}, {default: () => h('p', {class: 'content'}, 'Content'), ...slots}),
  });
  app.directive('test', {});
  app.component('PdkLoader', defineComponent({setup: () => () => h('i', {class: 'loader'})}));
  app.mount(container);
};

const find = (selector: string): HTMLElement | null => container.querySelector(selector);

describe('ShopwareBox', () => {
  beforeEach(() => {
    onClickHeader.mockReset();
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the content in a card', () => {
    mount();

    expect(find('.mypa-card .mypa-card__content .content')).not.toBeNull();
    expect(find('.mypa-card__header')).toBeNull();
    expect(find('.mypa-card__footer')).toBeNull();
  });

  it('shows only the loader while loading', () => {
    mount({loading: true, title: 'title'});

    expect(find('.loader')).not.toBeNull();
    expect(find('.mypa-card')).toBeNull();
    expect(find('.content')).toBeNull();
  });

  it('renders the translated title in the header', () => {
    mount({title: 'title'});

    expect(find('.mypa-card__header')?.textContent).toBe('[title]');
  });

  it('emits clickHeader on a click on the header', () => {
    mount({title: 'title'});

    find('.mypa-card__header')?.click();

    expect(onClickHeader).toHaveBeenCalledOnce();
  });

  it('renders the header and the footer slots', () => {
    mount({}, {header: () => 'Head', footer: () => 'Foot'});

    expect(find('.mypa-card__header')?.textContent).toBe('Head');
    expect(find('.mypa-card__footer')?.textContent).toBe('Foot');
  });
});

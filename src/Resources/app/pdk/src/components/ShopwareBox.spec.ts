import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareBox from './ShopwareBox.vue';

vi.mock('@myparcel-dev/pdk-admin', async () => {
  const vue = await import('vue');

  return {
  // Renders the id of its action, so the test can see which actions the box shows.
  ActionButton: vue.defineComponent({
    props: ['action'],
    setup: (p) => () => vue.h('button', {class: 'action'}, p.action.id),
  }),
  AdminComponent: {Box: 'Box'},
  Size: {Medium: 'md'},
  useLanguage: () => ({translate: (key: string) => `[${key}]`}),
  };
});

let app: App | undefined;
let container: HTMLElement;
const onClickHeader = vi.fn();

const mount = (props: Record<string, unknown> = {}, slots: Record<string, () => unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareBox, {onClickHeader, ...props}, {default: () => h('p', {class: 'content'}, 'Content'), ...slots}),
  });
  app.directive('test', {});
  app.component('PdkButtonGroup', defineComponent({setup: (_, {slots}) => () => h('div', {class: 'group'}, slots.default?.())}));
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

  // The PDK renders ConceptBoxWrapper as this box and gives it the actions of the order, such as export.
  it('renders the actions as buttons in the footer', () => {
    mount({actions: [{id: 'export'}, {id: 'print'}]});

    expect([...container.querySelectorAll('.mypa-card__footer .group .action')].map((b) => b.textContent)).toEqual([
      'export',
      'print',
    ]);
  });

  it('renders the footer slot instead of the actions', () => {
    mount({actions: [{id: 'export'}]}, {footer: () => h('span', {class: 'own-footer'})});

    expect(find('.mypa-card__footer .own-footer')).not.toBeNull();
    expect(find('.mypa-card__footer .action')).toBeNull();
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

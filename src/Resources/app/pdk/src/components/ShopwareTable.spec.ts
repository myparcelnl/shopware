import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareTable from './ShopwareTable.vue';

// The real context reads the transition from the admin config. This one takes it from the props.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {Table: 'Table'},
    useTableContext: (props: {transition?: string}) => ({transition: computed(() => props.transition)}),
  };
});

let app: App | undefined;
let container: HTMLElement;

const row = () => h('tr', {key: 'a'}, [h('td', 'Cell')]);

const mount = (props: Record<string, unknown> = {}, slots: Record<string, () => unknown> = {}) => {
  app = createApp({render: () => h(ShopwareTable, props, {default: () => [row()], ...slots})});
  app.directive('test', {});
  app.mount(container);
};

describe('ShopwareTable', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the rows in the tbody of a Shopware table', () => {
    mount();

    expect(container.querySelector('table.mypa-table tbody td')?.textContent).toBe('Cell');
    expect(container.querySelector('thead')).toBeNull();
    expect(container.querySelector('tfoot')).toBeNull();
  });

  it('renders the header and the footer slots', () => {
    mount({}, {header: () => h('tr', [h('th', 'Head')]), footer: () => h('tr', [h('td', 'Foot')])});

    expect(container.querySelector('thead th')?.textContent).toBe('Head');
    expect(container.querySelector('tfoot td')?.textContent).toBe('Foot');
  });

  it('gives a new row the enter classes of the transition', async () => {
    const keys = ref(['a']);

    app = createApp({
      render: () =>
        h(ShopwareTable, {transition: 'mypa-fade'}, {default: () => keys.value.map((key) => h('tr', {key}, [h('td', key)]))}),
    });
    app.directive('test', {});
    app.mount(container);

    keys.value = ['a', 'b'];
    await nextTick();

    const rows = container.querySelectorAll('table.mypa-table tbody tr');

    expect(rows).toHaveLength(2);
    expect(rows[1].classList.contains('mypa-fade-enter-active')).toBe(true);
    expect(rows[0].classList.contains('mypa-fade-enter-active')).toBe(false);
  });
});

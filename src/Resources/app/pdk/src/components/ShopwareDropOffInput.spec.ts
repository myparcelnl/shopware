import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareDropOffInput from './ShopwareDropOffInput.vue';

// Monday is on and Tuesday is off.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {reactive} = await import('vue');

  return {
    AdminComponent: {DropOffInput: 'DropOffInput'},
    useDropOffInputContext: () => ({
      weekdaysObject: {1: 'Monday', 2: 'Tuesday'},
      toggleRefs: reactive({1: true, 2: false}),
      cutoffRefs: reactive({1: '16:00', 2: '17:00'}),
      toggleElements: {1: {name: 'toggle-1'}, 2: {name: 'toggle-2'}},
      cutoffElements: {1: {name: 'cutoff-1'}, 2: {name: 'cutoff-2'}},
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;

const stub = (name: string) =>
  defineComponent({
    props: ['element', 'modelValue'],
    setup: (p) => () => h('i', {'data-stub': name, 'data-element': p.element.name, 'data-value': String(p.modelValue)}),
  });

describe('ShopwareDropOffInput', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
    app = createApp({render: () => h(ShopwareDropOffInput, {element: {name: 'dropOff'}, modelValue: {}})});
    app.directive('test', {});
    app.component('PdkToggleInput', stub('toggle'));
    app.component('PdkTimeInput', stub('time'));
    app.mount(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders a row per weekday with the day and the toggle', () => {
    const rows = [...container.querySelectorAll('.mypa-drop-off__day')];

    expect(rows.map((row) => row.querySelector('.mypa-drop-off__name')?.textContent)).toEqual(['Monday', 'Tuesday']);
    expect(rows.map((row) => row.querySelector('[data-stub="toggle"]')?.getAttribute('data-element'))).toEqual([
      'toggle-1',
      'toggle-2',
    ]);
  });

  it('shows the time input only for a day that is on', () => {
    const times = [...container.querySelectorAll('[data-stub="time"]')];

    expect(times).toHaveLength(1);
    expect(times[0].getAttribute('data-element')).toBe('cutoff-1');
    expect(times[0].getAttribute('data-value')).toBe('16:00');
  });
});

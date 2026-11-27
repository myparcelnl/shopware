import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareTriStateInput from './ShopwareTriStateInput.vue';

const toggleElement = {name: 'toggle'};
const inheritElement = {name: 'inherit'};

// The real context derives the toggle and the inherit state from the model. This one gives fixed values.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed, ref} = await import('vue');

  return {
    AdminComponent: {TriStateInput: 'TriStateInput'},
    useTriStateInputContext: (props: {modelValue: number}) => ({
      model: computed(() => props.modelValue),
      toggleModel: ref(true),
      toggleElement,
      inheritModel: ref(false),
      inheritElement,
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;

const stub = (name: string) =>
  defineComponent({
    props: ['element', 'modelValue', 'accessibleLabel'],
    setup: (p) => () =>
      h('i', {
        'data-stub': name,
        'data-element': p.element.name,
        'data-value': String(p.modelValue),
        'data-accessible-label': p.accessibleLabel,
      }),
  });

const mount = () => {
  app = createApp({render: () => h(ShopwareTriStateInput, {element: {name: 'option', label: 'Signature'}, modelValue: 1})});
  app.directive('test', {});
  app.component('PdkToggleInput', stub('toggle'));
  app.component('PdkCheckboxInput', stub('checkbox'));
  app.mount(container);
};

describe('ShopwareTriStateInput', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('binds the hidden input to the model', () => {
    mount();

    const hidden = container.querySelector('.mypa-tri-state input[type="hidden"]') as HTMLInputElement;

    expect(hidden.value).toBe('1');
  });

  it('renders the toggle and the inherit checkbox with their elements and models', () => {
    mount();

    const toggle = container.querySelector('[data-stub="toggle"]');
    const checkbox = container.querySelector('[data-stub="checkbox"]');

    expect(toggle?.getAttribute('data-element')).toBe('toggle');
    expect(toggle?.getAttribute('data-value')).toBe('true');
    expect(checkbox?.getAttribute('data-element')).toBe('inherit');
    expect(checkbox?.getAttribute('data-value')).toBe('false');
  });

  // The toggle element of the context has no label, so the tri-state gives the switch its own.
  it('gives the label of the setting to the switch', () => {
    mount();

    expect(container.querySelector('[data-stub="toggle"]')?.getAttribute('data-accessible-label')).toBe('Signature');
  });
});

import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick, ref} from 'vue';
import ShopwareRadioInput from './ShopwareRadioInput.vue';

// The real useElementContext reads the form element. This one gives the id and a model on modelValue.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {RadioInput: 'RadioInput'},
    useElementContext: (
      props: {modelValue: string; element: {name: string}},
      emit: (event: 'update:modelValue', value: string) => void,
    ) => ({
      id: `field-${props.element.name}`,
      model: computed({get: () => props.modelValue, set: (value) => emit('update:modelValue', value)}),
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;
const value = ref('a');

const mount = (element: Record<string, unknown> = {}, attrs: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareRadioInput, {
        element: {name: 'b', label: 'Option B', props: {value: 'b'}, ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: string) => (value.value = newValue),
        ...attrs,
      }),
  });
  app.directive('test', {});
  app.component('PdkIcon', defineComponent({props: ['icon'], setup: (p) => () => h('i', {'data-icon': p.icon})}));
  app.component('PdkImage', defineComponent({props: ['src'], setup: (p) => () => h('img', {src: p.src})}));
  app.mount(container);
};

const input = (): HTMLInputElement => container.querySelector('input') as HTMLInputElement;

describe('ShopwareRadioInput', () => {
  beforeEach(() => {
    value.value = 'a';
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders a radio with the label', () => {
    mount();

    expect(input().classList.contains('mypa-radio__input')).toBe(true);
    expect(input().type).toBe('radio');
    expect(input().id).toBe('field-b');
    expect(container.querySelector('label.mypa-radio')?.textContent).toContain('Option B');
  });

  it('puts the attributes of the group on the input', () => {
    mount({}, {name: 'field-carrier', disabled: true});

    expect(input().name).toBe('field-carrier');
    expect(input().disabled).toBe(true);
    expect(container.querySelector('label')?.hasAttribute('name')).toBe(false);
  });

  it('writes the value to the model on change', async () => {
    mount();

    input().click();
    await nextTick();

    expect(value.value).toBe('b');
  });

  it('stays disabled when the group passes disabled false', () => {
    mount({isDisabled: true}, {disabled: false});

    expect(input().disabled).toBe(true);
  });

  it.each(['isDisabled', 'isSuspended', 'isReadOnly'])('is disabled when %s', (flag) => {
    mount({[flag]: true});

    expect(input().disabled).toBe(true);
  });

  it('renders the icon and the image when the element has them', () => {
    mount({props: {value: 'b', icon: 'truck', image: '/logo.png'}});

    expect(container.querySelector('[data-icon="truck"]')).not.toBeNull();
    expect(container.querySelector('img')?.getAttribute('src')).toBe('/logo.png');
  });
});

import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareTextInput from './ShopwareTextInput.vue';

// The real useElementContext reads the form element. This one gives the id and a model on modelValue.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {TextInput: 'TextInput'},
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
const value = ref('');

const mount = (element: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareTextInput, {
        element: {name: 'title', ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: string) => (value.value = newValue),
      }),
  });
  app.directive('test', {});
  app.mount(container);
};

const input = (): HTMLInputElement => container.querySelector('input') as HTMLInputElement;

describe('ShopwareTextInput', () => {
  beforeEach(() => {
    value.value = '';
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('has the field class, the id and the name', () => {
    mount();

    expect(input().classList.contains('mypa-field')).toBe(true);
    expect(input().id).toBe('field-title');
    expect(input().name).toBe('field-title');
  });

  it('writes the trimmed value to the model', async () => {
    mount();
    input().value = '  a  ';
    input().dispatchEvent(new Event('input'));
    await nextTick();

    expect(value.value).toBe('a');
  });

  it.each([{isDisabled: true}, {isSuspended: true}])('is disabled with %o', (element) => {
    mount(element);

    expect(input().disabled).toBe(true);
  });

  it('is read-only, not disabled, when the element is read-only', () => {
    mount({isReadOnly: true});

    expect(input().readOnly).toBe(true);
    expect(input().disabled).toBe(false);
  });
});

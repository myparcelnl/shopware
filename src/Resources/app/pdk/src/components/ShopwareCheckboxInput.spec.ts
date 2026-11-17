import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareCheckboxInput from './ShopwareCheckboxInput.vue';

vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {CheckboxInput: 'CheckboxInput'},
    useElementContext: (
      props: {modelValue: unknown; element: {name: string}},
      emit: (event: 'update:modelValue', value: unknown) => void,
    ) => ({
      id: `field-${props.element.name}`,
      model: computed({get: () => props.modelValue, set: (value) => emit('update:modelValue', value)}),
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;
const value = ref<unknown>(false);

const mount = (element: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareCheckboxInput, {
        element: {name: 'selected', label: 'Select all', ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: unknown) => (value.value = newValue),
      }),
  });
  app.directive('test', {});
  app.mount(container);
};

const label = (): HTMLLabelElement => container.querySelector('label') as HTMLLabelElement;
const input = (): HTMLInputElement => container.querySelector('input') as HTMLInputElement;

describe('ShopwareCheckboxInput', () => {
  beforeEach(() => {
    value.value = false;
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('is a checkbox in a label with the checkbox class and the element label', () => {
    mount();

    expect(label().classList.contains('mypa-checkbox')).toBe(true);
    expect(label().textContent?.trim()).toBe('Select all');
    expect(input().type).toBe('checkbox');
    expect(input().id).toBe('field-selected');
  });

  it('toggles the model on click', async () => {
    mount();
    input().click();
    await nextTick();

    expect(value.value).toBe(true);
  });

  it('adds element.props.value to an array model', async () => {
    value.value = [];
    mount({props: {value: 'abc'}});
    input().click();
    await nextTick();

    expect(value.value).toEqual(['abc']);
  });

  it.each([{isDisabled: true}, {isSuspended: true}, {isReadOnly: true}])('is disabled with %o', (element) => {
    mount(element);

    expect(input().disabled).toBe(true);
  });
});

import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareSelectInput from './ShopwareSelectInput.vue';

interface Option {
  label: string;
  value: string;
  disabled?: boolean;
}

// The real useSelectInputContext reads the options from the form element. This one takes them from element.props.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {SelectInput: 'SelectInput'},
    useSelectInputContext: (
      props: {modelValue: string; element: {name: string; props: {options: Option[]}}},
      emit: (event: 'update:modelValue', value: string) => void,
    ) => ({
      id: `field-${props.element.name}`,
      options: computed(() => props.element.props.options),
      model: computed({get: () => props.modelValue, set: (value) => emit('update:modelValue', value)}),
    }),
  };
});

const OPTIONS: Option[] = [
  {label: 'Package', value: 'package'},
  {label: 'Letter', value: 'letter', disabled: true},
  {label: 'Mailbox', value: 'mailbox'},
];

let app: App | undefined;
let container: HTMLElement;
const value = ref('package');

const mount = (element: Record<string, unknown> = {}, options: Option[] = OPTIONS) => {
  app = createApp({
    render: () =>
      h(ShopwareSelectInput, {
        element: {name: 'packageType', props: {options}, ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: string) => (value.value = newValue),
      }),
  });
  app.directive('test', {});
  app.mount(container);
};

const select = (): HTMLSelectElement => container.querySelector('select') as HTMLSelectElement;

describe('ShopwareSelectInput', () => {
  beforeEach(() => {
    value.value = 'package';
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('is a native select with the field classes, the id and the name', () => {
    mount();

    expect([...select().classList]).toEqual(['mypa-field', 'mypa-field--select']);
    expect(select().id).toBe('field-packageType');
    expect(select().name).toBe('field-packageType');
  });

  it('renders one option per item with its label and disabled state', () => {
    mount();

    const options = [...select().options];

    expect(options.map((option) => option.textContent)).toEqual(['Package', 'Letter', 'Mailbox']);
    expect(options.map((option) => option.disabled)).toEqual([false, true, false]);
  });

  it('writes the chosen value to the model', async () => {
    mount();
    select().value = 'mailbox';
    select().dispatchEvent(new Event('change'));
    await nextTick();

    expect(value.value).toBe('mailbox');
  });

  it('is disabled with one option', () => {
    mount({}, [OPTIONS[0]]);

    expect(select().disabled).toBe(true);
  });

  it.each([{isDisabled: true}, {isSuspended: true}])('is disabled with %o', (element) => {
    mount(element);

    expect(select().disabled).toBe(true);
  });
});

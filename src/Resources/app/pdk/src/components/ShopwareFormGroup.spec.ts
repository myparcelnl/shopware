import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareFormGroup from './ShopwareFormGroup.vue';

// The id comes from the name of the element. A translation is the key in brackets, and every key
// but 'missing' has a translation.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {unref} = await import('vue');

  return {
    AdminComponent: {FormGroup: 'FormGroup'},
    generateFieldId: (element: {name: string}) => `field-${unref(element).name}`,
    useLanguage: () => ({
      has: (key?: string) => Boolean(key) && key !== 'missing',
      translate: (key: string) => `[${key}]`,
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;

const mount = (element: Record<string, unknown> = {}, slots: Record<string, () => unknown> = {}) => {
  app = createApp({
    render: () =>
      h(
        ShopwareFormGroup,
        {element: {name: 'title', label: 'Title', isValid: true, isVisible: true, errors: [], props: {}, ...element}},
        {default: () => h('input', {class: 'field'}), ...slots},
      ),
  });
  app.directive('test', {});
  app.mount(container);
};

const find = (selector: string): HTMLElement | null => container.querySelector(selector);

describe('ShopwareFormGroup', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the label for the field id', () => {
    mount();

    const label = find('label.mypa-form-group__label');

    expect(find('.mypa-form-group')).not.toBeNull();
    expect(label?.getAttribute('for')).toBe('field-title');
    expect(label?.textContent).toContain('Title');
  });

  // As in WooCommerce: almost every setting is optional, so the tip says nothing.
  it('shows no optional tip, also when the field is optional', () => {
    mount({isOptional: true});

    expect(container.textContent).not.toContain('form_optional_tip');
  });

  // As in WooCommerce: a field that depends on another one hides until that one allows it.
  it('hides the group when the element is not visible', () => {
    mount({isVisible: false});

    expect(find('.mypa-form-group')?.style.display).toBe('none');
  });

  it('shows the group when the element is visible', () => {
    mount();

    expect(find('.mypa-form-group')?.style.display).toBe('');
  });

  // As in WooCommerce: a key without a translation is not shown.
  it('shows no description that has no translation', () => {
    mount({props: {description: 'missing'}});

    expect(find('.mypa-form-group__description')).toBeNull();
  });

  it('renders no empty label when the element has no label', () => {
    mount({label: undefined});

    expect(find('label')).toBeNull();
    expect(find('.field')).not.toBeNull();
  });

  it('renders the translated description as html', () => {
    mount({props: {description: 'desc'}});

    expect(find('.mypa-form-group__description')?.innerHTML).toBe('[desc]');
  });

  it('shows no description when there is none', () => {
    mount();

    expect(find('.mypa-form-group__description')).toBeNull();
  });

  it('lists the errors when the field is not valid', () => {
    mount({isValid: false, errors: ['a', 'b']});

    const items = container.querySelectorAll('.mypa-form-group__errors li');

    expect([...items].map((item) => item.textContent)).toEqual(['a', 'b']);
  });

  it('shows no errors when the field is valid', () => {
    mount({errors: ['a']});

    expect(find('.mypa-form-group__errors')).toBeNull();
  });

  it('renders the default slot and the label slot', () => {
    mount({}, {label: () => 'Own label'});

    expect(find('.field')).not.toBeNull();
    expect(find('.mypa-form-group__label')?.textContent).toBe('Own label');
  });
});

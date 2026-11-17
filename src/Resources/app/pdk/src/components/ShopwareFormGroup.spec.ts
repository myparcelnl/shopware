import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareFormGroup from './ShopwareFormGroup.vue';

// The id comes from the name of the element. A translation is the key in brackets.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {unref} = await import('vue');

  return {
    AdminComponent: {FormGroup: 'FormGroup'},
    generateFieldId: (element: {name: string}) => `field-${unref(element).name}`,
    useLanguage: () => ({translate: (key: string) => `[${key}]`}),
  };
});

let app: App | undefined;
let container: HTMLElement;

const mount = (element: Record<string, unknown> = {}, slots: Record<string, () => unknown> = {}) => {
  app = createApp({
    render: () =>
      h(
        ShopwareFormGroup,
        {element: {name: 'title', label: 'Title', isValid: true, errors: [], props: {}, ...element}},
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

  it('shows the optional tip when the field is optional', () => {
    mount({isOptional: true});

    expect(find('.mypa-form-group__optional')?.textContent).toBe('[form_optional_tip]');
  });

  it('shows no optional tip when the field is required', () => {
    mount();

    expect(find('.mypa-form-group__optional')).toBeNull();
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

import {type Component, defineComponent, h} from 'vue';

type ClassValue = string | string[];

/**
 * Wraps a preset-default component and adds a class to its root. Use it for a PDK component that
 * needs only the class of the Shopware look: the props, the listeners and the slots go to the preset
 * component as they are, so later changes in admin-preset-default come along without a copy.
 *
 * @param className The class, or a function that makes it from the attributes (for example the level of a heading).
 */
export const withClass = (
  component: Component,
  className: ClassValue | ((attrs: Record<string, unknown>) => ClassValue),
): Component =>
  defineComponent({
    name: 'WithClass',
    inheritAttrs: false,
    setup: (_, {attrs, slots}) => () =>
      h(
        component,
        {...attrs, class: [attrs.class, typeof className === 'function' ? className(attrs) : className]},
        slots,
      ),
  });

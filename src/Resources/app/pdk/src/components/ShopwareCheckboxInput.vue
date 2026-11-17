<template>
  <label
    v-test="AdminComponent.CheckboxInput"
    class="mypa-checkbox">
    <input
      :id="id"
      v-model="model"
      :disabled="element.isDisabled || element.isSuspended || element.isReadOnly"
      :name="id"
      :value="element.props?.value"
      class="mypa-checkbox__input"
      type="checkbox" />
    <span v-if="element.label">{{ element.label }}</span>
  </label>
</template>

<script generic="T extends CheckboxInputModelValue" lang="ts" setup>
/**
 * The PDK checkbox with the look of the Shopware admin. TriStateInput, ShippingMethodsInput and
 * the shipment table of preset-default render this checkbox, so they get the same look.
 */
import {
  AdminComponent,
  type CheckboxInputEmits,
  type CheckboxInputModelValue,
  type CheckboxInputProps,
  useElementContext,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<CheckboxInputProps<T>>();
const emit = defineEmits<CheckboxInputEmits<T>>();

const {id, model} = useElementContext(props, emit);
</script>

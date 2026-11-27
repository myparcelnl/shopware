<template>
  <!-- As in WooCommerce: a field that depends on another one hides until that one allows it. -->
  <div
    v-show="element.isVisible"
    v-test="AdminComponent.FormGroup"
    class="mypa-form-group">
    <!-- As in WooCommerce there is no optional tip: almost every setting is optional, so it says nothing. -->
    <label
      v-if="element.label || $slots.label"
      :for="id"
      class="mypa-form-group__label">
      <!-- Can be used instead of the label prop. -->
      <slot name="label">
        {{ element.label }}
      </slot>
    </label>

    <!-- As in DefaultFormGroup: the description is a translation key, and a translation can have a link.
         As in WooCommerce: a key without a translation is not shown. -->
    <small
      v-if="has(element.props?.description)"
      class="mypa-form-group__description"
      v-html="translate(element.props.description)" />

    <slot />

    <ul
      v-if="!element.isValid"
      class="mypa-form-group__errors">
      <li
        v-for="(error, index) in element.errors"
        :key="`error_${index}`">
        {{ error }}
      </li>
    </ul>
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK form group as a field of the Shopware admin: the label and the description above the
 * field, and the errors below it. It has the label, the description and the errors of preset-default.
 */
import {toRefs} from 'vue';
import {
  AdminComponent,
  type FormGroupProps,
  type FormGroupSlots,
  generateFieldId,
  useLanguage,
} from '@myparcel-dev/pdk-admin';

const props = defineProps<FormGroupProps>();
defineSlots<FormGroupSlots>();

const propRefs = toRefs(props);

const id = generateFieldId(propRefs.element);

const {has, translate} = useLanguage();
</script>

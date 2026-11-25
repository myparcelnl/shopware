<template>
  <div
    v-test="AdminComponent.FormGroup"
    class="mypa-form-group">
    <label
      :for="id"
      class="mypa-form-group__label">
      <!-- Can be used instead of the label prop. -->
      <slot name="label">
        {{ element.label }}
        <span
          v-if="element.isOptional"
          class="mypa-form-group__optional"
          v-text="translate('form_optional_tip')" />
      </slot>
    </label>

    <!-- As in DefaultFormGroup: the description is a translation key, and a translation can have a link. -->
    <small
      v-if="element.props?.description"
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

const {translate} = useLanguage();
</script>

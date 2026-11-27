<template>
  <div>
    <input
      :id="id"
      v-model="model"
      :name="id"
      type="hidden" />

    <span
      v-test="AdminComponent.ToggleInput"
      class="mypa-switch-field">
      <input
        :id="toggleId"
        v-model="toggleModel"
        :disabled="element.isDisabled || element.isSuspended || element.isReadOnly"
        :readonly="element.isReadOnly"
        :value="true"
        :aria-label="accessibleLabel || element.label || undefined"
        class="mypa-switch"
        role="switch"
        type="checkbox" />

      <label :for="toggleId">
        {{ translate(`toggle_${toggleModel ? 'yes' : 'no'}`) }}
      </label>
    </span>
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK toggle as the switch of the Shopware admin. It keeps the text yes or no of
 * preset-default next to the switch, and the hidden input that holds the value of the form.
 *
 * The label of the form group points to the hidden input, so the switch takes its name from the
 * label of the element, or from accessibleLabel when the element has none (as in a tri-state).
 */
import {
  AdminComponent,
  type ToggleInputEmits,
  type ToggleInputProps,
  useLanguage,
  useToggleInputContext,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<ToggleInputProps & {accessibleLabel?: string}>();
const emit = defineEmits<ToggleInputEmits>();

const {id, model, toggleId, toggleModel} = useToggleInputContext(props, emit);

const {translate} = useLanguage();
</script>

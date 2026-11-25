<template>
  <label
    v-test="AdminComponent.RadioInput"
    class="mypa-radio">
    <input
      :id="id"
      v-model="model"
      :name="id"
      :readonly="element.isReadOnly"
      :value="element.props?.value"
      class="mypa-radio__input"
      type="radio"
      v-bind="$attrs"
      :disabled="disabled" />

    <PdkIcon
      v-if="element.props?.icon"
      :icon="element.props.icon" />

    <PdkImage
      v-if="element.props?.image"
      :alt="element.label ?? ''"
      :src="element.props.image"
      width="24" />

    <span v-if="element.label">{{ element.label }}</span>
  </label>
</template>

<script lang="ts">
export default {inheritAttrs: false};
</script>

<script lang="ts" setup>
/**
 * The PDK radio with the look of the Shopware admin. The group gives the name and the disabled state
 * as attributes; they go on the input, not on the label.
 */
import {computed, useAttrs} from 'vue';
import {AdminComponent, type RadioInputEmits, type RadioInputProps, useElementContext} from '@myparcel-dev/pdk-admin';

const props = defineProps<RadioInputProps>();
const emit = defineEmits<RadioInputEmits>();

const {id, model} = useElementContext(props, emit);

const attrs = useAttrs();

// The group disables all its radios. An option of its own stays disabled when the group is not.
// A radio ignores readonly, so a read-only option is disabled.
const disabled = computed(
  () =>
    Boolean(attrs.disabled) || props.element.isDisabled || props.element.isSuspended || props.element.isReadOnly,
);
</script>

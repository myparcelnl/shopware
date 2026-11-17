<template>
  <label
    v-test="AdminComponent.RadioInput"
    class="mypa-radio">
    <input
      :id="id"
      v-model="model"
      :disabled="element.isDisabled || element.isSuspended"
      :name="id"
      :readonly="element.isReadOnly"
      :value="element.props?.value"
      class="mypa-radio__input"
      type="radio"
      v-bind="$attrs" />

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
import {AdminComponent, type RadioInputEmits, type RadioInputProps, useElementContext} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<RadioInputProps>();
const emit = defineEmits<RadioInputEmits>();

const {id, model} = useElementContext(props, emit);
</script>

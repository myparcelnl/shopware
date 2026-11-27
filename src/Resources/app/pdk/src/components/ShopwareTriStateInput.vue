<template>
  <div
    v-test="AdminComponent.TriStateInput"
    class="mypa-tri-state">
    <input
      v-model="model"
      type="hidden" />

    <!-- The toggle element of the PDK has no label, so the switch gets the label of the setting. -->
    <PdkToggleInput
      v-model="toggleModel"
      :accessible-label="element.label"
      :element="toggleElement" />

    <PdkCheckboxInput
      v-model="inheritModel"
      :element="inheritElement" />
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK tri-state input: the Shopware switch for the value and the Shopware checkbox to use the
 * default, side by side. It writes the model as preset-default does.
 */
import {
  AdminComponent,
  type TriStateInputEmits,
  type TriStateInputProps,
  useTriStateInputContext,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<TriStateInputProps>();
const emit = defineEmits<TriStateInputEmits>();

const {model, inheritElement, inheritModel, toggleElement, toggleModel} = useTriStateInputContext(props, emit);
</script>

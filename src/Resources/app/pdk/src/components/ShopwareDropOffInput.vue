<template>
  <ul
    v-test="AdminComponent.DropOffInput"
    class="mypa-drop-off">
    <li
      v-for="[day, human] in Object.entries(weekdaysObject)"
      :key="day"
      class="mypa-drop-off__day">
      <span
        class="mypa-drop-off__name"
        v-text="human" />

      <PdkToggleInput
        v-model="toggleRefs[day]"
        :element="toggleElements[day]" />

      <PdkTimeInput
        v-if="toggleRefs[day]"
        v-model="cutoffRefs[day]"
        :element="cutoffElements[day]" />
    </li>
  </ul>
</template>

<script lang="ts" setup>
/**
 * The PDK drop-off days as rows of the day, the Shopware switch, and the cut-off time of a day that is on.
 */
import {
  AdminComponent,
  type DropOffInputEmits,
  type DropOffInputProps,
  useDropOffInputContext,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<DropOffInputProps>();
const emit = defineEmits<DropOffInputEmits>();

const {weekdaysObject, cutoffElements, toggleElements, toggleRefs, cutoffRefs} = useDropOffInputContext(props, emit);
</script>

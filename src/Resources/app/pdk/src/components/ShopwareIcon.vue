<template>
  <svg
    v-if="shape"
    v-test="[AdminComponent.Icon, icon]"
    :class="['mypa-icon', {'mypa-icon--spin': icon === AdminIcon.Spinner}]"
    :viewBox="shape.viewBox"
    fill="currentColor"
    role="none"
    xmlns="http://www.w3.org/2000/svg">
    <path
      :d="shape.path"
      clip-rule="evenodd"
      fill-rule="evenodd" />
  </svg>
</template>

<script lang="ts" setup>
import {computed} from 'vue';
import {AdminComponent, AdminIcon, type IconProps} from '@myparcel-dev/pdk-admin';
import {SHOPWARE_ICONS} from './shopwareIcons';

const props = defineProps<IconProps>();

// The PDK also passes package and delivery types here. They have no icon and render nothing.
const shape = computed(() => SHOPWARE_ICONS[props.icon]);
</script>

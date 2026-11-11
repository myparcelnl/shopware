<template>
  <span
    v-if="svg"
    v-test="[AdminComponent.Icon, icon]"
    :class="['mypa-icon', {'mypa-icon--spin': icon === AdminIcon.Spinner}]"
    role="none"
    v-html="svg" />
</template>

<script lang="ts" setup>
import {computed} from 'vue';
import {AdminComponent, AdminIcon, type IconProps} from '@myparcel-dev/pdk-admin';
import {getIconSvg} from './shopwareIcons';

const props = defineProps<IconProps>();

// The PDK also passes package and delivery types here. They have no icon and render nothing.
// v-html is safe: the markup comes from the icon kit in the build, never from input. mt-icon of
// Shopware renders its icons the same way.
const svg = computed(() => getIconSvg(props.icon));
</script>

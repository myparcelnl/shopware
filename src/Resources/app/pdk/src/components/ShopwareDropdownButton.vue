<template>
  <div
    ref="root"
    v-test="AdminComponent.DropdownButton"
    class="mypa-relative mypa-inline-flex mypa-align-top">
    <ActionButton
      v-for="(action, index) in dropdownActions.standalone"
      :key="action.id"
      v-test="[AdminComponent.DropdownButton, 'standalone']"
      :action="action"
      :class="{
        '!mypa-rounded-l-none -mypa-ml-px': index > 0,
        '!mypa-rounded-r-none': index < dropdownActions.standalone.length - 1 || hasMenu,
      }"
      :disabled="disabled"
      :hide-text="hideText"
      :size="size" />

    <PdkButton
      v-if="hasMenu"
      v-test="[AdminComponent.DropdownButton, 'button']"
      :aria-expanded="toggled"
      :aria-label="translate('toggle_dropdown')"
      :class="{'!mypa-rounded-l-none -mypa-ml-px': dropdownActions.standalone.length > 0}"
      :disabled="disabled"
      :icon="dropdownIcon"
      :size="size"
      aria-haspopup="true"
      @click="onToggle">
      <slot />
    </PdkButton>

    <div
      v-if="hasMenu"
      v-show="toggled"
      ref="menu"
      :class="openUp ? 'mypa-bottom-full mypa-mb-1' : 'mypa-top-full mypa-mt-1'"
      class="mypa-absolute mypa-left-0 mypa-z-50 mypa-flex mypa-flex-col mypa-items-stretch mypa-gap-1 mypa-rounded mypa-border mypa-border-solid mypa-border-gray-300 mypa-bg-white mypa-p-1 mypa-shadow"
      role="menu">
      <ActionButton
        v-for="(action, index) in dropdownActions.hidden"
        :key="`${index}_${action.id}`"
        v-test="[AdminComponent.DropdownButton, 'item']"
        :action="action"
        :disabled="disabled"
        :icon="action.icon"
        :size="size"
        class="mypa-whitespace-nowrap mypa-text-left"
        @click="toggle(false)" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref} from 'vue';
import {
  ActionButton,
  AdminComponent,
  type DropdownButtonProps,
  type DropdownButtonSlots,
  useDropdownButtonContext,
  useLanguage,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<DropdownButtonProps>();

defineSlots<DropdownButtonSlots>();

const {toggled, toggle, dropdownActions, dropdownIcon} = useDropdownButtonContext(props);

const {translate} = useLanguage();

const root = ref<HTMLElement>();
const menu = ref<HTMLElement>();
const openUp = ref(false);

const hasMenu = computed(() => dropdownActions.value.hidden.length > 0);

/**
 * Opens the menu below the buttons, or above them when the viewport has no room below. The menu has a size once it is shown, so it is measured after the render.
 */
const onToggle = async () => {
  const opening = !toggled.value;

  toggle(opening);

  if (!opening) {
    return;
  }

  await nextTick();

  const group = root.value?.getBoundingClientRect();
  const height = menu.value?.getBoundingClientRect().height ?? 0;

  openUp.value = Boolean(group) && height > window.innerHeight - group!.bottom && group!.top > height;
};

// The menu is no hover or focus menu: it only leaves on a click outside, on Escape or after an action.
const onDocumentClick = (event: MouseEvent) => {
  if (toggled.value && !root.value?.contains(event.target as Node)) {
    toggle(false);
  }
};

const onDocumentKeydown = (event: KeyboardEvent) => {
  if (toggled.value && event.key === 'Escape') {
    toggle(false);
  }
};

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

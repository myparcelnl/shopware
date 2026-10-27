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
      :style="menuStyle"
      class="mypa-fixed mypa-z-50 mypa-flex mypa-flex-col mypa-items-stretch mypa-gap-1 mypa-rounded mypa-border mypa-border-solid mypa-border-gray-300 mypa-bg-white mypa-p-1 mypa-shadow"
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
import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue';
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
const menuStyle = ref<{top?: string; left?: string}>({});

const hasMenu = computed(() => dropdownActions.value.hidden.length > 0);

const GAP = 4;

/**
 * Opens the menu below the buttons, or above them when the viewport has no room below.
 * The menu is fixed, because the grid of the order list clips an absolute menu. It has a size once it is shown, so it is
 * placed after the render: at the left edge of the group, or at its right edge when the menu would pass the viewport.
 */
const onToggle = async () => {
  const opening = !toggled.value;

  toggle(opening);

  if (!opening) {
    return;
  }

  await nextTick();

  const group = root.value?.getBoundingClientRect();
  const size = menu.value?.getBoundingClientRect();

  if (!group || !size) {
    return;
  }

  const fitsBelow = group.bottom + GAP + size.height <= window.innerHeight;
  const fitsAbove = group.top - GAP - size.height >= 0;
  const top = fitsBelow || !fitsAbove ? group.bottom + GAP : group.top - GAP - size.height;
  const left = group.left + size.width > window.innerWidth ? group.right - size.width : group.left;

  menuStyle.value = {top: `${top}px`, left: `${left}px`};
};

// The fixed menu does not follow its buttons, so it closes instead of moving.
const close = () => {
  if (toggled.value) {
    toggle(false);
  }
};

watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) {
      close();
    }
  },
);

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
  // Capture: a scroll does not bubble, and the grid wrapper scrolls, not the window.
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
  window.removeEventListener('scroll', close, true);
  window.removeEventListener('resize', close);
});
</script>

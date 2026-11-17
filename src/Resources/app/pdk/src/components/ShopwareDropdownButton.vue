<template>
  <div
    ref="root"
    v-test="AdminComponent.DropdownButton"
    class="mypa-inline-flex mypa-align-top">
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
      :popovertarget="menuId"
      :size="size">
      <slot />
    </PdkButton>

    <!-- A popover is in the top layer, above the grid cell and the sticky columns. It comes next to the toggle in the focus order. -->
    <div
      v-if="hasMenu"
      :id="menuId"
      ref="menu"
      :style="menuStyle"
      class="mypa-dropdown-menu mypa-flex-col mypa-items-stretch mypa-rounded mypa-border mypa-border-solid mypa-border-gray-300 mypa-bg-white mypa-py-1 mypa-shadow"
      popover="auto"
      @beforetoggle="onBeforeToggle"
      @toggle="onToggle">
      <ActionButton
        v-for="(action, index) in dropdownActions.hidden"
        :key="`${index}_${action.id}`"
        v-test="[AdminComponent.DropdownButton, 'item']"
        :action="action"
        :disabled="disabled"
        :icon="action.icon"
        :size="size"
        :variant="action.variant"
        :class="{'mypa-dropdown-menu__item--danger': action.variant === Variant.Error}"
        class="mypa-dropdown-menu__item"
        @click="close" />
    </div>
  </div>
</template>

<script lang="ts">
// Each menu needs its own id for popovertarget. Vue 3.4 has no useId.
let menuCount = 0;
</script>

<script lang="ts" setup>
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {
  ActionButton,
  AdminComponent,
  type DropdownButtonProps,
  type DropdownButtonSlots,
  useDropdownButtonContext,
  useLanguage,
  Variant,
} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<DropdownButtonProps>();

defineSlots<DropdownButtonSlots>();

const {toggled, toggle, dropdownActions, dropdownIcon} = useDropdownButtonContext(props);

const {translate} = useLanguage();

const menuId = `mypa-dropdown-menu-${++menuCount}`;
const root = ref<HTMLElement>();
const menu = ref<HTMLElement>();
const menuStyle = ref<{top?: string; left?: string; visibility?: 'hidden'}>({});

const hasMenu = computed(() => dropdownActions.value.hidden.length > 0);

const GAP = 4;

/**
 * Hides a menu that opens until it has its place, because it has a size only once it is shown.
 */
const onBeforeToggle = (event: Event) => {
  if ((event as ToggleEvent).newState === 'open') {
    menuStyle.value = {visibility: 'hidden'};
  }
};

/**
 * Keeps the state of the PDK in line with the popover, and places a menu that opened below
 * the buttons, or above them when the viewport has no room below. It is at the left edge
 * of the group, or at its right edge when the menu would pass the viewport.
 */
const onToggle = (event: Event) => {
  const opened = (event as ToggleEvent).newState === 'open';

  toggle(opened);

  if (!opened) {
    return;
  }

  const group = root.value?.getBoundingClientRect();
  const size = menu.value?.getBoundingClientRect();

  if (!group || !size) {
    menuStyle.value = {};

    return;
  }

  const fitsBelow = group.bottom + GAP + size.height <= window.innerHeight;
  const fitsAbove = group.top - GAP - size.height >= 0;
  const top = fitsBelow || !fitsAbove ? group.bottom + GAP : group.top - GAP - size.height;
  const left = group.left + size.width > window.innerWidth ? group.right - size.width : group.left;

  menuStyle.value = {top: `${top}px`, left: `${left}px`};
};

// Hiding the popover gives the focus back to the toggle.
const close = () => {
  if (toggled.value) {
    menu.value?.hidePopover();
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

// The menu does not follow its buttons, so it closes instead of moving.
onMounted(() => {
  // Capture: a scroll does not bubble, and the grid wrapper scrolls, not the window.
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', close, true);
  window.removeEventListener('resize', close);
});
</script>

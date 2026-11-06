<template>
  <!-- A modal dialog is in the top layer, so it leaves the modals block below the order list grid. -->
  <!-- No display class here: it would also show the dialog while it is closed. -->
  <dialog
    ref="dialog"
    v-test="AdminComponent.Modal"
    :aria-labelledby="headingId"
    class="mypa-modal mypa-max-h-[calc(100vh-6rem)] mypa-w-[calc(100%-1rem)] mypa-max-w-2xl mypa-overflow-y-auto mypa-rounded mypa-border-0 mypa-bg-white mypa-p-0 mypa-shadow-lg"
    @click="onClick"
    @close="onClose">
    <div
      v-if="isOpen"
      class="mypa-flex mypa-flex-col mypa-p-6">
      <header class="mypa-mb-4 mypa-flex mypa-items-start mypa-justify-between mypa-gap-4">
        <PdkHeading
          :id="headingId"
          level="2">
          {{ translate(title) }}
        </PdkHeading>

        <button
          :aria-label="translate('action_close')"
          class="mypa-cursor-pointer mypa-border-0 mypa-bg-transparent mypa-p-1"
          type="button"
          @click="close">
          <PdkIcon icon="close" />
        </button>
      </header>

      <NotificationContainer :category="NotificationCategory.Modal" />

      <KeepAlive>
        <slot :context="context" />
      </KeepAlive>

      <footer
        v-if="actions.length > 0"
        class="mypa-mt-4 mypa-flex mypa-justify-end mypa-gap-2">
        <ActionButton
          v-for="(action, index) in actions"
          :key="`action_${action.id}_${index}`"
          :action="action" />
      </footer>
    </div>
  </dialog>
</template>

<script lang="ts">
// Each heading needs its own id for aria-labelledby. Vue 3.4 has no useId.
let headingCount = 0;
</script>

<script lang="ts" setup>
import {onMounted, ref, toRefs, watch} from 'vue';
import {
  ActionButton,
  AdminComponent,
  type ModalProps,
  type ModalSlots,
  NotificationCategory,
  NotificationContainer,
  useLanguage,
  useModalElementContext,
} from '@myparcel-dev/pdk-admin';

const props = withDefaults(defineProps<ModalProps>(), {
  actions: () => [],
  title: '',
});

defineSlots<ModalSlots>();

const propRefs = toRefs(props);

const {isOpen, context, close} = useModalElementContext(propRefs.modalKey.value);
const {translate} = useLanguage();

const headingId = `mypa-modal-heading-${++headingCount}`;
const dialog = ref<HTMLDialogElement>();

/**
 * Opens or closes the dialog when the PDK opens or closes the modal. A modal dialog makes
 * the page behind it inert, moves the focus into it, and gives the focus back on close.
 */
const syncDialog = () => {
  const element = dialog.value;

  if (!element) {
    return;
  }

  if (isOpen.value && !element.open) {
    element.showModal();
  } else if (!isOpen.value && element.open) {
    element.close();
  }
};

watch(isOpen, syncDialog, {flush: 'post'});
onMounted(syncDialog);

// The dialog also closes itself on Escape. Tell the PDK, unless the PDK closed it.
const onClose = () => {
  if (isOpen.value) {
    close();
  }
};

// The content fills the dialog, so a click on the dialog itself is a click on the backdrop.
const onClick = (event: MouseEvent) => {
  if (event.target === dialog.value) {
    close();
  }
};
</script>

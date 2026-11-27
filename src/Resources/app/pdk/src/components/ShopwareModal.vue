<template>
  <!-- A modal dialog is in the top layer, so it leaves the modals block below the order list grid. -->
  <!-- No display class here: it would also show the dialog while it is closed. -->
  <dialog
    ref="dialog"
    v-test="AdminComponent.Modal"
    :aria-labelledby="headingId"
    class="mypa-modal"
    @click="onClick"
    @close="onClose">
    <template v-if="isOpen">
      <header class="mypa-modal__header">
        <PdkHeading
          :id="headingId"
          class="mypa-modal__title"
          level="2">
          {{ translate(title) }}
        </PdkHeading>

        <button
          :aria-label="translate('action_close')"
          class="mypa-modal__close"
          type="button"
          @click="close">
          <PdkIcon icon="close" />
        </button>
      </header>

      <div class="mypa-modal__content">
        <NotificationContainer :category="NotificationCategory.Modal" />

        <KeepAlive>
          <slot :context="context" />
        </KeepAlive>
      </div>

      <footer
        v-if="actions.length > 0"
        class="mypa-modal__footer">
        <ActionButton
          v-for="(action, index) in actions"
          :key="`action_${action.id}_${index}`"
          :action="action" />
      </footer>
    </template>
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

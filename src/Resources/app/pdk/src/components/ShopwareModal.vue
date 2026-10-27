<template>
  <!-- The modals block sits below the order list grid, off screen. The dialog leaves it for the body, like the dropdown menu does. -->
  <Teleport
    v-if="isOpen"
    to="body">
    <div class="myparcel-pdk">
      <div
        class="mypa-fixed mypa-inset-0 mypa-z-[1000] mypa-bg-black mypa-bg-opacity-40"
        data-modal-backdrop
        role="none"
        @click="close" />

      <div
        v-test="AdminComponent.Modal"
        :aria-label="translate(title)"
        class="mypa-fixed mypa-inset-x-2 mypa-top-12 mypa-z-[1000] mypa-mx-auto mypa-flex mypa-max-h-[calc(100vh-6rem)] mypa-max-w-2xl mypa-flex-col mypa-overflow-y-auto mypa-rounded mypa-bg-white mypa-p-6 mypa-shadow-lg"
        aria-modal="true"
        role="dialog">
        <header class="mypa-mb-4 mypa-flex mypa-items-start mypa-justify-between mypa-gap-4">
          <PdkHeading level="2">{{ translate(title) }}</PdkHeading>

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
    </div>
  </Teleport>
</template>

<script lang="ts" setup>
import {onBeforeUnmount, onMounted, toRefs} from 'vue';
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

const onDocumentKeydown = (event: KeyboardEvent) => {
  if (isOpen.value && event.key === 'Escape') {
    close();
  }
};

onMounted(() => {
  document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

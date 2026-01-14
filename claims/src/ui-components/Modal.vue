<template>
  <div>
    <TransitionRoot :show="isOpen" as="template">
      <Dialog
        as="div"
        @close="disableBackdropClose ? () => {} : closeModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto"
      >
        <!-- Backdrop -->
        <div
          class="fixed inset-0 bg-gray-900/70 backdrop-filter backdrop-blur-sm"
          @click="handleBackdropClick"
        ></div>

        <!-- Modal Content -->
        <TransitionChild
          enter="ease-out duration-300"
          enter-from="opacity-0 scale-95"
          enter-to="opacity-100 scale-100"
          leave="ease-in duration-200"
          leave-from="opacity-100 scale-100"
          leave-to="opacity-0 scale-95"
        >
          <DialogPanel
            :class="[
              'relative flex flex-col h-full transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all',
              sizeClass,
            ]"
          >
            <!-- Modal Header -->
            <div
              :class="[
                headerColor ? headerColor : 'bg-gray-800',
                'px-4 py-3 border-b border-gray-200 flex justify-between items-center',
              ]"
            >
              <h3 class="text-lg font-medium text-gray-100">{{ title }}</h3>
              <button
                @click="closeModal"
                class="text-gray-200 hover:text-gray-50 text-xl"
              >
                ✖
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6">
              <slot name="body"> Default Body Content </slot>
            </div>

            <!-- Modal Footer -->
            <div
              v-if="$slots.footer"
              class="px-4 py-3 bg-gray-70 border-t border-gray-200 flex justify-end space-x-3"
            >
              <slot name="footer"></slot>
            </div>
          </DialogPanel>
        </TransitionChild>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { computed } from "vue";
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
} from "@headlessui/vue";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "Modal Title",
  },
  size: {
    type: String,
    default: "lg", // Options: "sm", "md", "lg", "xl", "full"
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true, // Controls whether clicking outside closes the modal
  },
  disableBackdropClose: {
    type: Boolean,
    default: false, // If true, clicking outside won't close the modal
  },
  headerColor: {
    type: String,
    default: null, // If not provided, fallback to default class
  },
});

const emit = defineEmits(["update:isOpen"]);

const sizeClass = computed(() => {
  const sizes = {
    sm: "max-w-md",
    md: "max-w-lg",
    lg: "max-w-2xl",
    xl: "max-w-4xl",
    full: "m-0 h-screen w-screen",
  };
  return sizes[props.size] || sizes.md;
});

const closeModal = () => {
  emit("update:isOpen", false);
};

const handleBackdropClick = () => {
  if (!props.disableBackdropClose) {
    closeModal();
  }
};
</script>

<style scoped>
.dialog-panel-centered {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
}
</style>

<template>
  <div>
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="modelValue" class="fixed inset-0 z-[9999] flex items-center justify-center">
          <!-- Backdrop -->
          <div 
            class="fixed inset-0 bg-gray-900/70 backdrop-filter backdrop-blur-sm overscroll-contain"
            @click="handleBackdropClick"
            @wheel.prevent="preventScroll"
            @touchmove.prevent="preventScroll"
            @scroll.prevent="preventScroll"
          ></div>
          
          <!-- Modal Content -->
          <Transition
            enter="ease-out duration-300"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="ease-in duration-200"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <div
              :class="[
                'relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all',
                sizeClass,
                props.size === 'full' ? 'h-screen flex flex-col' : 'max-h-[90vh] flex flex-col'
              ]"
            >
              <!-- Modal Body with Integrated Title and Subtitle -->
              <div class="flex-1 overflow-y-auto p-6">
                <!-- Title and Close Button Row -->
                <div class="flex justify-between items-center mb-4">
                  <div>
                    <h3 class="text-xl font-semibold text-gray-900">{{ title }}</h3>
                    <p v-if="subtitle" class="text-sm text-gray-500 -mt-1">{{ subtitle }}</p>
                  </div>
                  <button
                    @click="closeModal"
                    class="text-gray-500 hover:text-gray-700 text-xl"
                  >
                    ✖
                  </button>
                </div>
                
                <!-- Modal Content -->
                <slot></slot>
              </div>
              
              <!-- Modal Footer -->
              <div
                v-if="$slots.footer"
                class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3 shrink-0"
              >
                <slot name="footer"></slot>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, watch, onMounted, onUnmounted } from "vue";

// Variable to store the scroll position
let scrollPosition = 0;
// Variable to store the scrollbar width
let scrollbarWidth = 0;

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "Modal Title",
  },
  subtitle: {
    type: String,
    default: "",
  },
  size: {
    type: String,
    default: "md", // Options: "sm", "md", "lg", "xl", "full"
  },
  disableBackdrop: {
    type: Boolean,
    default: false, // If true, clicking outside won't close the modal
  },
  headerColor: {
    type: String,
    default: 'dark', // Kept for compatibility, but not used in new UI
  },
});

const emit = defineEmits(["update:modelValue", "close"]);

const sizeClass = computed(() => {
  const sizes = {
    sm: "max-w-md w-full",
    md: "max-w-lg w-full",
    lg: "max-w-2xl w-full",
    xl: "max-w-4xl w-full",
    full: "w-screen m-0",
  };
  return sizes[props.size] || sizes.md;
});

const closeModal = () => {
  emit("update:modelValue", false);
  emit("close");
};

const handleBackdropClick = () => {
  if (!props.disableBackdrop) {
    closeModal();
  }
};

// Function to calculate scrollbar width
const getScrollbarWidth = () => {
  const outer = document.createElement('div');
  outer.style.visibility = 'hidden';
  outer.style.overflow = 'scroll';
  outer.style.msOverflowStyle = 'scrollbar'; // For IE/Edge
  document.body.appendChild(outer);

  const inner = document.createElement('div');
  outer.appendChild(inner);

  const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;

  outer.parentNode.removeChild(outer);
  return scrollbarWidth;
};

// Prevent scroll events
const preventScroll = (event) => {
  event.preventDefault();
  event.stopPropagation();
};

// Lock scroll on the entire page
const lockScroll = () => {
  if (!scrollbarWidth) {
    scrollbarWidth = getScrollbarWidth();
  }
  scrollPosition = window.scrollY || window.pageYOffset;
  document.body.classList.add('modal-open');
  document.body.style.paddingRight = `${scrollbarWidth}px`;
  document.body.style.top = `-${scrollPosition}px`;
};

// Unlock scroll with a delay to match the closing transition
const unlockScroll = () => {
  // Match the duration of the modal's closing transition (200ms)
  setTimeout(() => {
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
    document.body.style.top = '';
    // Restore scroll position with smooth behavior
    window.scrollTo({
      top: scrollPosition,
      behavior: 'instant', // Use 'instant' to avoid animation during flicker
    });
  }, 200); // Delay matches the leave transition duration (200ms)
};

// Watch for modal visibility to toggle scroll lock
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      lockScroll();
    } else {
      unlockScroll();
    }
  }
);

// Ensure scroll is unlocked when component is unmounted
onUnmounted(() => {
  unlockScroll();
});

// Apply initial scroll lock if modal is open on mount
onMounted(() => {
  if (props.modelValue) {
    lockScroll();
  }
});
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Ensure modal sizing is responsive */
@media (max-width: 640px) {
  .max-w-md, .max-w-lg, .max-w-2xl, .max-w-4xl {
    max-width: 90% !important;
  }
}

/* Prevent scroll propagation on backdrop */
.overscroll-contain {
  overscroll-behavior: contain;
  touch-action: none;
}
</style>

<style>
/* Lock scroll when modal is open */
body.modal-open {
  position: fixed !important;
  width: 100% !important;
  overflow: hidden !important;
  height: 100vh !important;
}

/* Ensure no nested elements can scroll */
body.modal-open * {
  overflow: hidden !important;
}

/* Ensure only the modal body can scroll */
body.modal-open .flex-1.overflow-y-auto {
  overflow-y: auto !important;
}
</style>
<template>
  <div class="flex w-full h-full relative overflow-hidden" ref="containerRef">
    <!-- Left panel with transition and slot -->
    <div
      class="h-full overflow-auto flex flex-col transform transition-all duration-300 ease-out bg-white shadow-md"
      :class="{ 'translate-x-0': panelAnimationComplete, '-translate-x-full': !panelAnimationComplete }"
      :style="{ width: leftWidth + 'px' }"
      v-if="showPanel"
    >
      <slot name="left" :close="closePanel" />
    </div>

    <!-- Resizer handle -->
    <div
      class="resizer-container w-3 flex justify-center mr-1 ml-1 items-center bg-transparent relative z-10 transform transition-opacity duration-300 ease-out"
      :class="{ 'opacity-100': panelAnimationComplete, 'opacity-0': !panelAnimationComplete }"
      @mousedown="initResize"
      @touchstart="initResize" v-if="showPanel"
    >
      <div class="w-px h-full bg-gray-400 absolute"></div>
      <div class="w-5 h-16 bg-gray-100 flex justify-center items-center rounded-sm border border-gray-300 relative hover:bg-gray-200">
        <div class="flex flex-col items-center justify-center gap-1">
          <div class="h-1 w-1 rounded-full bg-gray-600"></div>
          <div class="h-1 w-1 rounded-full bg-gray-600"></div>
          <div class="h-1 w-1 rounded-full bg-gray-600"></div>
        </div>
      </div>
    </div>

    <!-- Right panel slot -->
    <div class="h-full overflow-auto flex-1">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  initialLeftWidth: {
    type: [String, Number],
    default: '400',
  },
  minLeftWidth: {
    type: Number,
    default: 200,
  },
  minRightWidth: {
    type: Number,
    default: 300,
  },
  // showOnMount: {
  //   type: Boolean,
  //   default: true,
  // },
});

const emit = defineEmits(['closed']);

const containerRef = ref(null);
const containerWidth = ref(0);
const leftWidth = ref(0);

const showPanel = defineModel('showPanel', { type: Boolean, default: true });
const panelAnimationComplete = ref(showPanel.value);

const isResizing = ref(false);
const startX = ref(0);
const startLeftWidth = ref(0);

watch(showPanel, (val) => {
  panelAnimationComplete.value = val;
});

const closePanel = () => {
  panelAnimationComplete.value = false;
  setTimeout(() => {
    showPanel.value = false;
  }, 300);
};

const openPanel = () => {
  showPanel.value = true;
  setTimeout(() => {
    panelAnimationComplete.value = true;
  },300);
};

// ⬇️ This makes `openPanel` and `closePanel` accessible to parent via ref
defineExpose({ openPanel, closePanel });
// Convert initialLeftWidth to px
const calculateInitialLeftWidth = () => {
  const container = containerRef.value;
  if (!container) return;

  containerWidth.value = container.clientWidth;

  if (typeof props.initialLeftWidth === 'string' && props.initialLeftWidth.includes('%')) {
    const percent = parseFloat(props.initialLeftWidth);
    leftWidth.value = Math.max(
      props.minLeftWidth,
      Math.min((percent / 100) * containerWidth.value, containerWidth.value - props.minRightWidth)
    );
  } else {
    leftWidth.value = Math.max(
      props.minLeftWidth,
      Math.min(parseInt(props.initialLeftWidth), containerWidth.value - props.minRightWidth)
    );
  }
};

const initResize = (event) => {
  if (!panelAnimationComplete.value) return;
  isResizing.value = true;
  startX.value = event.type === 'mousedown' ? event.clientX : event.touches[0].clientX;
  startLeftWidth.value = leftWidth.value;
  document.body.style.userSelect = 'none';

  event.preventDefault();
};

const handleMouseMove = (event) => {
  if (!isResizing.value) return;
  resize(event.clientX);
};

const handleTouchMove = (event) => {
  if (!isResizing.value) return;
  resize(event.touches[0].clientX);
};

const resize = (clientX) => {
  const dx = clientX - startX.value;
  let newLeftWidth = startLeftWidth.value + dx;
  if (newLeftWidth < props.minLeftWidth) newLeftWidth = props.minLeftWidth;
  else if (newLeftWidth > containerWidth.value - props.minRightWidth)
    newLeftWidth = containerWidth.value - props.minRightWidth;

  leftWidth.value = newLeftWidth;
};

const stopResize = () => {
  if (!isResizing.value) return;
  isResizing.value = false;
  document.body.style.userSelect = '';
};

// const closePanel = () => {
//   panelAnimationComplete.value = false;
//   setTimeout(() => {
//     showPanel.value = false;
//     emit('closed');
//   }, 300); // Matches transition
// };

onMounted(() => {
  calculateInitialLeftWidth();
  window.addEventListener('resize', calculateInitialLeftWidth);
  window.addEventListener('mousemove', handleMouseMove);
  window.addEventListener('mouseup', stopResize);
  window.addEventListener('touchmove', handleTouchMove, { passive: false });
  window.addEventListener('touchend', stopResize);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', calculateInitialLeftWidth);
  window.removeEventListener('mousemove', handleMouseMove);
  window.removeEventListener('mouseup', stopResize);
  window.removeEventListener('touchmove', handleTouchMove);
  window.removeEventListener('touchend', stopResize);
});
</script>

<style scoped>
/* .resizer-container {
  cursor: col-resize;
}

.resizer-container.resizing {
  cursor: col-resize;
} */
 .resizer-container {
  cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12h14'%3E%3C/path%3E%3Cpath d='M2 12l3 3'%3E%3C/path%3E%3Cpath d='M2 12l3-3'%3E%3C/path%3E%3Cpath d='M22 12l-3 3'%3E%3C/path%3E%3Cpath d='M22 12l-3-3'%3E%3C/path%3E%3C/svg%3E") 12 12, col-resize;
}

/* Cursor while actively resizing */
.resizer-container.resizing {
  cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12h14'%3E%3C/path%3E%3Cpath d='M2 12l3 3'%3E%3C/path%3E%3Cpath d='M2 12l3-3'%3E%3C/path%3E%3Cpath d='M22 12l-3 3'%3E%3C/path%3E%3Cpath d='M22 12l-3-3'%3E%3C/path%3E%3C/svg%3E") 12 12, col-resize;
}

/* Styles for active resizing */
.resizer-container.resizing .border {
  @apply bg-blue-100 border-blue-400;
}

.resizer-container:hover .border {
  @apply bg-gray-200;
}
</style>

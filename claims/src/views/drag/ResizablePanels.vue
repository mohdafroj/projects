<template>
  <div>
    <!-- Only Right Panel visible initially -->
    <div v-if="!showSplitPanel" class="h-full w-full">
      <RightPanelComponent @add-note="enableSplitPanel" />
    </div>

    <!-- Split Panel with Notes and Right Panel - only shown after clicking Add Note -->
    <div
      v-else
      class="flex w-full h-full relative overflow-hidden"
      ref="containerRef"
    >
      <!-- Left panel (Notes) with slide-in animation -->
      <div
        class="h-full overflow-auto flex flex-col transform transition-all duration-300 ease-out"
        :class="{
          'translate-x-0': panelAnimationComplete,
          '-translate-x-full': !panelAnimationComplete,
        }"
        :style="{ width: leftWidth + 'px' }"
      >
        <NoteSection />
        <div class="flex-1 overflow-auto p-2.5">
          <!-- Notes content -->
        </div>
      </div>

      <!-- Resizer handle with custom cursor -->
      <div
        class="resizer-container w-3 flex justify-center items-center bg-transparent relative z-10 transform transition-opacity duration-300 ease-out"
        :class="{
          'opacity-100': panelAnimationComplete,
          'opacity-0': !panelAnimationComplete,
        }"
        @mousedown="initResize"
        @touchstart="initResize"
      >
        <!-- Solid handle with center line for better visibility -->
        <div class="w-px h-full bg-gray-400 absolute"></div>
        <div
          class="w-5 h-16 bg-gray-100 flex justify-center items-center rounded-sm border border-gray-300 relative hover:bg-gray-200"
        >
          <!-- Custom handle icon that looks like ⋮⋮ but more visible -->
          <div class="flex flex-col items-center justify-center gap-1">
            <div class="h-1 w-1 rounded-full bg-gray-600"></div>
            <div class="h-1 w-1 rounded-full bg-gray-600"></div>
            <div class="h-1 w-1 rounded-full bg-gray-600"></div>
          </div>
        </div>
      </div>

      <!-- Right panel (Claim Details) - Modified to fill available space -->
      <div
        class="h-full overflow-auto flex-1 transform transition-all duration-300 ease-out"
        :class="{ 'translate-x-0': panelAnimationComplete }"
        :style="{ minWidth: minRightWidth + 'px' }"
      >
        <!-- Use a container div inside to apply proper padding and scrolling -->
        <RightPanelComponent @add-note="enableSplitPanel" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import NoteSection from "@/views/drag/NoteSection.vue";
import RightPanelComponent from "@/views/drag/RightPanelComponent.vue";

// State to control the visibility of the split panel
const showSplitPanel = ref(false);
const panelAnimationComplete = ref(false);

// Panel size state
const containerRef = ref(null);
const containerWidth = ref(0);
const leftWidth = ref(550); // Initial width of left panel
const minLeftWidth = 200;
const minRightWidth = 600;

// Resizing state
const isResizing = ref(false);
const startX = ref(0);
const startLeftWidth = ref(0);

// Function to enable split panel when "Add Note" is clicked
const enableSplitPanel = () => {
  showSplitPanel.value = true;

  // Trigger animation after a small delay to ensure the DOM is updated
  setTimeout(() => {
    panelAnimationComplete.value = true;

    // Apply a small delay before calculating widths to ensure the DOM is updated
    setTimeout(() => {
      calculateInitialWidths();
    }, 300); // Match duration of the animation
  }, 50);
};

// Calculate initial widths
const calculateInitialWidths = () => {
  if (containerRef.value) {
    containerWidth.value = containerRef.value.clientWidth;
  }
};

// Initialize resize
const initResize = event => {
  if (!panelAnimationComplete.value) return;

  isResizing.value = true;
  startX.value =
    event.type === "mousedown" ? event.clientX : event.touches[0].clientX;
  startLeftWidth.value = leftWidth.value;

  // Prevent text selection during resize
  document.body.style.userSelect = "none";

  // Add active class to resizer
  const resizer = event.target.closest(".resizer-container");
  if (resizer) resizer.classList.add("resizing");

  // Prevent default to avoid issues on touch devices
  event.preventDefault();
};

// Handle mouse move during resize
const handleMouseMove = event => {
  if (!isResizing.value) return;
  resize(event.clientX);
};

// Handle touch move during resize
const handleTouchMove = event => {
  if (!isResizing.value) return;
  resize(event.touches[0].clientX);
};

// Resize logic
const resize = clientX => {
  const dx = clientX - startX.value;
  let newLeftWidth = startLeftWidth.value + dx;

  // Apply constraints
  if (newLeftWidth < minLeftWidth) {
    newLeftWidth = minLeftWidth;
  } else if (newLeftWidth > containerWidth.value - minRightWidth) {
    newLeftWidth = containerWidth.value - minRightWidth;
  }

  leftWidth.value = newLeftWidth;
};

// Stop resizing
const stopResize = () => {
  if (!isResizing.value) return;

  isResizing.value = false;
  document.body.style.userSelect = "";

  // Remove active class from resizer
  const resizer = document.querySelector(".resizer-container.resizing");
  if (resizer) resizer.classList.remove("resizing");
};

// Lifecycle hooks
onMounted(() => {
  window.addEventListener("resize", calculateInitialWidths);
  window.addEventListener("mousemove", handleMouseMove);
  window.addEventListener("mouseup", stopResize);
  window.addEventListener("touchmove", handleTouchMove, { passive: false });
  window.addEventListener("touchend", stopResize);
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", calculateInitialWidths);
  window.removeEventListener("mousemove", handleMouseMove);
  window.removeEventListener("mouseup", stopResize);
  window.removeEventListener("touchmove", handleTouchMove);
  window.removeEventListener("touchend", stopResize);
});
</script>

<style scoped>
/* Define the custom cursor using a data URI */
.resizer-container {
  cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12h14'%3E%3C/path%3E%3Cpath d='M2 12l3 3'%3E%3C/path%3E%3Cpath d='M2 12l3-3'%3E%3C/path%3E%3Cpath d='M22 12l-3 3'%3E%3C/path%3E%3Cpath d='M22 12l-3-3'%3E%3C/path%3E%3C/svg%3E")
      12 12,
    col-resize;
}

/* Cursor while actively resizing */
.resizer-container.resizing {
  cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12h14'%3E%3C/path%3E%3Cpath d='M2 12l3 3'%3E%3C/path%3E%3Cpath d='M2 12l3-3'%3E%3C/path%3E%3Cpath d='M22 12l-3 3'%3E%3C/path%3E%3Cpath d='M22 12l-3-3'%3E%3C/path%3E%3C/svg%3E")
      12 12,
    col-resize;
}

/* Styles for active resizing */
.resizer-container.resizing .border {
  @apply bg-blue-100 border-blue-400;
}

.resizer-container:hover .border {
  @apply bg-gray-200;
}
</style>

<template>
  <ChartHead />

  <div class="grid grid-cols-12 gap-4 my-4">
    <div class="col-span-8">
      <!-- Settings -->
      <div class="py-3 flex justify-end">
        <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center font-medium animate-spin-slow">
          <Icon
            icon="heroicons:cog-6-tooth"
            class="inline-block text-xl cursor-pointer"
            @click="openModal"
          />
        </span>
      </div>

      <!-- Modal Component -->
      <DasboardSettings 
        :isOpen="isModalOpen" 
        @update:isOpen="isModalOpen = $event" 
        @update:options="updateSelectedOptions" 
      />

      <!-- Dashboard Cards -->
       <!-- <component 
        v-for="component in selectedComponents" 
        :key="component" 
        :is="loadComponent(component)" 
      />  -->
      <draggable 
  v-model="selectedComponents" 
  @end="onDragEnd"
  class="grid grid-cols-1 gap-4"
  item-key="id"
>
  <template #item="{ element }">
    <component 
      :is="loadComponent(element)" 
      :key="element" 
    />
  </template>
</draggable>

    </div>

    <div class="col-span-4">
      <Card>
        Sidebar Content Here
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, defineAsyncComponent, watch} from "vue";
import Icon from "@/ui-components/Icon.vue";
import ChartHead from "./charts/ChartHead.vue";
import DasboardSettings from "./DasboardSettings.vue";
import Card from "@/ui-components/Card.vue";
import { VueDraggableNext } from 'vue-draggable-next';

// Modal state
const isModalOpen = ref(false);

// Track selected components
const selectedComponents = ref(['attendance']); // Default to only show attendance

// Open modal
const openModal = () => {
  console.log("Opening modal...");
  isModalOpen.value = true;
};

// Update selected options from the modal
const updateSelectedOptions = (options) => {
  selectedComponents.value = options;
};

// Function to dynamically import components
const loadComponent = (componentName) => {
  switch (componentName) {
    case 'attendance':
      return defineAsyncComponent(() => import('./dynamic-cards/attendance.vue'));
    case 'card2':
      return defineAsyncComponent(() => import('./dynamic-cards/card2.vue'));
    case 'card3':
      return defineAsyncComponent(() => import('./dynamic-cards/card3.vue'));
    default:
      return null;
  }
};

const onDragEnd = (event) => {
  console.log("Drag ended:", event);
};
watch(selectedComponents, (newValue) => {
  console.log("Updated components:", newValue);
});
</script>

<template>
  <ChartHead />

  <div class="grid grid-cols-12 gap-4 my-4">
    <div class="col-span-8">
      <!-- ⚙️ Settings Icon -->
      <div class="py-3 flex justify-end">
        <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center font-medium animate-spin-slow">
          <Icon
            icon="heroicons:cog-6-tooth"
            class="inline-block text-xl cursor-pointer"
            @click="openModal"
          />
        </span>
      </div>

      <!--  Modal Component -->
      <DasboardSettings 
        :isOpen="isModalOpen" 
        @update:isOpen="isModalOpen = $event" 
        @update:options="updateSelectedOptions" 
      />

      <!--  Draggable Cards with Drag Handle  :title="item.name"  -->
      <VueDraggable 
        v-model="selectedComponents" 
        item-key="id" 
        class="mt-4 p-4" 
        :handle="'.drag-handle'"
      >
        <Card 
          v-for="item in selectedComponents" 
          :key="item.id" 
         
          class="pb-4 relative"
        >
          <!--  Drag Handle Icon (Only This Triggers Drag) <Icon icon="iconoir:drag" width="24" height="24" />-->
          <span class="drag-handle absolute top-2 right-2 cursor-grab">
            <Icon icon="iconoir:drag" class="text-xl text-gray-500 hover:text-gray-700" />
          </span>

          <!-- Render the actual dynamic component inside Card -->
          <component :is="item.component" />
        </Card>
      </VueDraggable>
    </div>

    <div class="col-span-4">
      <Card>
        Sidebar Content Here
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, markRaw } from "vue";
import { VueDraggable } from "vue-draggable-plus"; // ✅ Correct Import
import Icon from "@/ui-components/Icon.vue";
import ChartHead from "./charts/ChartHead.vue";
import DasboardSettings from "./DasboardSettings.vue";
import Card from "@/ui-components/Card.vue";
import attendance from "./dynamic-cards/attendance.vue";
import card2 from "./dynamic-cards/card2.vue";
import card3 from "./dynamic-cards/card3.vue";

//  Modal state
const isModalOpen = ref(false);

//  Track selected dynamic components (Now using `markRaw()`)
const selectedComponents = ref([
  { id: 1, name: "Attendance", component: markRaw(attendance) },
  { id: 2, name: "Card 2", component: markRaw(card2) },
  { id: 3, name: "Card 3", component: markRaw(card3) }
]); 

//  Open modal function
const openModal = () => {
  console.log("Opening modal...");
  isModalOpen.value = true;
};

// Update selected options from modal
const updateSelectedOptions = (options) => {
  selectedComponents.value = options.map(option => ({
    ...option,
    component: markRaw(option.component) // Apply `markRaw` when updating
  }));
};

// Handle drag end event
const onDragEnd = (event) => {
  console.log("Drag ended:", event);
};

//  debug
watch(selectedComponents, (newValue) => {
  console.log("Updated components:", newValue);
});
</script>

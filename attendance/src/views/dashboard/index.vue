<template>
 <div class="font-semibold text-blue-800 text-2xl text-center">Welcome User !</div>
</template>

<script setup>
import { ref, watch, markRaw } from "vue";
import { VueDraggable } from "vue-draggable-plus";
import Icon from "@/ui-components/Icon.vue";
import ChartHead from "./charts/ChartHead.vue";
import DasboardSettings from "./DasboardSettings.vue";
import Card from "@/ui-components/Card.vue";
import attendance from "./dynamic-cards/attendance.vue";
import card2 from "./dynamic-cards/card2.vue";
import card3 from "./dynamic-cards/card3.vue";
import SessionSechedule from "./dynamic-cards/SessionSechedule.vue";
import Button from "@/ui-components/Button.vue";
import CardSlider from "./CardSlider.vue";

//  Modal State
const isModalOpen = ref(false);

//  Define All Available Components
const allComponents = ref([
  { id: "attendance", name: "Attendance", component: markRaw(attendance) },
  {
    id: "session-schedule",
    name: "Session Schedule",
    component: markRaw(SessionSechedule),
  },
  { id: "card2", name: "Card 2", component: markRaw(card2) },
  { id: "card3", name: "Card 3", component: markRaw(card3) },
]);

//  Track Selected Components (Initially All are Selected)
const selectedComponentIds = ref([
  "attendance",
  "session-schedule",
  "card2",
  "card3",
]); // Store selected values
const selectedComponents = ref(
  allComponents.value.filter(c => selectedComponentIds.value.includes(c.id)),
);

//  Generate JSON Based on Current Selection & Order
const generateDashboardConfig = () => {
  return {
    userId: "12345", // Replace with actual user ID
    selectedCards: selectedComponents.value.map((card, index) => ({
      id: card.id,
      name: card.name,
      position: index + 1, // 1-based index for position
    })),
  };
  console.log(" Generated JSON:", JSON.stringify(dashboardConfig, null, 2)); // ✅
};

// 🏆 Open Modal Function
const openModal = () => {
  console.log("Opening modal...");
  isModalOpen.value = true;
};

//  Update Selected Options from Modal
const updateSelectedOptions = selectedValues => {
  selectedComponentIds.value = selectedValues;
  selectedComponents.value = allComponents.value.filter(component =>
    selectedValues.includes(component.id),
  );

  // Log the updated JSON after selection changes
  console.log(
    "Updated JSON:",
    JSON.stringify(generateDashboardConfig(), null, 2),
  );
};

//  Update JSON on Drag End
const onDragEnd = () => {
  console.log("Drag ended. New order:");
  console.log(JSON.stringify(generateDashboardConfig(), null, 2));
};

//  debug
watch(selectedComponents, newValue => {
  console.log("Updated Components:", newValue);
});
</script>

<template>
  <Modal :isOpen="isOpen" title="Customize your dashboard" size="lg" centered headerColor="bg-purple-700" @update:isOpen="$emit('update:isOpen', $event)">
    <template #body>
      <form @submit.prevent="updateProject" class="space-y-8">
        <div class="p-4 bg-violet-100 border-l-4 border-purple-500 mb-5">
          <p class="text-gray-700 text-lg font-medium">
            <strong>Note:</strong> Customize your dashboard to perfectly suit your needs. Select the specific items you want to see and save your preferences for future visits.
          </p>
        </div>

        <div class="flex flex-col space-y-2">
          <div v-for="option in options" :key="option.id" class="flex items-center space-x-2">
            <input 
              type="checkbox" 
              :id="option.id"
              :value="option.id" 
              :checked="selectedOptions.includes(option.id)"
              @change="toggleSelection(option.id)"
              class="w-10 h-10  border-red-300 rounded"
            />
            <label :for="option.id" class="text-gray-600 text-xl  cursor-pointer">{{ option.label }}</label>
          </div>
        </div>

        <div class="text-gray-600 dark:text-white my-[30px]">
          Selected: {{ selectedOptions }}
        </div>
        <Button type="submit" class="btn-primary float-right">Update</Button>
      </form>
    </template>
  </Modal>
</template>

<script setup>
import { ref, watch } from "vue";
import Button from "@/ui-components/Button.vue";
import Modal from "@/ui-components/Modal.vue";


const props = defineProps({
  isOpen: Boolean,
  selected: Array, 
  selectedComponents: Array 
});

const emit = defineEmits(["update:isOpen", "update:options"]);


const selectedOptions = ref([...props.selected]);


watch(() => props.selected, (newVal) => {
  selectedOptions.value = [...newVal];
}, { immediate: true });


const options = ref([
  { id: "attendance", value: "attendance", label: "Session Attendance" },
  { id: "card2", value: "card2", label: "Card 2 Description" },
  { id: "card3", value: "card3", label: "Card 3 Description" },
]);


const toggleSelection = (id) => {
  if (selectedOptions.value.includes(id)) {
    selectedOptions.value = selectedOptions.value.filter(item => item !== id);
  } else {
    selectedOptions.value.push(id);
  }
};

// Generate JSON and Update Parent
const updateProject = () => {
  console.log("Updated project with options:", selectedOptions.value);

  // Generate JSON with Order and Names
  const dashboardConfig = {
    userId: "12345", 
    selectedCards: selectedOptions.value.map((cardId, index) => {
      
      const foundCard = props.selectedComponents.find(c => c.id === cardId);
      return {
        id: cardId,
        name: foundCard ? foundCard.name : "", 
        position: index + 1, 
      };
    }),
  };

  console.log("🔥 Generated JSON:", JSON.stringify(dashboardConfig, null, 2));

  
  emit("update:options", selectedOptions.value);
  emit("update:isOpen", false); 
};
</script>


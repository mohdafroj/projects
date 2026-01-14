<template>
  <div class="overflow-hidden" >
    <div
      @click="isOpen = !isOpen"
      class="flex justify-between w-full text-left border-md cursor-pointer"
    >
    
      <h5 class="text-xs font-bold">
        Claim Detail : <span class="text-blue-600">#{{ claim.id }}</span> :
        <span class="font-bold uppercase underline">{{ claim.name }}</span>
      </h5>
     
      <Icon
        icon="mdi:chevron-down"
        class="transition-transform duration-300"
        :class="{ 'rotate-180': isOpen }"
        width="24"
        height="24"
      />
    </div>
    
    <div v-if="isOpen" class="mt-2">
     
      <ClaimDataTable :addNote="addNote" />
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Icon } from "@iconify/vue";
import ClaimDataTable from "./ClaimDataTable.vue";

const props = defineProps({
  claim: Object,
  addNote: Function
});
const isOpen = ref(true);
</script>

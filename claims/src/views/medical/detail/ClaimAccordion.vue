<template>
  <div class="overflow-hidden" >
    <div
      @click="isOpen = !isOpen"
      class="flex justify-between w-full text-left border-md "
    >
    
      <h5>
        Claim Detail : <span class="text-blue-600">#{{ claim.id }}</span> :
        <span class="font-bold uppercase">{{ claim.name }}</span>
      </h5>
      <Icon
        icon="mdi:chevron-down"
        class="transition-transform duration-300"
        :class="{ 'rotate-180': isOpen }"
        width="24"
        height="24"
      />
    </div>
    
    <div v-if="isOpen" class="bg-white mt-2">
     
      <ClaimDataTable :items="claim.items" />
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Icon } from "@iconify/vue";
import ClaimDataTable from "./ClaimDataTable.vue";

const props = defineProps({
  claim: Object,
});

const isOpen = ref(false);
</script>

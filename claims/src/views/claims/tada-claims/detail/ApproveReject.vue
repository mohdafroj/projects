<template>
<!-- APPROVAL BANNER -->
  <div v-if="apiStore.tada_claim?.detail.status?.toLowerCase() == 'approved'" class="max-w-6xl">
    <div
      class="flex items-center gap-6 rounded-xl px-8 py-6
             bg-gradient-to-r from-green-700 via-green-600 to-green-500
             shadow-lg text-white"
    >
      <!-- LEFT ICON -->
      <div class="relative flex-shrink-0">
        <div
          class="w-28 h-28 rounded-full bg-white
                 flex items-center justify-center shadow-inner"
        >
          <!-- Document Icon -->
            <img src="./../../../../assets/images/check.gif" width="150" style="border-radius: 100px;">
        </div>
      </div>
      <!-- RIGHT CONTENT -->
      <div class="flex-1">
        <p class="text-xl font-semibold leading-snug">
          The claim has been approved by
          <span class="font-bold">{{ apiStore.tada_claim?.detail?.approval_data?.actor_name }}</span>
        </p>
        <p class="mt-1 text-lg font-medium">
          on <span class="font-bold">{{ dateFormated(apiStore.tada_claim?.detail?.approval_data?.created_at) }}</span> at
          <span class="font-bold">{{ timeFormated(apiStore.tada_claim?.detail?.approval_data?.created_at) }}</span>
        </p>
        <div class="mt-4 border-t border-white/40 pt-2">
          <p class="italic text-lg font-semibold">
            Digitally Signed
          </p>
        </div>
      </div>
    </div>
  </div>
<!--Approval End-->
  <!-- REJECTION BANNER -->
  <div v-if="apiStore.tada_claim?.detail.status?.toLowerCase() == 'rejected'" class="max-w-6xl">
    <div
      class="flex items-center gap-8 rounded-xl px-10 py-6
             bg-gradient-to-r from-red-800 via-red-600 to-red-500
             shadow-lg text-white"
    >
      <!-- LEFT ICON -->
      <div class="relative flex-shrink-0">
        <!-- Outer Circle -->
        <div
          class="w-28 h-28 rounded-full bg-white
                 flex items-center justify-center shadow-inner"
        >
          <!-- Document Icon -->
           <img src="./../../../../assets/images/rej.gif" width="170" style="border-radius: 100px;">
        </div>
      </div>
      <!-- RIGHT CONTENT -->
      <div class="flex-1">
        <p class="text-xl font-semibold leading-snug">
          The claim has been rejected by
          <span class="font-bold">{{ apiStore.it_equipment_final_data?.actor_name }}</span>
        </p>
        <p class="mt-1 text-lg font-medium">
          on <span class="font-bold">{{ dateFormated(apiStore.it_equipment_final_data?.created_at) }}</span>
          at <span class="font-bold">{{ timeFormated(apiStore.it_equipment_final_data?.created_at) }}</span>
        </p>
        <!-- Divider -->
        <div class="mt-4 border-t border-white/40 pt-2">
          <p class="italic text-xl font-semibold">
            Digitally Signed
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
    import { onMounted } from 'vue';
    import { useApiStore } from '@/store/apiData';
    import { timeFormated, dateFormated } from '@/utils/dateFormat';
    const apiStore = useApiStore();

</script>
<template>
<div class="relative min-h-64 mb-2 bg-white rounded-2xl shadow-xl outline outline-1 outline-slate-200 overflow-hidden">
  <div class="h-2 w-full bg-gradient-to-r from-[#8e2f2f] to-indigo-600"></div>

  <!-- Header -->
  <div class="flex items-center h-12 bg-slate-100 px-6">
    <h2 class="text-[18px] font-bold text-slate-900">Leave Details</h2>
  </div>

  <!-- Body -->
  <div class="flex gap-3 px-2 pt-4">

    <!-- Profile Image -->
    <img
      class="w-[139px] h-[165px] rounded-lg shadow-md object-cover"
      :src="leaveData?.profile_photo || 'https://placehold.co/139x165'"
    />

    <!-- Right Content -->
    <div class="flex flex-col flex-1 gap-1">

      <!-- Name -->
      <div class="text-lg font-bold text-[#8e2f2f]">
        {{ leaveData?.name || '' }}
      </div>

      <!-- Meta Info -->
      <div class="grid grid-cols-2 gap-y-1 text-base">
        <div class="text-slate-400">Request ID</div>
        <div class="text-slate-900">{{ leaveData?.request_id || '' }}</div>

        <div class="text-slate-400">Applied On</div>
        <div class="text-slate-900">{{ leaveData?.created_at || '' }}</div>
      </div>

      <!-- Leave Period Card -->
      <div class="my-2 grid grid-cols-3 rounded-lg bg-blue-50/50 border border-blue-100 p-2 flex flex-col">

        <div class="col-span-2">
          <div class="flex-1 text-center text-[#8e2f2f] font-medium">Leave Period</div>
          <div class="flex-1 text-center text-slate-900">
            {{ leaveData?.start_date || '' }} –
            {{ leaveData?.end_date || '' }}
          </div>
        </div>

        <div class="items-center">
          <div class="flex-1 text-center text-[#8e2f2f] font-medium">Total Leave</div>
          <div class="flex-1 text-center text-slate-900">
            {{ leaveData?.total_days || '0' }} Days
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<div class="relative min-h-64 pb-2 bg-white rounded-2xl shadow-xl outline outline-1 outline-slate-200 overflow-hidden">
  <div class="grid grid-cols-2 gap-2 flex items-start p-2">
    <div class="w-full flex items-center justify-center mt-2">
      <div v-if="leaveData?.request_receipt">Request Receipt</div>
    </div>
    <div class="w-full flex items-center justify-center mt-2">
      <div v-if="leaveData?.approved_receipt">Approved Receipt</div>
    </div>
    <div class="w-full h-[40vh] flex items-center justify-center">
        <img
          v-if="isImage(leaveData?.request_receipt)"
          :src="leaveData?.request_receipt"
          class="w-full h-full object-contain"
        />
        <object
          v-else
          :data="leaveData?.request_receipt"
          type="application/pdf"
          class="w-full h-full object-contain"
        />
    </div>

    <div class="w-full h-[40vh] flex items-center justify-center">
        <img
          v-if="isImage(leaveData?.approved_receipt)"
          :src="leaveData?.approved_receipt"
          class="w-full h-full object-contain"
        />
        <object
          v-else
          :data="leaveData?.approved_receipt"
          type="application/pdf"
          class="w-full h-full object-contain"
        />
    </div>
    <div class="w-full flex items-center justify-center mt-2">
      <Button v-if="leaveData?.request_receipt" label="Download" size="sm" color="green-outline" @click="() => downloadFileByUrl(leaveData?.request_receipt)" />
    </div>
    <div class="w-full flex items-center justify-center mt-2">
      <Button v-if="leaveData?.approved_receipt" label="Download" size="sm" color="green-outline" @click="() => downloadFileByUrl(leaveData?.approved_receipt)" />
    </div>
  </div>
</div>
</template>

<script setup>
import { leaveRequestDetail } from '@/services/attendanceService';
import { downloadFileByUrl, isImage } from '@/utils/downloads';
import { Button } from '@sds/oneui-common-ui';
import { ref, watch } from 'vue';

const props = defineProps({
    leaveId: {
        type:Number,
        default: 0
    }
});
const leaveData = ref({});
const fetchLeaveRequestDataById = async (id) => {
  const response = await leaveRequestDetail(id);
  if ( response.isError == false && response.success_code == 200) {
   leaveData.value = response.data[0] || {};
  }
};

watch(
    () => props.leaveId, 
    (newId) => {
    if ( newId ) fetchLeaveRequestDataById(newId);
}, {immediate:true})

</script>
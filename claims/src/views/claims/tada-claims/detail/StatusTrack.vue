<template>
  <div class="p-4 w-full h-full mb-5">
    <div class="flex items-center justify-between mb-4">
      <h5 class="font-medium">{{ title }}</h5>
      <div class="flex space-x-2">
        <button
          class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition"
          @click="$emit('viewHistoryDetails', $event)"
        >
          <Icon
            icon="heroicons:arrow-path-solid"
            width="16"
            height="16"
            class="mr-2"
            style="color: #8bea60"
          />
          <span class="text-sm">{{ historyLabel }}</span>
        </button>
        <button
          class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition"
          @click="$emit('refresh', $event)"
        >
          <Icon
            icon="heroicons:arrow-path-solid"
            width="16"
            height="16"
            class="mr-2"
            style="color: #8bea60"
          />
          <span class="text-sm">{{ refreshLabel }}</span>
        </button>
      </div>
    </div>
    <!-- Timeline Steps -->
    <div class="flex justify-between items-start relative pt-4 w-full">
      <div
        v-for="(stage, index) in stages"
        :key="stage.id"
        class="relative flex flex-col items-center text-center w-1/4"
      >
        <!-- <div
          v-if="stage.delay && stage.delay > 0"
          class="absolute top-14 text-xs text-red-500 -left-12 whitespace-nowrap"
        >
          Delayed by {{ stage.delay }} day{{ stage.delay > 1 ? 's' : '' }}
        </div> -->
        <!-- Dashed Connector -->
        <div
          v-if="index < stages.length - 1 && showDashedLine[index]"
          class="absolute top-20 z-0 border-dashed border-gray-300"
          style="border-top-width: 4px !important;"
          :style="{
            left: 'calc(50% + 24px)',
            width: 'calc(100% - 48px)',
          }"
        />
        <!-- Green Connector (Animates Over Dashed Line) -->
        <div
          v-if="index < stages.length - 1 && connectorStyles[index]"
          class="absolute z-1 green-connector"
          style="height: 4px;"
          :style="{
            left: 'calc(50% + 24px)',
            width: 'calc(100% - 48px)',
            backgroundColor: '#22c55e',
            top: '5rem',
          }"
          :class="connectorStyles[index]"
        />
        <!-- Step Title -->
        <div class="font-semibold text-gray-700 mb-1">{{ stage.name }}</div>
        <!-- Status Badge -->
        <div
          class="text-xs px-2 py-0.5 rounded-full mb-2"
          :class="{
            'bg-green-100 text-green-700': stage.status === 'success' && !stage.isRejected && !stage.isReturned && stageAnimationComplete[index],
            'bg-red-100 text-red-700': (stage.status === 'success' && stage.isRejected) || (stage.status === 'success' && stage.isReturned) && stageAnimationComplete[index],
            'bg-gray-200 text-gray-500': stage.status === 'pending' && stageAnimationComplete[index],
            'invisible': !stageAnimationComplete[index]
          }"
        >
          {{ formatStatus(stage.status, stage.isRejected, stage.isReturned) }}
        </div>
        <!-- Step Circle -->
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold z-10 border-4 shadow-md"
          :class="[stageStyles[index] || 'bg-gray-200 text-gray-500 border-gray-300']"
        >
          <template v-if="stage.status === 'success' && stage.isRejected && stageAnimationComplete[index]">
            <!-- Rejected X icon  -->
            <Icon
              icon="mdi:close"
              width="28"
              height="28"
              style="color: #dc2626"
            />
          </template>
          <template v-else-if="stage.status === 'success' && stage.isReturned && stageAnimationComplete[index]">
            <!-- Returned back arrow icon  -->
            <Icon
              icon="mdi:arrow-left"
              width="28"
              height="28"
              style="color: #dc2626"
            />
          </template>
          <template v-else-if="stage.status === 'success' && !stage.isRejected && !stage.isReturned && stageStyles[index]?.includes('border-green-500')">
            <!-- success check icon -->
            <Icon
              icon="weui:done-outlined"
              width="28"
              height="28"
              style="color: #50cd1b"
            />
          </template>
          <template v-else>
            {{ index + 1 }}
          </template>
        </div>
     
        <div 
          class="text-xs text-gray-500 mt-1"
          :class="{
            'invisible': !stage.date || !stageAnimationComplete[index]
          }"
        >
          {{ stage.date ? formatDate(stage.date) : '&nbsp;' }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { defineProps, ref, onMounted, watch } from "vue";

const props = defineProps({
  title: String,
  stages: Array,
  successLabel: {
    type: String,
    default: "Success",
  },
  pendingLabel: {
    type: String,
    default: "Pending",
  },
  rejectedLabel: {
    type: String,
    default: "Rejected",
  },
  returnedLabel: {
    type: String,
    default: "Returned",
  },
  refreshLabel: {
    type: String,
    default: "Refresh",
  },
  historyLabel: {
    type: String,
    default: "View Status History",
  },
});

const showGlow = ref(false);
const connectorStyles = ref({});
const showDashedLine = ref({});
const stageStyles = ref({});
const stageAnimationComplete = ref({});
const firstPendingIndex = ref(-1);

// reset and re-run animations
const runAnimations = () => {
  // Reset animation states
  showGlow.value = false;
  connectorStyles.value = {};
  showDashedLine.value = {};
  stageStyles.value = {};
  stageAnimationComplete.value = {};
  firstPendingIndex.value = -1;

  for (let i = 0; i < props.stages.length - 1; i++) {
    showDashedLine.value[i] = true;
  }


  for (let i = 0; i < props.stages.length; i++) {
    stageStyles.value[i] = "bg-gray-200 text-gray-500 border-gray-300";
    stageAnimationComplete.value[i] = false;
  }

  let lastSuccessIndex = -1;
  let firstPendingIdx = -1;

  for (let i = 0; i < props.stages.length; i++) {
    if (props.stages[i].status === "success") {
      lastSuccessIndex = i;
    } else if (props.stages[i].status === "pending" && firstPendingIdx === -1) {
      firstPendingIdx = i;
      firstPendingIndex.value = i;
      break;
    }
  }

  stageStyles.value[0] = "border-green-500 text-green-500 shadow-green-400 animate-glow-temp";
  stageAnimationComplete.value[0] = true; 
  setTimeout(() => {
 
    stageStyles.value[0] = "border-green-500 text-green-500 shadow-green-400";
  }, 500);


  let delay = 500; 


  for (let i = 1; i <= lastSuccessIndex; i++) {
    setTimeout(() => {
      
      connectorStyles.value[i - 1] = "animate-grow-line";
      setTimeout(() => {
        showDashedLine.value[i - 1] = false;
        
        if (props.stages[i].isRejected) {
          
          stageStyles.value[i] =
            "border-red-500 text-red-500 shadow-red-400 animate-glow-temp-red";
          setTimeout(() => {
            
            stageStyles.value[i] = "border-red-500 text-red-500 shadow-red-400";
          }, 500);
        } else if (props.stages[i].isReturned) {
         
          stageStyles.value[i] =
            "border-red-500 text-red-500 shadow-red-400 animate-glow-temp-red";
          setTimeout(() => {
           
            stageStyles.value[i] = "border-red-500 text-red-500 shadow-red-400";
          }, 500);
        } else {
         
          stageStyles.value[i] =
            "border-green-500 text-green-500 shadow-green-400 animate-glow-temp";
          setTimeout(() => {
           
            stageStyles.value[i] = "border-green-500 text-green-500 shadow-green-400";
          }, 500);
        }
       
        stageAnimationComplete.value[i] = true;
      }, 1000); 
    }, delay);
    delay += 1500; 
  }


  if (firstPendingIdx !== -1) {
    setTimeout(() => {
      if (lastSuccessIndex < props.stages.length - 1) {
        connectorStyles.value[lastSuccessIndex] = "animate-grow-line";
        setTimeout(() => {
          showDashedLine.value[lastSuccessIndex] = false;

          stageStyles.value[firstPendingIdx] =
            "border-orange-400 text-orange-400 shadow-orange-300 animate-glow";
          showGlow.value = true;
        
          stageAnimationComplete.value[firstPendingIdx] = true;
        }, 1000); 
      } else {
        
        stageStyles.value[firstPendingIdx] =
          "border-orange-400 text-orange-400 shadow-orange-300 animate-glow";
        showGlow.value = true;
        
        stageAnimationComplete.value[firstPendingIdx] = true;
      }
    }, delay);
  }
};


onMounted(() => {
  runAnimations();
});


watch(
  () => props.stages,
  () => {
    runAnimations();
  },
  { deep: true }
);

const formatStatus = (status, isRejected = false, isReturned = false) => {
  if (typeof status !== "string") return props.pendingLabel; // Handle non-string status (e.g., [])
  const normalizedStatus = status.toLowerCase();
  
  if (normalizedStatus === "success") {
    if (isRejected) return props.rejectedLabel;
    if (isReturned) return props.returnedLabel;
    return props.successLabel;
  }
  
  return props.pendingLabel;
};

const formatDate = (date) => {
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
};
</script>

<style scoped>

@keyframes glow-temp {
  0% {
    box-shadow: 0 0 1px #22c55e, 0 0 10px #22c55e, 0 0 15px #22c55e;
  }
  50% {
    box-shadow: 0 0 8px #22c55e, 0 0 20px #22c55e, 0 0 25px #22c55e;
  }
  100% {
    box-shadow: 0 0 3px #22c55e, 0 0 10px #22c55e, 0 0 15px #22c55e;
  }
}

.animate-glow-temp {
  animation: glow-temp 0.5s ease-in-out;
}


@keyframes glow-temp-red {
  0% {
    box-shadow: 0 0 1px #dc2626, 0 0 10px #dc2626, 0 0 15px #dc2626;
  }
  50% {
    box-shadow: 0 0 8px #dc2626, 0 0 20px #dc2626, 0 0 25px #dc2626;
  }
  100% {
    box-shadow: 0 0 3px #dc2626, 0 0 10px #dc2626, 0 0 15px #dc2626;
  }
}

.animate-glow-temp-red {
  animation: glow-temp-red 0.5s ease-in-out;
}


@keyframes glow {
  0% {
    box-shadow: 0 0 1px #f97316, 0 0 10px #f97316, 0 0 15px #f97316;
  }
  50% {
    box-shadow: 0 0 8px #f97316, 0 0 20px #f97316, 0 0 25px #f97316;
  }
  100% {
    box-shadow: 0 0 3px #f97316, 0 0 10px #f97316, 0 0 15px #f97316;
  }
}

.animate-glow {
  animation: glow 1.5s ease-in-out infinite;
}


@keyframes grow-line {
  0% {
    width: 0;
  }
  100% {
    width: calc(100% - 48px);
  }
}

.animate-grow-line {
  animation: grow-line 1s ease-in-out forwards;
}


.green-connector {
  height: 4px !important;
  will-change: width;
}
</style>
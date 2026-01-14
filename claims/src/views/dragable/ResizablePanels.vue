<template>
  <div>
    <!-- Split Panel with Notes and Right Panel - only shown after clicking Add Note -->
    
    <SplitPanel ref="splitPanelRef" :initialLeftWidth="'50%'" v-model:showPanel="showSplitPanel">
    <template #left="{ close }">
      <NoteSection @closeEditor="close" />
    </template>

    <template #right>
      <div> <RightPanelComponent @add-note="enableSplitPanel" /></div>
    </template> 
  </SplitPanel>

 
  </div>
</template>

<script setup>
import { ref } from 'vue';
import NoteSection from './NoteSection.vue';
import RightPanelComponent from './RightPanelComponent.vue';
import SplitPanel from './SplitPanel.vue';

const showSplitPanel = ref(false);
const splitPanelRef = ref();


// ✅ Open editor: show left panel at 50% width
const enableSplitPanel = () => {
  showSplitPanel.value=true
   splitPanelRef.value?.openPanel();
};


</script>

<template>
  <ResizablePanels />
  <!-- split panel for letter add section -->
  <div class="mt-4">
    <SplitPanel ref="letterPanelRef" :initialLeftWidth="'50%'" minRightWidth="0" v-model:showPanel="letterSplitPanel">
      <template #left="{ close }">
        <LetterTemplateEditor />
      </template>
    </SplitPanel>
  </div>
  <div class="hidden bg-blue-800" />
  <div class="flex justify-start gap-4 py-3 rounded-b">
    <button class="bg-red-500 hover:bg-red-600 text-white rounded-full px-4 py-2 rounded text-sm">
      Send Back To Member
    </button>
    <button class="bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm">
      Approve
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import LetterTemplateEditor from './LetterTemplateEditor.vue';
import ResizablePanels from './ResizablePanels.vue';
import SplitPanel from './SplitPanel.vue';
const letterPanelRef = ref();
const letterSplitPanel = ref(true);
</script>
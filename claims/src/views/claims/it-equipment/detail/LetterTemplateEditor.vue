<template >
    <Loading v-if="isLoading" />
    <AccordionSlot :isOpen="false" title="Letter Template">
      <div v-if="hasAnyPermission([PERMISSIONS.ITCLAIM.INITIATE, PERMISSIONS.ITCLAIM.REVIEW])" class="space-y-4 p-4 rounded w-full mx-auto h-full">
        <!-- Template Cards -->
        <div v-if="hasPermission([PERMISSIONS.ITCLAIM.INITIATE])" class="flex gap-4 overflow-x-auto">
          <div
            v-for="template in templates"
            :key="template.id"
            @click="() => setContent(template)"
            class="flex flex-col items-center justify-center cursor-pointer"
          >
            <div
            class="relative w-24 h-32 flex-shrink-0 border rounded-lg p-2 flex flex-col items-center justify-center hover:border-blue-400"
            :class="{ 'border-blue-500 ring-2 ring-blue-300': selectedId == template.id }"
            :style="{ backgroundImage: `url(${TemplateImage})` }"
            >
              <div class="absolute top-1 right-1 text-green-500" v-if="selectedId == template.id">
                <Icon icon="mdi:check-circle" class="w-5 h-5" />
              </div>
            </div>
            <div class="pt-1 bottom-1 right-1 text-xs text-center w-24 truncate" :title="template.template_name">
              <span class="text-xl" v-if="template.id == 0">+</span>
              {{ template.template_name }}
            </div>
          </div>
        </div>

        <!-- Reset Button -->
        <div v-if="hasPermission([PERMISSIONS.ITCLAIM.INITIATE]) && (editorContainer != '')" class="flex justify-end">
          <Button
            icon="mdi:refresh"
            label="Reset"
            color="red-outline"
            size="xs"
            @click="resetContent"            
          />
        </div>
        <RichTextEditor v-show="hasAnyPermission([PERMISSIONS.ITCLAIM.INITIATE, PERMISSIONS.ITCLAIM.REVIEW]) || editorContainer != null"
          v-model="editorContainer"
          :title="'IT Claim: ' + selectedTemplateName"
          @update:modelValue="handleSave"
        />
      </div>
      <div v-else class="space-y-4 p-4 rounded w-full mx-auto h-full" v-html="serverContent"></div>
      
    </AccordionSlot>
</template>
<script setup>
import AccordionSlot from './AccordionSlot.vue';
import { ref, computed, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { Button, Loading } from '@sds/oneui-common-ui';
import RichTextEditor from './RichTextEditor.vue';
import { useApiStore } from "@/store/apiData";
import TemplateImage from '@/assets/images/template.jpg';
import { getTemplateContent } from "@/services/rss/itEquipmentsService";
import { PERMISSIONS, hasPermission, hasAnyPermission } from '@/utils/rbac';

const apiStore = useApiStore();
const selectedTemplateName = ref('')
const selectedId = ref(null)
const editorContainer=ref(null)
const serverContent = ref('');
const isLoading = ref(false);
const templates = ref();

const setContent = async (item) => {
  editorContainer.value = item.content;
  if ( item.id > 0 ) {
    isLoading.value = true;
    const response = await getTemplateContent(apiStore.it_equipment.detail.claim_id, item.id);
    isLoading.value = false;
    if ( response.isError == false && response.success_code == 200 ) {
      editorContainer.value = response.data;
    }
  }
  selectedId.value = item.id;
  selectedTemplateName.value = item.template_name;
  apiStore.setItEquipment({...apiStore.it_equipment, detail: {...apiStore.it_equipment.detail,template_id:selectedId.value, template_content:editorContainer.value}});
}

const handleSave = (content) => {
  apiStore.setItEquipment({...apiStore.it_equipment, detail: {...apiStore.it_equipment.detail,template_content:content}});
}

const resetContent = () => {  
  selectedId.value = null;
  editorContainer.value = null;
}

watch(
  () => apiStore.it_equipment.detail?.template_content, 
  (newData, oldData) => {
    serverContent.value = newData;
  },
  { immediate: true }
);

watch(
  () => apiStore.it_claim_templates, 
  (newData) => {
    let tem = newData || [];
    tem = [{ id: 0, content: '', template_name: 'Office Notes' }, ...tem];
    tem.map(item => {
      if ( item.id == apiStore.it_equipment.detail.template_id ) {
        selectedId.value = item.id;
        editorContainer.value = apiStore.it_equipment.detail.template_content || item.content;
        selectedTemplateName.value = item.template_name;
      }
    });
    templates.value = tem;
  },
  { immediate: true }
);
</script>

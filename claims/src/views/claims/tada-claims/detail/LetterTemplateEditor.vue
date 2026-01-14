<template >
  <!-- {{ storedata_tada }} -->
    <Loading v-if="isLoading" />
    <AccordionSlot :title="esignBy ? 'Add a Letter to Template' : 'View Letter Template'">

      <div v-if="esignBy" class="space-y-4 p-4 rounded w-full mx-auto h-full">
        <!-- Template Cards -->
        <div class="flex gap-4 overflow-x-auto" v-if="!props.edit">
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
        <div class="flex justify-end" v-if="!props.edit">
          <button
            @click="resetContent"
            class="flex items-center text-red-500 border border-red-300 rounded-full px-3 py-1 text-sm hover:bg-red-50 transition"
            v-if="editorContainer != ''"
          >
            <Icon icon="mdi:refresh" class="w-4 h-4 mr-1" />
            Reset
          </button>
        </div>
        <!-- {{ editorContent }} -->
        <RichTextEditor v-if="editorContent != null"
          v-model="editorContent"
          :title="'TADA Claim: ' + selectedTemplateName"

        />
      </div>
      <div v-else class="space-y-4 p-4 rounded w-full mx-auto h-full" v-html="serverContent"></div>
      
    </AccordionSlot>
</template>
<script setup>
import AccordionSlot from './AccordionSlot.vue';
import { ref, computed, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { Loading } from '@sds/oneui-common-ui';
import RichTextEditor from './RichTextEditor.vue';
import { useApiStore } from "@/store/apiData";
import TemplateImage from '@/assets/images/template.jpg';
import { getTemplateContent } from "@/services/rss/TadaServices";

const apiStore = useApiStore();

const selectedTemplateName = ref('')
const storedata_tada = ref(null);
const moduleId = ref('0');
const selectedId = ref(null)
const editorContainer=ref(null)
const serverContent = ref('');
const isLoading = ref(false);
const esignBy = ref(false);
const editorContent = defineModel('content',{
  type:String,
  default:''
});

const props = defineProps({
  edit: {
    type: Boolean,
    default: false
  }
});

const templates = computed(() => {
  let tem = apiStore.tada_claim.detail?.templates || [];
  tem = [{ id: 0, content: '', template_name: 'Office Notes' }, ...tem]
  tem.map(item => {
    if ( item.id == apiStore.tada_claim.detail.template_id ) {
      selectedId.value = item.id;
      editorContent.value = apiStore.tada_claim.detail.template_content || item.content;
      selectedTemplateName.value = item.template_name;
    }
  });
  return tem;
});

const setContent = async (item) => {
  if ( item.id > 0 ) {
    isLoading.value = true;
    const response = await getTemplateContent(apiStore.tada_claim.detail.claim_id, item.id);
    isLoading.value = false;
    if ( response.isError == false && response.success_code == 200 ) {
      editorContainer.value = response.data;
      console.log(response);
    } else {
      editorContainer.value = item.content;
    }
  } else {
    editorContainer.value = item.content;
  }
  selectedId.value = item.id;
  selectedTemplateName.value = item.template_name;
  apiStore.setTadaClaim({...apiStore.tada_claim, detail: {...apiStore.tada_claim.detail,template_id:selectedId.value, template_content:editorContainer.value}});
}

const handleSave = (content) => {
  apiStore.setTadaClaim({...apiStore.tada_claim, detail: {...apiStore.tada_claim.detail,template_content:content}});
}

const resetContent = () => {  
  selectedId.value = null;
  editorContainer.value = null;
}

watch(
  () => apiStore.tada_claim?.detail,
  (newDetail) => {
    if (!newDetail) {
      storedata_tada.value = null;
      moduleId.value = '0';
      return;
    }

    storedata_tada.value = newDetail;
    moduleId.value = newDetail.module_id || '0';
  },
  { immediate: true, deep: true }
);

watch(
  () => apiStore.tada_claim.detail?.template_content, 
  (newData, oldData) => {
    serverContent.value = newData;
    let esign_by = apiStore.tada_claim.detail?.esign_by || 0;
    if ( esign_by > 0 ) {
      let userId = apiStore.user.id || 0;
      if ( userId == esign_by ) {
        esignBy.value = true;
      } else {
        esignBy.value = false;
      }
    } else {
      esignBy.value = true;
    }
  },
  { immediate: true }
);
</script>

<template >
    <AccordionSlot title="Template History" :isOpen="false">
    <div class="space-y-4 p-4 rounded w-full mx-auto h-full">
    <div v-if="templates.length > 0" class="flex gap-4 overflow-x-auto">
      <div
        v-for="item in templates"
        :key="item.id"
        @click="() => setContent(item)"
        class="flex flex-col items-center justify-center cursor-pointer"
      >
        <div
        class="relative w-24 h-32 flex-shrink-0 border rounded-lg p-2 flex flex-col items-center justify-center hover:border-blue-400"
        :class="{ 'border-blue-500': selectedId == item.id }"
        :style="{ backgroundImage: `url(${TemplateImage})` }"
        >
        </div>
        <div class="pt-1 bottom-1 right-1 text-xs text-center w-24 truncate" :title="item.sender">
          {{ item.sender }}
        </div>
      </div>
    </div>
    <div v-else>
      {{ message }}
    </div>
  </div>
  <hr></hr>
  <div v-show="selectedId" class="text-center justify-center">
    <span class="cursor-pointer text-red-500 text-xs" @click="clearHistory">
      Clear History
    </span>
  </div>
  <div class="mt-2" v-html="selectedContent"></div>
  </AccordionSlot>

</template>
<script setup>
import AccordionSlot from './AccordionSlot.vue';
import { ref, computed, onMounted } from 'vue'
import TemplateImage from '@/assets/images/template.jpg';
import { getStatusHistory } from "@/services/rss/itEquipmentsService";
import { useRoute } from 'vue-router';
import { useI18n } from "vue-i18n";

const route = useRoute();
const { t, locale } = useI18n();
const selectedContent = ref('')
const selectedId = ref(null)
const templates = ref([]);
const claimId = ref(0);
const isLoading = ref(false);
const message = ref('');

const setContent = (item) => {
  selectedId.value = item.id;
  selectedContent.value = item.content;
}

const clearHistory = () => {
  selectedId.value = null;
  selectedContent.value = '';
}

const fetchHistory = async () => {
    if ( templates.value.length ) return;
    isLoading.value = true;
    const payload = {params: {pagelimit:100}};
    const response = await getStatusHistory(claimId.value, payload);
    isLoading.value = false;
    if ( response.isError == false ) {
      if ( response.success_code == 200 ) {
        templates.value = response.data.map(item => {
          return {id: item.id, sender:item.sender, content:item.template_content};
        }).filter(item => item.content);
      } else {
        templates.value = [];
      }
      if ( templates.value.length == 0 ) {
        message.value = computed( () =>t("no_record"));
      }
    } else {
      message.value = computed( () =>t("something_wrong"));
    }
}

onMounted(() => {
  claimId.value = route.params.id;
  fetchHistory();
})
</script>

<template>
  <div ref="fullscreenElement" class="bg-white rounded shadow-sm w-full max-w-max" >
    <div class="flex justify-between items-center p-3 border-b">
      <h3 class="text-gray-700 font-semibold text-base">Add Remarks</h3>
      <div class="flex items-center space-x-2 text-gray-600">
        <button class="hover:bg-gray-100 p-1 rounded" @click="toggleFullscreen">
          <Icon icon="mdi:fullscreen" class="h-5 w-5" />
        </button>
       
        <!-- <button class="hover:bg-gray-100 p-1 rounded" @click="closeEditorMessages">
          <Icon icon="mdi:close" class="h-5 w-5" />
        </button> -->
      </div>
    </div>

    <!-- Vue Quill -->
    <div class="px-4 pt-4" ref="editorSection">
       <QuillEditorWrapper
      v-model:content="editorContent"
      contentType="html"
      theme="snow" 
      ref="editorRef"
      />
      
    </div>

    <!-- Save Buttons -->
    <div class="flex items-center space-x-2 px-4 py-4">
      <button
      :disabled="isClicked"
        @click="() => addNoteRemark(0)"
        class="bg-green-600 rounded-full hover:bg-green-700 text-white px-4 py-1.5 flex items-center text-sm"
      >
        <Icon
          class="h-5 w-5 mr-1"
          icon="material-symbols-light:send-outline-rounded"
          width="24"
          height="24"
          style="color: #fff; transform: rotate(315deg)"
        />
        Add Remarks
      </button>
      <button
      :disabled="isClicked"
      @click="() => addNoteRemark(1)"
        class="bg-white border border-green-600 text-green-600 hover:bg-green-50 px-4 py-1.5 rounded-full flex items-center text-sm"
      >
        <Icon
          class="h-5 w-5 mr-1"
          icon="material-symbols-light:send-outline-rounded"
          width="24"
          height="24"
          style="color: bg-green-600; transform: rotate(315deg)"
        />
        Save As Draft
      </button>
    </div>

    <!-- Notes List -->
    <div class="px-4 pb-4 space-y-3" v-if="paginationData.length">
      <!-- Note 1 -->
      <div
        v-for="note in paginationData"
        :key="note.comm_id"
        :class="[
          note.self_note ? 'bg-orange-100' : 'bg-green-50',
          'rounded p-3 relative'
        ]"
      >
        <div v-if="note.self_note" class="flex justify-between items-center">
          <div class="flex">
            <svg
              @click="() => editRemark(note)" 
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 mr-1 text-gray-600 mt-[2px] cursor-pointer"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            <div class="flex flex-col leading-tight">
              <span class="font-medium text-sm">Draft Note</span>
              <span class="text-xs text-gray-500">Visible to you</span>
            </div>
          </div>
          <div class="flex space-x-1">
            <button :disabled="isClicked" @click="() => publishRemark(note.id)" class="bg-gray-600 text-white text-xs px-2 py-0.5 rounded">
              Publish
            </button>
          </div>
        </div>
        <div v-else>
          <!--div class="font-medium text-sm">
            Item id : {{ note.itemId }}, {{ note.itemName }}
          </div-->
          <div class="font-medium text-sm">Remark : #{{ note.note_count }}</div>
        </div>

        <p class="text-sm mt-1 text-gray-700" v-html="note.desc"></p>
        <div class="mt-2 text-right">
          <div class="text-xs text-gray-600">{{ note.actor_name }}</div>
          <div class="text-xs text-gray-600">{{ note.actor_designation }}</div>
          <div class="text-xs text-gray-600">{{ useLocalDate(note.created_at) }}</div>
        </div>
      </div>
    </div>
    <div class="bg-white">
        <PaginationSelect 
        :pagiShowLabel="t('pagi_show_label')"
        :pagiPrevLabel="t('pagi_prev_label')"
        :pagiNextLabel="t('pagi_next_label')"
        :pagiTotalLabel="pagiTotalLabel"
        :currentPage="claimPagination.current_page" 
        :totalPages="claimPagination.total_page" 
        :pageSize="claimPagination.per_page"
        @update:currentPage="handlePageChange" 
        @update:pageSize="handlePageSizeChange" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import useLocalDate from "@/composables/useLocalDate";
import QuillEditorWrapper from "./QuillEditorWrapper.vue";
import {addNote, updateNote} from "@/services/rss/itEquipmentsService";
import Swal from 'sweetalert2';
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { useApiStore } from "@/store/apiData";
import PaginationSelect from "./../PaginationSelect.vue";
import { useI18n } from "vue-i18n";
import { Button } from "@sds/oneui-common-ui";

const { t, locale } = useI18n();
const apiStore = useApiStore();
const currentUserId = apiStore.user.id || 0;
const editorSection = ref(null);
const editorContent = ref("");
const editorRef = ref(null);
const itemDetails = ref([]);
const editItem = ref({});
const isClicked = ref(false);

const paginationData = ref([]);
const claimPagination = ref({per_page:10, current_page:0, total_page:0});
const pagiTotalLabel = computed(() => t("pagi_total_label", { per_page: claimPagination.value.per_page, current: claimPagination.value.current_page, total: claimPagination.value.total_page }));
const startIndex = computed(() => (claimPagination.value.current_page - 1) * claimPagination.value.per_page);
const handlePageChange = newPage => {
  claimPagination.value.current_page = newPage;
  paginationData.value = claimNotes.value.slice(startIndex.value, startIndex.value + claimPagination.value.per_page);
};
const handlePageSizeChange = newSize => {
  claimPagination.value = {...claimPagination.value, per_page:newSize, current_page:1};
  claimPagination.value.total_page = Math.ceil(claimNotes.value.length/claimPagination.value.per_page)
  paginationData.value = claimNotes.value.slice(startIndex.value, startIndex.value + claimPagination.value.per_page);
};

const contentNot = computed(() => {
  let status = true;
  let claimStatus = apiStore.it_equipment.detail.status.toLowerCase() || '';
  const assignedTo = apiStore.it_equipment.detail.assigned_to || 0;
  if ( hasPermission([PERMISSIONS.ITCLAIM.APPROVE])) {
    if ( ['submitted','initiated','in progress'].includes(claimStatus) ) {
      status = false;
    }
  } else if ( hasPermission([PERMISSIONS.ITCLAIM.REVIEW]) ) {
    if ( (assignedTo == currentUserId) && ['submitted','initiated', 'in progress'].includes(claimStatus) ) {
      status = false;
    }
  } else if ( hasPermission([PERMISSIONS.ITCLAIM.INITIATE]) ) {
    if ( claimStatus == 'submitted' ) {
      status = false;
    }
  }
  return status;
});

const emit=defineEmits(['closeEditor']);
let notes = (apiStore.it_equipment.detail?.notes || []).map(note => ({
    ...note,
    self_note: note.self_note?.toString().toLowerCase() === "true"
  })).filter(item => ((item.actor_id == currentUserId) && item.self_note) || !item.self_note )

const claimNotes = ref(notes);
const resetPages = () => {
    claimPagination.value.current_page = claimNotes.value.length ? 1 : 0;
    claimPagination.value.total_page = Math.ceil(claimNotes.value.length/claimPagination.value.per_page);
    paginationData.value = claimNotes.value.slice(startIndex.value, startIndex.value + claimPagination.value.per_page);
}

watch(
  () => apiStore.it_equipment.detail?.notes,
  (newData) => {
    if (newData) {
      claimNotes.value = newData.map(note => ({
        ...note,
        self_note: note.self_note?.toString().toLowerCase() === "true"
      })).filter(item => ((item.actor_id == currentUserId) && item.self_note) || !item.self_note );
      resetPages();
    }
  },
  { immediate: true }
);

const findItemDetails = () => {
  const claimItems = apiStore.it_equipment.detail?.claim_items || [];
  const touchedItems = apiStore.it_equipment.detail?.touched_items || [];
  
  if ( Array.isArray(touchedItems) && touchedItems.length > 0 ) {
    let descContent = '<div class="font-medium text-sm">Item id : ';
    touchedItems.map(id => {
      const item = claimItems.filter(item => item.id == id );
      if ( Array.isArray(item) && item.length > 0 && !itemDetails.value.includes(id) ) {
        const pipeSymbol = itemDetails.value.length > 0 ? '|' : '';
        descContent = descContent + item[0]['id'] + ', '+ item[0]['item_name'] + pipeSymbol;
        itemDetails.value = [...itemDetails.value, {id, status:item[0]['checked']}];
      }
    });
    descContent = descContent + '</div>';
    if ( false == editorContent.value.includes('<div class="font-medium text-sm">Item id : ') ) {
      editorContent.value = descContent + editorContent.value;
    }
  }
  return true;
}

const addNoteRemark = async (self_note=0) => {
  if ( contentNot.value ) {
    noteSwalPopup({isError: true, message: 'Sorry, you don`t have the required rights.'});
    return false;
  }
  if (isClicked.value) return false;
  const editorText = editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim();
  if ( editorText == "" ) {
    noteSwalPopup({isError: true, message: 'Content should not be empty!'});
    return true;
  }
  findItemDetails();
  const payload = {
    claim_id: apiStore.it_equipment.detail.claim_id,
    description: editorContent.value,
    self_note: self_note,
    item_details: itemDetails.value
  }
  isClicked.value = true;
  const response = ( editItem.value?.id ) ? await updateNote(editItem.value.id, payload) : await addNote(payload);
  isClicked.value = false;
  let message = '';
  if ( response.isError == false ) {
    if ( response.success_code == 200) {
      if ( ( editItem.value?.id ) ) {
        claimNotes.value = claimNotes.value.map(item => editItem.value?.id == item.id ? {...item, desc: response.data.description, self_note: (self_note == 1) ? "true" : "false" } : item);
      } else {
        claimNotes.value = response.data;
        resetPages();
      }
      apiStore.setItEquipment({...apiStore.it_equipment, detail: {...apiStore.it_equipment.detail, touched_items:[], notes:claimNotes.value}});
      editorRef.value?.clearEditor();
      editorContent.value = '';
      editItem.value = {};
      itemDetails.value = [];
      message = response.message || 'Content successfully is saved!';
      noteSwalPopup({isError: response.isError, message});
    } else {
      message = response.message || 'Something went wrong!';
      noteSwalPopup({isError: true, message});
    }
  } else {
    noteSwalPopup({isError: response.isError, message});
  }
}

const editRemark = (item) => {
  if ( contentNot.value ) {
    noteSwalPopup({isError: true, message: 'Sorry, you don’t have the required rights.'});
    return false;
  }
  editItem.value = item;
  editorContent.value = item.desc;
  editorSection.value.scrollIntoView({ behavior: "smooth", block: "start" });
}

const publishRemark = async (id) => {
  if ( contentNot.value ) {
    noteSwalPopup({isError: true, message: 'Sorry, you don’t have the required rights.'});
    return false;
  }
  if ( isClicked.value ) return false;
  isClicked.value = true;
  const payload = {self_note: 0}
  const response = await updateNote(id, payload);
  isClicked.value = false;
  let message = '';
  if ( ! response.isError ) {
    message = 'Successfully published!';
    claimNotes.value = claimNotes.value.map(item => id == item.id ? {...item, self_note:0 } : item);
    apiStore.setItEquipment({...apiStore.it_equipment, detail: {...apiStore.it_equipment.detail, notes:claimNotes.value}});
  }
  noteSwalPopup({isError: response.isError, message});
}
const noteSwalPopup = (item) => {
  Swal.fire({
    icon: item.isError ? 'error' : 'success',
    title: "IT Claim: Remark",
    text: item.message || 'Something went wrong, please try after sometime!',
    confirmButtonText: "OK",
    customClass: {
      confirmButton: 'bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm mr-4'
    }
  });
  return true;
}
const fullscreenElement = ref(null)

const toggleFullscreen = () => {
  const el = fullscreenElement.value

  if (document.fullscreenElement) {
    document.exitFullscreen()
  } else if (el) {
    el.requestFullscreen()
  }
}
const closeEditorMessages=()=>{
   emit("closeEditor", false);
}

onMounted (() => {
  findItemDetails();
})

</script>

<style scoped>
/* Custom styles for Quill editor */
:deep(.ql-editor) {
  min-height: 100px;
}

:deep(.ql-container) {
  border-bottom-left-radius: 0.25rem;
  border-bottom-right-radius: 0.25rem;
}

:deep(.ql-toolbar) {
  border-top-left-radius: 0.25rem;
  border-top-right-radius: 0.25rem;
}
</style>

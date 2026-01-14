<template>
  <div class="h-full">
    <!-- Top Meta Info -->
    <div class="bg-gray-50 dark:bg-gray-800 min-h-screen p-2 rounded-xl">
      <!-- Notes Section -->
      <div class="col-span-1 flex-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6">

        <div class="space-y-3">

          <!-- EDITOR SECTION: Always show one default editor -->
          <div class="rounded-xl p-3">
            <QuillEditorWrapper v-model:content="editorNote.remarks" contentType="html" theme="snow" ref="editorRef" 
            class="rounded-md"
            />

            <p class="text-gray-500 text-sm mt-1" v-if="!editorNote.remarks">
              Keep your account secure with authentication step.
            </p>

            <div class="flex justify-left mt-2 gap-2" v-if="hasPermission(PERMISSIONS.TADACLAIM.INITIATE) ||
              hasPermission(PERMISSIONS.TADACLAIM.REVIEW) ||
              hasPermission(PERMISSIONS.TADACLAIM.APPROVE)">
              <button @click="submitGreen(editorNote)"
                class="bg-green-600 rounded-full hover:bg-green-700 text-white px-4 py-1.5 flex items-center text-sm">
                <Icon class="h-5 w-5 mr-1" icon="material-symbols-light:send-outline-rounded" width="24" height="24"
                  style="color: #fff; transform: rotate(315deg)" />
                Add Remarks
              </button>

              <button @click="submitYellow(editorNote)"
                class="bg-white border border-green-600 text-green-600 hover:bg-green-50 px-4 py-1.5 rounded-full flex items-center text-sm">
                <Icon class="h-5 w-5 mr-1" icon="material-symbols-light:send-outline-rounded" width="24" height="24"
                  style="color: bg-green-600; transform: rotate(315deg)" />
                Save As Draft
              </button>
            </div>
          </div>

          <!-- NOTES LIST -->
          <div class="px-4 pb-4 space-y-3" v-if="paginationData.length">

            <div v-for="note in paginationData" :key="note.id" :class="[
              note.type === 0 ? 'bg-orange-100 border-orange-300' : 'bg-green-50 border-green-300',
              'rounded-lg p-4 border relative'
            ]">
              <!-- HEADER -->
              <div class="flex justify-between items-center" v-if="note.type === 0">


                <!-- ACTIONS -->

                <div class="flex">
                  <svg @click="editDraft(note)" xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 mr-1 text-gray-600 mt-[2px] cursor-pointer" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  <div class="flex flex-col leading-tight">
                    <span class="font-medium text-sm" v-if="note.type === 0">Draft Note</span>
                    <span class="text-xs text-gray-500">Visible to you</span>
                  </div>
                </div>
                <div class="flex space-x-1">

                  <button class="bg-gray-600 hover:bg-gray-700 text-white text-xs px-3 py-1 rounded"
                    @click="convertNote(note)">
                    Publish
                  </button>
                </div>
            </div>

            <!-- BODY -->
            <div class="text-sm text-gray-700 leading-relaxed" v-html="note.remarks || '<i>No content</i>'"></div>

            <!-- FOOTER -->
            <div class="mt-3 text-right text-xs text-gray-600">
              <div>{{ note?.user?.full_name || 'N/A' }}</div>
              <div>
                {{
                  formatDate(note.created_at, {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                  })
                }}
              </div>
            </div>
          </div>
        </div>

        <!-- EMPTY STATE -->
        <div v-if="!paginationData.length" class="text-center text-gray-500 py-6">
          No notes available
        </div>

        <!-- PAGINATION -->
        <div class="bg-white border-t px-1 py-3 flex justify-between items-start">
          <!-- <div class="text-sm text-gray-600">
            Showing
            {{ startIndex + 1 }} –
            {{ Math.min(startIndex + pageSize, notes.length) }}
            of {{ notes.length }}
          </div> -->

          <div class="flex items-left">
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
        </div>

      </div>
    </div>
    <!-- End Notes Section -->
  </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch  } from 'vue';
import { Button } from '@sds/oneui-common-ui';
import { Icon } from "@iconify/vue";
import QuillEditorWrapper from "./QuillEditorWrapper.vue";
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { useRoute, useRouter } from 'vue-router';
import { formatDate } from '@/utils/global';
import {
  fetchApprovalView,
  postConvertNote,
  fetchSendTo,
  postNotes,
  getAllNotes,
  statusEsign,
} from '@/services/approval';
import { debounce } from '@/utils/debounce';
import { fireToast } from '@/mixins/toast';

// State
const route = useRoute();
const router = useRouter();
import { usePersistedStore } from '@/composables/TadaAPIStore';
const apiStore = usePersistedStore();
const module_id = ref('0');
import PaginationSelect from "./../PaginationSelect.vue";
import { useI18n } from "vue-i18n";

const { t, locale } = useI18n();

const loading = reactive({
  page: true,
  reviewuser: false,
  sendback: false,
  sendreview: false,
  sendesign: false,
  savenotice: false,
  toclist: false,
  tocmarkedas: false,
  tocForm: false,
  draftlist: false,
  draftForm: false,
});

const submitbtn = ref('esign');
const module = ref({});
const notes = ref([]);
const reviewusers = ref([]);
const paginationData = ref([]);
const claimPagination = ref({
  per_page: 10,
  current_page: 0,
  total_page: 0,
});

const startIndex = computed(() => {
  return (claimPagination.value.current_page - 1) * claimPagination.value.per_page;
});

const handlePageChange = newPage => {
  claimPagination.value.current_page = newPage;
  paginationData.value = notes.value.slice(
    startIndex.value,
    startIndex.value + claimPagination.value.per_page
  );
};

const handlePageSizeChange = newSize => {
  claimPagination.value = {
    ...claimPagination.value,
    per_page: newSize,
    current_page: 1,
  };

  claimPagination.value.total_page = Math.ceil(
    notes.value.length / claimPagination.value.per_page
  );

  paginationData.value = notes.value.slice(
    startIndex.value,
    startIndex.value + claimPagination.value.per_page
  );
};
const resetPages = () => {
  claimPagination.value.current_page = notes.value.length ? 1 : 0;

  claimPagination.value.total_page = Math.ceil(
    notes.value.length / claimPagination.value.per_page
  );

  paginationData.value = notes.value.slice(
    startIndex.value,
    startIndex.value + claimPagination.value.per_page
  );
};

const form = reactive({
  remarks: '',
  review_to: '',
});

// Single editor note (always visible)
const editorNote = reactive({
  remarks: '',
  type: 1,
});

// Computed property for listing notes
const contentNot = computed(() => {
  let status = true;
  let claimStatus = apiStore.tada_claim.detail.status.toLowerCase() || '';
  const assignedTo = apiStore.tada_claim.detail.assigned_to || 0;
  if ( hasPermission([PERMISSIONS.TADACLAIM.APPROVE])) {
    if ( ['submitted','initiated','in progress'].includes(claimStatus) ) {
      status = false;
    }
  } else if ( hasPermission([PERMISSIONS.TADACLAIM.REVIEW]) ) {
    if ( (assignedTo == currentUserId) && ['submitted','initiated', 'in progress'].includes(claimStatus) ) {
      status = false;
    }
  } else if ( hasPermission([PERMISSIONS.TADACLAIM.INITIATE]) ) {
    if ( claimStatus == 'submitted' ) {
      status = false;
    }
  }
  return status;
});

const viewApprovalReset = () => {
  loading.page = true;
  fetchApprovalView({ params: { module_id: module_id.value } })
    .then(response => {
      module.value = response?.data || {};
    })
    .finally(() => {
      loading.page = false;
    });
};


const searchSendTo = debounce(async (search = '') => {
  loading.reviewuser = true;
  form.review_to = '';
  reviewusers.value = [];
  fetchSendTo({ params: { search: search } })
    .then(response => {
      loading.reviewuser = false;
      if (response.success && Array.isArray(response?.data?.data)) {
        reviewusers.value = response.data.data.map(item => ({
          label: item.full_name,
          value: item.core_user_id,
        }));
      }
    })
    .catch(error => {
      loading.sendto = false;
      console.error('Error fetching list:', error);
    });
}, 1000);
searchSendTo();

window.updateParent = query => {
  statusEsign(query.requestId)
    .then(response => {
      if (response.success) {
        submitbtn.value = 'approve';
        fireToast({ type: 'success', message: response.message });
      } else {
        fireToast({
          type: 'error',
          message: 'Esign not verified, Please try again later!',
        });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.page = false;
    });
};

const submitGreen = note => {
    if ( contentNot.value ) {
         fireToast({
          type: 'error',
          message: 'Sorry, you don’t have the required rights.',
        });

    return false;
  }
  const content = extractContent(note.remarks).trim();

  if (!content) {
    fireToast({
      type: 'error',
      message: 'Green note is mandatory. Please enter remarks.',
    });
    return;
  }

  const payload = {
    remarks: note.remarks,
    type: 1, // GREEN
  };

  postNotes(module.value.id, payload)
    .then(response => {
      if (response.success) {
        fireToast({
          type: 'success',
          message: 'Green note submitted successfully.',
        });

        // Clear editor and refresh list
        editorNote.remarks = '';
        getnoteList();
      }
    })
    .catch(error => {
      fireToast({ type: 'error', message: error.message });
    });
};

const submitYellow = note => {
    if ( contentNot.value ) {
         fireToast({
          type: 'error',
          message: 'Sorry, you don’t have the required rights.',
        });

    return false;
  }
  const payload = {
    remarks: note.remarks,
    type: 0, // YELLOW
  };

  postNotes(module.value.id, payload)
    .then(response => {
      if (response.success) {
        fireToast({
          type: 'success',
          message: 'Yellow note submitted successfully.',
        });

        // Clear editor and refresh list
        editorNote.remarks = '';
        getnoteList();
      }
    })
    .catch(error => {
      fireToast({ type: 'error', message: error.message });
    });
};

const extractContent = s => {
  var span = document.createElement('span');
  span.innerHTML = s;
  return span.textContent || span.innerText;
};

const editDraft = note => {
  editorNote.remarks = note.remarks;
  editorNote.type = 0;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const getnoteList = () => {
  if (!module_id) return;

  getAllNotes(module_id.value)
    .then(response => {
      if (response.success) {
        notes.value = response.data?.data || [];
        resetPages(); // 🔑 IMPORTANT
      }
    })
    .catch(error => {
      fireToast({ type: 'error', message: error.message });
    });
};



const convertNote = note => {
  let tmr = extractContent(note.remarks).trim();
  if (tmr.length === 0) {
    let result = window.confirm(
      'Are you sure you want to proceed with blank message?'
    );
    if (result) {
      finalConvertNote(note);
    }
  } else {
    finalConvertNote(note);
  }
};

//postConvertNote
const finalConvertNote = note => {
  postConvertNote(module.value.id, note)
    .then(response => {
      if (response?.success && response?.data?.id) {
        note.id = response.data.id;
        note.type = 1;

        fireToast({ type: 'success', message: response.message });
        resetPages(); // 🔑 keep pagination correct
      }
    })
    .catch(error => {
      fireToast({ type: 'error', message: error.message });
    });
};

watch(
  () => apiStore.tada_claim?.detail,
  (newDetail) => {
    if (!newDetail) {
      module_id.value = '0';
      return;
    }

    module_id.value = newDetail.module_id || '0';
  },
  { immediate: true, deep: true }
);

watch(
  () => module_id.value,
  (val) => {
    if (!val || val === '0') return;

    viewApprovalReset();
    getnoteList();
  },
  { immediate: true }
);

onMounted(() => {
  notes.value = [];
});

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
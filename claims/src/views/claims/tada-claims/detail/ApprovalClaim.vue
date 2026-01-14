<template>
  <div>
    <!-- <Loading v-if="!module?.id" /> -->
    <div>
      <div class="bg-gray-50 dark:bg-gray-800 min-h-screen p-8 rounded-xl">
        <!-- <Loading v-if="loading.page" /> -->
        <!-- Top Meta Info -->
        <div class="grid grid-cols-2 gap-4 mb-5">
          <div>
            <div class="text-sm text-gray-500">
              Meeting Group - ID: File No. -
              {{ filesName(module.created_at) }}/{{ module.module_id }}
            </div>
            <div class="text-xs text-gray-400">
              Assigned To: {{ module?.assign?.full_name ?? 'N/A' }}
            </div>
          </div>
          <div class="flex justify-end items-start gap-2 mt-4">
            <Button
              v-if="!module?.action"
              label="Send to Back History"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showTimelineModal = true"
            />
            <Button
              v-if="module?.can_pull"
              label="Pull to Back"
              color="gray-outline"
              style="
                padding: 2px 10px;
                border-radius: 5px;
                color: #979701;
                background: transparent;
                border: 1px solid #979701;
              "
              @click="pullToBack"
            />
            <Button
              v-if="module?.action"
              label="Send to Back"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showTimelineModal = true"
            />
            <Button
              v-if="module?.action"
              label="Send"
              color="blue-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showReviewModal = true"
            />
            <Button
              v-if="module?.action"
              label="Approve"
              color="green"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showApproveModal = true"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
          <!-- Notes Section -->
          <div
            class="col-span-1 flex-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6"
          >
            <div class="grid grid-cols-3 gap-4 items-center mb-2">
              <div>Notes</div>
              <div
                v-if="module?.notes?.filter(ele => ele.closed == 0).length == 0"
                class="col-span-2 flex justify-end gap-2"
              >
                <Button
                  v-if="module?.action"
                  label="+ Add Green Note"
                  color="green-outline"
                  style="padding: 2px 10px; border-radius: 5px"
                  @click="createNote('green')"
                />
                <Button
                  v-if="module?.action"
                  label="+ Add Yellow Note"
                  color="green"
                  style="
                    padding: 2px 10px;
                    border-radius: 5px;
                    color: #979701;
                    background: transparent;
                    border: 1px solid #979701;
                  "
                  @click="createNote('yellow')"
                />
              </div>
            </div>

            <div class="space-y-1">
              <div
                v-for="note in module.notes"
                :key="note.id"
                class="rounded-xl p-1"
              >
                <!--:class="note.type === 1 ? 'bg-green-100' : 'bg-yellow-100'" -->
                <div v-if="note.closed == 0">
                  <RichTextEditor
                    v-model="note.remarks"
                    class="w-full bg-transparent border-none focus:ring-0 text-sm dark:text-gray-100 resize-none"
                    :readonly="note.type === 1 && note.saved"
                    v-on:change="note.saved = false"
                    placeholder="Write your note here..."
                    :style="
                      note.type === 1
                        ? 'background:#f0fdf4'
                        : 'background:#ffffd5'
                    "
                  ></RichTextEditor>
                  <div
                    class="flex justify-end mt-2 gap-2"
                    v-if="module?.action && note.closed === 0"
                  >
                    <Button
                      label="Save"
                      color="blue"
                      style="padding: 2px 10px; border-radius: 5px"
                      @click="saveNote(note)"
                    />
                    <Button
                      label="Delete"
                      color="red"
                      style="padding: 2px 10px; border-radius: 5px"
                      @click="delNote(note)"
                    />
                    <Button
                      v-if="note.type === 0 && note.online == 1"
                      label="Convert"
                      color="green"
                      style="padding: 2px 10px; border-radius: 5px"
                      @click="convertNote(note)"
                    />
                  </div>
                </div>
                <div
                  v-else
                  class="p-4 rounded-md grid grid-cols-1"
                  :class="note.type === 1 ? 'bg-green-100' : 'bg-yellow-100'"
                >
                  <div
                    v-if="note?.user?.id"
                    class="text-sm text-green-700 font-medium"
                  >
                    Note #{{ note?.note_no || 'N/A' }}
                  </div>
                  <div
                    class="text-sm p-1 pb-4 overflow-hidden"
                    v-html="note.remarks"
                  ></div>
                  <div
                    class="flex justify-between items-center text-xs font-medium"
                  >
                    <div>
                      {{
                        formatDate(note.created_at, {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                          hour12: true,
                        }).toUpperCase()
                      }}
                    </div>
                    <div>
                      {{ note?.user?.full_name || 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-if="module?.notes?.length == 0"
                class="bg-gray-100 rounded-xl p-4"
              >
                <div class="text-xs text-gray-800 font-medium mb-1 text-center">
                  No records founds.
                </div>
              </div>
            </div>
          </div>

          <!-- Module Details Section -->
          <div
            class="col-span-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6"
          >
            <div class="flex justify-between items-center mb-4">
              <div>
                <div class="font-semibold text-gray-700">Module Details</div>
                <div class="text-xs text-gray-500">
                  Created: {{ formatDate(module.created_at) }}
                </div>
              </div>
            </div>

            <!-- Toggle buttons -->
            <div class="flex mb-4">
              <button
                :class="{
                  'bg-gray-200 text-gray-700': isDraft,
                  'bg-gray-100 text-gray-500': !isDraft,
                }"
                class="flex-1 py-2 rounded-l-lg font-medium text-sm"
                @click="isDraft = true"
              >
                Draft
              </button>
              <button
                :class="{
                  'bg-gray-200 text-gray-700': !isDraft,
                  'bg-gray-100 text-gray-500': isDraft,
                }"
                class="flex-1 py-2 rounded-r-lg font-medium text-sm"
                @click="isDraft = false"
              >
                Toc
              </button>
            </div>

            <!-- Content that appears based on the toggle -->
            <div v-if="isDraft" class="mb-4">
              <div v-if="module.module">
                <div
                  v-if="module?.action"
                  class="flex items-center justify-end"
                >
                  <Button
                    label="Add Draft"
                    color="blue"
                    style="padding: 2px 10px; border-radius: 5px"
                    @click="addDraftFrom"
                  />
                </div>
                <div>
                  <DataTable
                    :data="draftList"
                    :customColumnOrder="draftColumns"
                    :loading="loading.draftlist"
                    :loading-row="draftPagination.per_page"
                    :show-actions="true"
                  >
                    <template #cell-s_no="{ cell }">
                      <div>{{ cell.value }}</div>
                    </template>
                    <template #cell-version="{ cell }">
                      <div>{{ cell.value }}</div>
                    </template>
                    <template #cell-draft_no="{ cell }">
                      <div>{{ cell.value }}</div>
                    </template>
                    <template #cell-subject="{ cell }">
                      <div>{{ cell.value }}</div>
                    </template>
                    <template #cell-created_at="{ cell }">
                      <div>
                        {{
                          formatDate(cell.value, {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true,
                          }).toUpperCase()
                        }}
                      </div>
                    </template>
                    <template #cell-after="{ row }">
                      <div class="flex items-center justify-center gap-2">
                        <Icon
                          v-if="module?.action"
                          icon="mdi:edit-outline"
                          class="h-5 w-5 cursor-pointer text-blue-500"
                          @click="editDraftFrom(row, false)"
                        />
                        <Icon
                          icon="mdi:eye-outline"
                          class="h-5 w-5 cursor-pointer text-blue-500"
                          @click="editDraftFrom(row, true)"
                        />
                      </div>
                    </template>
                  </DataTable>
                  <PaginationSelect
                    :currentPage="draftPagination.current_page"
                    :totalPages="draftPagination.last_page"
                    :pageSize="draftPagination.per_page"
                    @update:currentPage="onPageChangeDraft"
                    @update:pageSize="onPageSizeChangeDraft"
                  />
                </div>
              </div>
            </div>

            <!-- Table appears when 'Toc' is selected -->
            <div v-if="!isDraft" class="overflow-x-auto">
              <div class="flex items-center justify-between">
                <div class="flex space-x-2">
                  <template
                    v-if="tocMarkedAsIds?.length > 0"
                    v-for="(tocStatus, index) in tocMarkStatus"
                  >
                    <Button
                      v-if="tocStatus"
                      :label="tocStatus"
                      color="blue"
                      style="
                        padding: 2px 2px;
                        border-radius: 5px;
                        background-color: initial;
                        color: blue;
                        text-decoration: underline;
                        box-shadow: none;
                      "
                      :loading="loading.tocmarkedas == index"
                      @click="tocMarkSubmit(index)"
                    />
                  </template>
                </div>
                <div v-if="module?.action">
                  <Button
                    label="Add Reciept"
                    color="blue"
                    style="padding: 2px 10px; border-radius: 5px"
                    @click="showTocFormwModal = true"
                  />
                </div>
              </div>
              <DataTable
                :showActions="false"
                :data="tocList"
                :customColumnOrder="tocColumns"
                :loading="loading.toclist"
                :loading-row="tocPagination.per_page"
              >
                <template #cell-id="{ cell }">
                  <div class="flex items-center">
                    <CheckboxGroup
                      v-if="module?.action"
                      label=""
                      v-model="tocMarkedAsIds"
                      :options="[{ label: '', value: cell.value }]"
                    />
                    {{ cell.value }}
                  </div>
                </template>
                <template #cell-marked_as="{ cell }">
                  <div>{{ tocMarkStatus[cell.value] }}</div>
                </template>
                <template #cell-subject="{ cell }">
                  <div>{{ cell.value }}</div>
                </template>
                <template #cell-remarks="{ cell }">
                  <div>{{ cell.value }}</div>
                </template>
                <template #cell-page="{ cell }">
                  <div>{{ cell.value }}</div>
                </template>
                <template #cell-attached_by="{ cell }">
                  <div>{{ cell.value }}</div>
                </template>
                <template #cell-attached_on="{ cell }">
                  <div>
                    {{
                      formatDate(cell.value, {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                      })
                    }}
                  </div>
                </template>
                <template #cell-issued_on="{ cell }">
                  <div>
                    {{
                      formatDate(cell.value, {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true,
                      })
                    }}
                  </div>
                </template>
              </DataTable>
              <PaginationSelect
                :currentPage="tocPagination.current_page"
                :totalPages="tocPagination.last_page"
                :pageSize="tocPagination.per_page"
                @update:currentPage="onPageChangeToc"
                @update:pageSize="onPageSizeChangeToc"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline Modal -->
      <Modal
        v-model="showTimelineModal"
        title="Timeline"
        size="sm"
        @close="showTimelineModal = false"
      >
        <div class="p-4 min-h-[200px] max-h-[70vh]">
          <div
            v-for="item in module.stackholder"
            :key="item.id"
            class="flex items-center justify-between border-b border-gray-200 py-3"
          >
            <div class="flex items-center space-x-3">
              <span class="text-gray-500 text-lg mt-0.5">
                <Icon icon="mdi:account-outline" />
              </span>

              <div>
                <div class="text-sm font-semibold text-gray-800">
                  {{ item?.user?.full_name }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ item.utype }}
                </div>
              </div>
            </div>

            <!-- Right button -->
            <Button
              v-if="item.action && module?.action"
              label="Send Back"
              color="blue-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="sendtoback(item)"
            />
          </div>
        </div>
      </Modal>

      <!-- Review Modal -->
      <Modal
        v-model="showReviewModal"
        title="Send"
        size="sm"
        @close="showReviewModal = false"
      >
        <div class="p-5">
          <div class="mb-4 min-h-[200px]">
            <SelectInput
              v-model="form.review_to"
              search
              type="select"
              :loading="loading.reviewuser"
              placeholder="Search Review User"
              :options="reviewusers"
              @search="searchSendTo"
            />
          </div>

          <div class="flex items-center justify-end gap-3 mt-6">
            <Button
              :disabled="loading.sendreview"
              label="Cancel"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showReviewModal = false"
            />
            <Button
              :loading="loading.sendreview"
              label="Send"
              color="green-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="sendToReviewUser"
            />
          </div>
        </div>
      </Modal>

      <!-- Toc Form Modal -->
      <Modal
        v-model="showTocFormwModal"
        title="Add Reciept"
        size="md"
        :disable-backdrop="true"
        @close="tocFormReset"
      >
        <div class="grid grid-col-1 space-y-4">
          <TextInput
            v-model="tocForm.subject"
            label="Subject"
            :error="errors.subject"
            :isRequired="true"
          />
          <TextInput
            v-model="tocForm.remarks"
            label="Remarks"
            :error="errors.remarks"
            :isRequired="true"
          />
          <FileUpload
            label="Upload PDF File"
            accept=".pdf"
            fileTypes="PDF"
            :error="errors.file"
            @update:files="handleTocFile"
          />
          <div class="flex items-center justify-center space-x-4">
            <Button
              label="Reset"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              :disabled="loading.tocForm"
              @click="tocFormReset"
            />
            <Button
              label="Submit"
              color="green"
              style="padding: 2px 10px; border-radius: 5px"
              :loading="loading.tocForm"
              @click="tocFormSubmit"
            />
          </div>
        </div>
      </Modal>

      <!-- Draft Form Modal -->
      <Modal
        v-model="showDraftFormwModal"
        title="Draft Revision"
        size="lg"
        :disable-backdrop="true"
        @close="draftFormReset"
      >
        <div class="max-h-[70vh] grid grid-col-1 space-y-4">
          <RichTextEditor
            v-if="!draftForm.view"
            v-model="draftForm.content"
            label="Content"
            :error="errorsDraft?.content"
            :isRequired="true"
          />
          <div v-else v-html="draftForm.content" class="overflow-hidden"></div>
        </div>
        <template v-if="!draftForm.view" #footer>
          <div class="w-full flex items-center justify-center space-x-2">
            <Button
              label="Submit"
              color="green"
              style="padding: 2px 10px; border-radius: 5px"
              :loading="loading.draftForm"
              @click="draftFormSubmit"
            />
          </div>
        </template>
      </Modal>

      <!-- Approve Modal -->
      <Modal
        v-model="showApproveModal"
        title="Approve"
        size="xl"
        @close="showApproveModal = false"
      >
        <div class="p-4">
          <div class="max-h-[80vh] min-h-[80vh]">
            <SelectInput
              v-model="approvalForm.draft_id"
              search
              type="select"
              placeholder="Select Version"
              :options="draftDropdown"
              :error="errorsApproval.draft_id"
              @change="selectApprovalDraft"
            />
            <div v-html="approvalForm.content" class="overflow-hidden"></div>
          </div>
        </div>
        <template #footer>
          <div class="w-full flex items-center justify-center space-x-2">
            <Button
              label="Close"
              color="gray-outline"
              style="padding: 4px 20px; border-radius: 5px"
              @click="showApproveModal = false"
            />
            <Button
              v-if="submitbtn === 'esign'"
              label="eSign"
              color="blue"
              style="padding: 4px 20px; border-radius: 5px"
              @click="sendToEsign"
            />
            <Button
              v-if="
                submitbtn === 'approve' ||
                $env.VITE_COMMITTEE_ESIGN_BYPASS === 'true'
              "
              label="Approve"
              color="blue"
              style="padding: 4px 20px; border-radius: 5px"
              @click="approvedSubmit"
            />
          </div>
        </template>
      </Modal>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import {
  Modal,
  FileUpload,
  Button,
  TextInput,
  CheckboxGroup,
} from '@sds/oneui-common-ui';
import DataTable from '@/components/Datatable/DataTable.vue';
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SelectInput from '@/components/SelectInput.vue';
import { Icon } from '@iconify/vue';
import { useRoute, useRouter } from 'vue-router';
import { formatDate } from '@/utils/global';
import { tocMarkStatus, $env } from '@/constant/global';
import { useValidation, approvalNoticeValSchema } from '@/constant/validation';
import { formFieldValidator } from '@/utils/formFieldValidator';
import { formDataPayload } from '@/utils/formdatapayload';
import {
  fetchApprovalView,
  sendBackToApproval,
  postConvertNote,
  fetchSendTo,
  postNotes,
  discardNotes,
  submitReview,
  submitApproval,
  sendEsign,
  statusEsign,
  getDraftList,
  getDraftAll,
  postDraftSave,
  getTocList,
  getTocView,
  postTocSave,
  postTocMark,
  PullBackApproval,
} from '@/services/approval';
import { debounce } from '@/utils/debounce';
import { fireToast } from '@/mixins/toast';

// State
const route = useRoute();
const router = useRouter();

const module_id = route.params.id;
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
const isDraft = ref(true);
const submitbtn = ref('esign');
const module = ref({});
const reviewusers = ref([]);
const form = reactive({
  remarks: '',
  review_to: '',
});
const showApproveModal = ref(false);
const showTimelineModal = ref(false);
const showReviewModal = ref(false);
const showTocFormwModal = ref(false);
const showDraftFormwModal = ref(false);
const tocColumns = [
  { key: 'id', label: 'Receipt No.', type: 'String' },
  { key: 'marked_as', label: 'Marked As', type: 'String' },
  { key: 'subject', label: 'Subject', type: 'String' },
  { key: 'remarks', label: 'Remarks', type: 'String' },
  { key: 'page', label: 'Page', type: 'String' },
  { key: 'attached_by', label: 'Attached By', type: 'String' },
  { key: 'attached_on', label: 'Attached On', type: 'String' },
  { key: 'issued_on', label: 'Issued On', type: 'String' },
];
const tocList = ref([]);
const draftColumns = [
  { key: 's_no', label: 'S. No.', type: 'String' },
  { key: 'version', label: 'Version', type: 'String' },
  { key: 'draft_no', label: 'Draft No.', type: 'String' },
  { key: 'subject', label: 'Subject', type: 'String' },
  { key: 'created_at', label: 'Updated At', type: 'String' },
];
const draftList = ref([]);
const draftDropdown = ref([]);
const draftDefault = ref({});
const tocPagination = reactive({
  per_page: 10,
  current_page: 1,
  last_page: 0,
});
const draftPagination = reactive({
  per_page: 10,
  current_page: 1,
  last_page: 0,
});
const tocMarkedAsIds = ref([]);
const tocForm = reactive({
  subject: '',
  remarks: '',
  file: '',
});
const tocFormValidationSchema = {
  subject: approvalNoticeValSchema.toc.subject,
  remarks: approvalNoticeValSchema.toc.remarks,
  file: approvalNoticeValSchema.toc.file,
};
const { errors, validateField, validateAll } = useValidation(
  tocForm,
  tocFormValidationSchema
);
formFieldValidator(tocForm, validateField);

const draftForm = reactive({
  view: false,
  content: '',
});
const draftFormValidationSchema = {
  content: approvalNoticeValSchema.draft.content,
};
const {
  errors: errorsDraft,
  validateField: validateFieldDraft,
  validateAll: validateAllDraft,
} = useValidation(draftForm, draftFormValidationSchema);
formFieldValidator(draftForm, validateFieldDraft);

const approvalForm = reactive({
  draft_id: '',
  content: '',
});
const approvalFormValidationSchema = {
  draft_id: approvalNoticeValSchema.approve.draft_id,
};
const {
  errors: errorsApproval,
  validateField: validateFieldApproval,
  validateAll: validateAllApproval,
} = useValidation(approvalForm, approvalFormValidationSchema);
formFieldValidator(approvalForm, validateFieldApproval);

const resetAllError = debounce(
  () => Object.keys(errors).forEach(field => (errors[field] = '')),
  0
);

const filesName = ele => {
  try {
    let d = new Date(ele);
    return d.getDate() + '' + d.getMonth() + '' + d.getFullYear();
  } catch ($e) {
    return $e;
  }
};

const viewApprovalReset = () => {
  loading.page = true;
  fetchApprovalView({ params: { module_id: module_id } })
    .then(response => {
      module.value = response?.data ?? [];
    })
    .catch(error => {
      console.log(error.message);
    })
    .finally(() => {
      loading.page = false;
    });
};

if (route.params.id) {
  viewApprovalReset();
}

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

const sendtoback = event => {
  loading.sendback = true;
  loading.page = true;
  sendBackToApproval(module.value.id, { nextUser: event.core_user_id })
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: 'Submitted successfully.' });
        viewApprovalReset();
        return true;
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.sendback = false;
    });
};

const sendToReviewUser = () => {
  if (!form.review_to) {
    fireToast({ type: 'error', message: 'Please select user.' });
    return false;
  }
  loading.sendreview = true;
  loading.page = true;
  submitReview(module.value.id, {
    nextUser: form.review_to,
  })
    .then(response => {
      if (response) {
        showReviewModal.value = false;
        fireToast({ type: 'success', message: 'Submitted successfully.' });
        viewApprovalReset();
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.sendreview = false;
    });
};

const sendToEsign = async () => {
  if (!(await validateAllApproval())) return false;
  loading.page = true;
  sendEsign(module.value.id, approvalForm.draft_id, {
    redirect_url: import.meta.env.VITE_API_ESIGN_SUCCESS,
  })
    .then(response => {
      if (
        response?.success &&
        response?.data?.success &&
        response?.data?.esign?.redirectUrl
      ) {
        window.open(
          response?.data?.esign?.redirectUrl,
          'Esign Request',
          'width=1000,height=600,left=10,top=10,resizable=yes,scrollbars=yes'
        );
      } else {
        loading.page = false;
        fireToast({ type: 'error', message: 'Something went wrong!' });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

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

const createNote = color => {
  const newNote = {
    id: Date.now(),
    type: color == 'green' ? 1 : 0,
    content: '',
    saved: false,
    online: false,
    user: 'You',
    created_at: new Date(),
    closed: 0,
  };
  saveNote(newNote);
  module.value.notes.push(newNote);
};

const convertNote = note => {
  let tmr = extractContent(note.remarks).trim();
  if (tmr.length == 0) {
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

const extractContent = s => {
  var span = document.createElement('span');
  span.innerHTML = s;
  return span.textContent || span.innerText;
};

const saveNote = note => {
  let tmr = extractContent(note.remarks).trim();
  if (tmr.length == 0) {
    let result = window.confirm(
      'Are you sure you want to proceed with blank message?'
    );
    if (result) {
      finalsaveNote(note);
    }
  } else {
    finalsaveNote(note);
  }
};

const delNote = note => {
  loading.page = true;
  discardNotes(module.value.id, note.id)
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: response?.message });
        viewApprovalReset();
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

//postConvertNote
const finalConvertNote = note => {
  postConvertNote(module.value.id, note)
    .then(response => {
      if (response?.success && response?.data?.id) {
        note.id = response?.data?.id;
        note.saved = true;
        note.online = true;
        note.type = 1;
        fireToast({ type: 'success', message: response?.message });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

const finalsaveNote = note => {
  postNotes(module.value.id, note)
    .then(response => {
      if (response.success) {
        note.id = response.data.id;
        note.saved = true;
        note.online = true;
        fireToast({ type: 'success', message: response?.message });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

const approvedSubmit = async () => {
  loading.page = true;
  submitApproval(module.value.id, approvalForm.draft_id)
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: response.message });
        router.push({ name: 'notice-approval' });
      } else {
        fireToast({ type: 'error', message: 'Something went wrong!' });
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

const findTocList = () => {
  loading.toclist = true;
  const options = {
    page: parseInt(tocPagination?.current_page ?? 1),
    perPage: parseInt(tocPagination?.per_page ?? 10),
  };
  getTocList(module_id, { params: options })
    .then(response => {
      if (response.success && response?.data?.data) {
        tocList.value = response?.data?.data.map((item, index) => ({
          s_no:
            response.data.per_page * (response.data.current_page - 1) +
            index +
            1,
          id: item?.id,
          marked_as: item?.marked_as,
          subject: item?.subject,
          remarks: item?.remarks,
          page: item?.page,
          attached_by: item?.user?.full_name,
          attached_on: item?.attached_on,
          issued_on: item?.issued_on,
        }));

        tocPagination.current_page = response?.data?.current_page;
        tocPagination.per_page = response?.data?.per_page;
        tocPagination.last_page = response?.data?.last_page;
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    })
    .finally(() => {
      loading.toclist = false;
    });
};
findTocList();

const onPageChangeToc = current_page => {
  tocPagination.current_page = current_page;
  findTocList();
};

const onPageSizeChangeToc = per_page => {
  tocPagination.per_page = per_page;
  tocPagination.current_page = 1;
  findTocList();
};

const tocFormReset = () => {
  resetAllError();
  Object.keys(tocForm).map(key => {
    tocForm[key] = '';
  });
};

const handleTocFile = files => {
  tocForm.file = files[0];
};

const tocFormSubmit = async () => {
  if (!(await validateAll())) return false;
  loading.tocForm = true;
  const tocFormData = formDataPayload(tocForm);
  postTocSave(module_id, tocFormData)
    .then(response => {
      if (response.success) {
        showTocFormwModal.value = false;
        fireToast({ type: 'success', message: response.message });
        findTocList();
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.tocForm = false;
    });
};

const tocMarkSubmit = marked_as => {
  if (!(tocMarkedAsIds?.value?.length > 0)) {
    return fireToast({
      type: 'error',
      message: 'Please select at least one TOC row.',
    });
  }
  loading.tocmarkedas = marked_as;
  const payload = {
    marked_as: marked_as,
    toc_ids: tocMarkedAsIds.value,
  };
  postTocMark(payload)
    .then(response => {
      if (response.success) {
        tocMarkedAsIds.value = [];
        fireToast({ type: 'success', message: response.message });
        findTocList();
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: response?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.tocmarkedas = false;
    });
};

const tocView = () => {
  // getTocView
};

const findDraftAll = () => {
  getDraftAll(module_id)
    .then(response => {
      if (response.success && response?.data) {
        draftDropdown.value = response.data.map(item => ({
          value: item.id,
          label: `Version ${item.version} (Draft No. - ${item.draft_no})`,
          content: item.content,
          status: item.status,
        }));
        const draft = draftDropdown.value.find(
          draftItem => draftItem.status === 1
        );
        selectApprovalDraft(draft.value);
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    });
};
findDraftAll();

const selectApprovalDraft = value => {
  const draft = draftDropdown.value.find(
    draftItem => draftItem.value === value
  );
  approvalForm.draft_id = draft?.value;
  approvalForm.content = draft?.content;
};

const findDraftList = () => {
  loading.draftlist = true;
  const options = {
    page: parseInt(draftPagination?.current_page ?? 1),
    perPage: parseInt(draftPagination?.per_page ?? 10),
  };
  getDraftList(module_id, { params: options })
    .then(response => {
      if (response.success && response?.data?.data) {
        draftList.value = response?.data?.data.map((item, index) => ({
          s_no:
            response.data.per_page * (response.data.current_page - 1) +
            index +
            1,
          id: item?.id,
          content: item?.content,
          version: item?.version,
          draft_no: item?.draft_no,
          subject: item?.subject,
          status: item?.status,
          created_at: item?.created_at,
        }));
        draftPagination.current_page = response?.data?.current_page;
        draftPagination.per_page = response?.data?.per_page;
        draftPagination.last_page = response?.data?.last_page;
        if (!draftDefault.value?.id) {
          draftDefault.value = draftList.value?.find(
            draft => draft.status === 1
          );
        }
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    })
    .finally(() => {
      loading.draftlist = false;
    });
};
findDraftList();

const onPageChangeDraft = current_page => {
  draftPagination.current_page = current_page;
  findDraftList();
};

const onPageSizeChangeDraft = per_page => {
  draftPagination.per_page = per_page;
  draftPagination.current_page = 1;
  findDraftList();
};

const editDraftFrom = (row, view = false) => {
  draftForm.view = view;
  draftForm.content = row?.content ?? '';
  showDraftFormwModal.value = true;
};

const addDraftFrom = () => {
  editDraftFrom(draftDefault.value);
};

const draftFormReset = () => {
  resetAllError();
  Object.keys(draftForm).map(key => {
    tocForm[key] = '';
  });
};

const draftFormSubmit = async () => {
  if (!(await validateAllDraft())) return false;
  loading.draftForm = true;
  const draftFormPayload = formDataPayload(draftForm);
  postDraftSave(module_id, draftFormPayload)
    .then(response => {
      if (response.success) {
        showDraftFormwModal.value = false;
        if (response?.data?.id) draftDefault.value = response.data;
        fireToast({ type: 'success', message: response.message });
        findDraftList();
        findDraftAll();
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.draftForm = false;
    });
};

const pullToBack = () => {
  loading.page = true;
  PullBackApproval(module_id)
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: response.message });
        viewApprovalReset();
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    });
};
</script>

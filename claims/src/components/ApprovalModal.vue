<template>
  <Modal
    v-model="open"
    title="Approve"
    size="xl"
  >
    <!-- Body -->
    <div class="p-4">
      <div class="max-h-[80vh] min-h-[80vh]">
        <SelectInput
          v-model="draftId"
          search
          type="select"
          placeholder="Select Version"
          :options="drafts"
          :error="errors?.draft_id"
          @change="emit('draft-change', $event)"
        />

        <div
          v-html="content"
          class="overflow-hidden mt-3"
        />
      </div>
    </div>

    <!-- Footer -->
    <template #footer>
      <div class="w-full flex items-center justify-center space-x-2">
        <Button
          label="Close"
          color="gray-outline"
          style="padding: 4px 20px; border-radius: 5px"
          @click="close"
        />

    

        <Button
          label="Approve"
          color="blue"
          style="padding: 4px 20px; border-radius: 5px"
          @click="emit('approve')"
        />
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed } from 'vue'
import { Modal, Button } from '@sds/oneui-common-ui'
import SelectInput from '@/components/SelectInput.vue'

/* v-models */
const open = defineModel()
const draftId = defineModel('draftId')

const props = defineProps({
  drafts: { type: Array, default: () => [] },
  content: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
  submitbtn: { type: String, default: '' }
})

const emit = defineEmits(['draft-change', 'approve', 'eSign'])

const envBypass = computed(
  () => import.meta.env.VITE_COMMITTEE_ESIGN_BYPASS === 'true'
)

const close = () => {
  open.value = false
}
</script>

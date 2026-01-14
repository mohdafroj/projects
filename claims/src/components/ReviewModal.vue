<template>
  <Modal
    v-model="open"
    title="Send"
    size="sm"
  >
    <div class="p-5">
      <div class="mb-4 min-h-[200px]">
        <SelectInput
          v-model="reviewTo"
          search
          type="select"
          :loading="loadingUser"
          placeholder="Search Review User"
          :options="users"
          @search="emit('search', $event)"
        />
      </div>

      <div class="flex items-center justify-end gap-3 mt-6">
        <Button
          label="Cancel"
          color="gray-outline"
          :disabled="loadingSend"
          style="padding: 2px 10px; border-radius: 5px"
          @click="close"
        />

        <Button
          label="Send"
          color="green-outline"
          :loading="loadingSend"
          style="padding: 2px 10px; border-radius: 5px"
          @click="emit('send')"
        />
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { Modal, Button } from '@sds/oneui-common-ui'
import SelectInput from '@/components/SelectInput.vue'

/* v-models */
const open = defineModel()
const reviewTo = defineModel('reviewTo')

const props = defineProps({
  users: { type: Array, default: () => [] },
  loadingUser: { type: Boolean, default: false },
  loadingSend: { type: Boolean, default: false }
})

const emit = defineEmits(['search', 'send'])

const close = () => {
  open.value = false
}
</script>

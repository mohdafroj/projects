<template>
  <Modal
    v-model="open"
    title="Timeline"
    size="sm"
  >
    <div class="p-4 min-h-[200px] max-h-[70vh]">
      <div
        v-for="item in stakeholders"
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

        <Button
          v-if="item.action && allowAction"
          label="Send Back"
          color="blue-outline"
          style="padding: 2px 10px; border-radius: 5px"
          @click="emit('send-back', item)"
        />
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { Modal, Button } from '@sds/oneui-common-ui'
import { Icon } from '@iconify/vue'

/* v-model */
const open = defineModel()

const props = defineProps({
  stakeholders: { type: Array, default: () => [] },
  allowAction: { type: Boolean, default: false }
})

const emit = defineEmits(['send-back'])
</script>

<template>
  <div ref="fullscreenElement" class="bg-white rounded shadow-sm w-full max-w-max" >
    <div class="flex justify-between items-center p-3 border-b">
      <h3 class="text-gray-700 font-semibold text-lg">Add Notes</h3>
      <div class="flex items-center space-x-2 text-gray-600">
        <!-- <button class="hover:bg-gray-100 p-1 rounded">
          <Icon icon="mdi:menu" class="h-5 w-5" />
        </button> -->
        <button class="hover:bg-gray-100 p-1 rounded">
          <Icon icon="mdi:magnify" class="h-5 w-5" />
        </button>
        <button class="hover:bg-gray-100 p-1 rounded" @click="toggleFullscreen">
          <Icon icon="mdi:fullscreen" class="h-5 w-5" />
        </button>
         <!-- Reusable Fullscreen Button -->
       
        <button class="hover:bg-gray-100 p-1 rounded" @click="closeEditorMessages">
          <Icon icon="mdi:close" class="h-5 w-5" />
        </button>
      </div>
    </div>

    <!-- Vue Quill -->
    <div class="px-4 pt-4">
       <QuillEditorWrapper
      v-model:content="editorContent"
      contentType="html"
      theme="snow" />
      <p class="text-gray-500 text-sm mt-1" v-if="!editorContent">
        Keep your account secure with authentication step.
      </p>
    </div>

    <!-- Save Buttons -->
    <div class="flex items-center space-x-2 px-4 py-4">
      <button
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
    <div class="px-4 pb-4 space-y-3" v-if="notes.length">
      <!-- Note 1 -->
      <div
        v-for="note in notes"
        :key="note.id"
        :class="[
          note.isDraft ? 'bg-orange-100' : 'bg-green-50',
          'rounded p-3 relative',
        ]"
      >
        <div v-if="note.isDraft" class="flex justify-between items-center">
          <div class="flex items-center">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 mr-1 text-gray-600"
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
            <span class="font-medium text-sm">Draft Note</span>
          </div>
          <div class="flex space-x-1">
            <div class="text-xs text-gray-600">
              Created by {{ note.createdBy }} • Review • {{ note.username }}
            </div>
            <button class="bg-gray-600 text-white text-xs px-2 py-0.5 rounded">
              Publish
            </button>
          </div>
        </div>
        <div v-else>
          <div class="font-medium text-sm">
            Item id : {{ note.itemId }}, {{ note.itemName }}
          </div>
          <div class="font-medium text-sm">Note : #{{ note.noteNumber }}</div>
        </div>
        <p class="text-sm mt-1 text-gray-700" v-html="note.content"></p>
        <div class="mt-2 text-right">
          <div class="text-xs text-gray-600">{{ note.senderName }}</div>
          <div class="text-xs text-gray-600">{{ note.senderDesignation }}</div>
          <div class="text-xs text-gray-600">{{ note.timestamp }}</div>
        </div>
      </div>
    </div>

    <!-- <div class="flex justify-between px-4 py-3 bg-gray-50 rounded-b">
      <button
        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm"
      >
        Send Back To Member
      </button>
      <button
        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded text-sm"
      >
        Approve
      </button>
    </div> -->
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Icon } from "@iconify/vue";
import QuillEditorWrapper from "@/ui-components/QuillEditorWrapper.vue";

const editorContent = ref("");
const emit=defineEmits(['closeEditor']);
// Sample notes data
const notes = ref([
  {
    id: 2,
    itemId: "03",
    itemName: "Wooden Chair",
    noteNumber: "02",
    content:
      "In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.",
    senderName: "Sender Name",
    senderDesignation: "Sender Designation",
    timestamp: "Aug 13 2023 10:30 AM",
    isDraft: false,
  },
  {
    id: 1,
    itemId: "03",
    itemName: "Wooden Chair",
    noteNumber: "01",
    content:
      "In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.",
    senderName: "Sender Name",
    senderDesignation: "Sender Designation",
    timestamp: "Aug 13 2023 10:30 AM",
    isDraft: false,
  },
  {
    id: 3,
    itemId: "03",
    itemName: "Wooden Chair",
    noteNumber: "Draft",
    content:
      "In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.",
    senderName: "Sender Name",
    senderDesignation: "Sender Designation",
    timestamp: "Aug 13 2023 10:30 AM",
    isDraft: true,
    createdBy: "John Smith",
    username: "Username",
  },
]);

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

<template>
  <div  ref="fullscreenElement" class="bg-white rounded shadow-sm w-full max-w-max" >
    <div class="flex justify-between items-center p-3 border-b">
      <h3 class="text-gray-700 font-semibold text-lg">Add Notes</h3>
      <div class="flex items-center space-x-2 text-gray-600">
        <!-- <button class="hover:bg-gray-100 p-1 rounded">
          <Icon icon="mdi:menu" class="h-5 w-5" />
        </button> -->
        <!-- <button class="hover:bg-gray-100 p-1 rounded">
          <Icon icon="mdi:magnify" class="h-5 w-5" />
        </button> -->
        <button type="button" class="hover:bg-gray-100 p-1 rounded" @click="toggleFullscreen">
          <Icon icon="mdi:fullscreen" class="h-5 w-5" />
        </button>
         <!-- Reusable Fullscreen Button -->
       
        <button type="button" class="hover:bg-gray-100 p-1 rounded" @click="closeEditorMessages">
          <Icon icon="mdi:close" class="h-5 w-5" />
        </button>
      </div>
    </div>

    <!-- Vue Quill -->
    <div class="px-4 pt-4">
       <QuillEditorWrapper
       :key="editorKey"
      v-model:content="editorContent"
      contentType="html"
      theme="snow" />
      <p class="text-gray-500 text-sm mt-1" v-if="!editorContent">
        Keep your account secure with authentication step.
      </p>
    </div>
<!-- ==<pre>{{ props.requests }}</pre> -->
    <!-- Save Buttons -->
    <div class="flex items-center space-x-2 px-4 py-4">
      <button 
        @click="saveNotes('save')" 
        type="button" class="flex items-center rounded-md border border-blue-800 text-sm text-white px-3 py-2 bg-blue-800 hover:bg-blue-700">
        <Icon icon="tabler:send" width="14" height="14" class="mr-2" /> Add Remark
      </button>
      <button type="button"
       @click="saveNotes('draft')" 
      class="flex items-center rounded-md border border-blue-800 text-sm text-blue-800 px-3 py-2 bg-white hover:bg-blue-800 hover:text-white">
      <Icon icon="tabler:file-text" width="16" height="16" class="mr-2" /> Save as Draft</button>
      
    </div>

    <!-- Notes List -->
    <div class="px-4 pb-4 space-y-3" v-if="notes.length">
      <!-- Note 1 -->
      <div
        v-for="note in notes"
        :key="note.id"
        :class="[
          note.isDraft ? 'bg-orange-100' : 'bg-green-100',
          'rounded p-5 relative',
        ]"
      >  
        <div v-if="note.self_note=='false'">
          <div class="flex justify-between items-center">
          <div class="flex items-center">
            <Icon icon="tabler:edit" width="20" height="20"  class="mr-2"/>
            <div class="font-semibold text-md mb-1">Draft Note</div>
          </div>
          <div class="flex space-x-1">
            <button @click="publishNotes(note.desc)" type="button" class="flex items-center rounded-2xl border border-neutral-600 text-sm text-white px-3 py-1 bg-neutral-600 mb-1 font-medium">
          Publish <Icon icon="tabler:send-2" width="16" height="16" class="ml-2" /></button>
          </div>
           </div>
           <div class="text-xs text-gray-600 mb-3">
              Created by {{ note.createdBy }} &nbsp; • &nbsp; Review &nbsp; • &nbsp; {{ note.username }}
            </div>
        </div>
       
        <div v-else>
          <!-- <div class="font-semibold text-sm mb-1">
            Item id : {{ note.itemId }}, {{ note.title }}
          </div> -->
          <div class="font-semibold text-md mb-1">Note : #{{ note.note_count }}</div>
        </div>
        <p class="text-sm mt-1 text-gray-700" v-html="note.desc"></p>
        <div class="mt-2 text-right">
          <div class="text-sm text-gray-600 font-semibold mb-1">{{ note.actor_name }}</div>
          <div class="text-xs text-gray-600 font-semibold mb-1">{{ note.actor_designation }}</div>
          <div class="text-xs text-gray-600 font-semibold italic">{{ note.created_at }}</div>
        </div>
      </div>
    </div> 
    <!-- Notes List -->
  </div>
</template>

<script setup>
import { ref,defineEmits,watch, defineProps,computed} from "vue";
import { Icon } from "@iconify/vue";
import { addnote } from '@/services/rss/medicalClaims';
import QuillEditorWrapper from "@/ui-components/QuillEditorWrapper.vue";
import Swal from 'sweetalert2' 

const props = defineProps({
  content: String,
  requests:Object,
  claimid:String
}); 

const isLoading = ref(false); 
const editorKey = ref(0);

const emit=defineEmits(['closeEditor',"update:content",'refreshNotes']);
const editorContent = ref(props.content || "");

watch(editorContent, (val) => {
  emit("update:content", val);
});

// Sync with prop changes from parent
watch(() => props.content, (val) => {
  //console.log('fffff',editorContent.value)
  if (val !== editorContent.value) {
    editorContent.value = val;
  }
}); 

const fullscreenElement = ref(null);
const notes = computed(() => {
  return props.requests ;
});

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


//checking quilleditor is empty or not 
const isContentEmpty = computed(() => {
  const temp = document.createElement('div');
  temp.innerHTML = editorContent.value || '';
  const text = temp.textContent || temp.innerText || '';
  return text.trim() === '';
});//checking quilleditor is empty or not 

//notes section-save notes
const saveNotes = async (mode)=>{
  isLoading.value=true;
  const selfNote = (mode=== 'draft')?0:1;
  let postDataNotes = {};
  if(!isContentEmpty.value){    
     postDataNotes = {
      description: editorContent.value,
      self_note: selfNote,       
      claim_id: props.claimid,
       
    };
    try{
      if(await addnote(postDataNotes)){ 
          Swal.fire({
          toast: true,
          position: "top-end",
          icon: "success",
          title: "Note Add successfully!",
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
        })
      // postData.description='';
       editorContent.value='';
       postDataNotes={};
       editorKey.value += 1;
       emit('refreshNotes');
       console.log('post NOtes',editorContent.value)
      }
      
    }catch(err){
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Failed to forward. Please try again",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    console.error('Error forwarding request:', err);
    } finally {
    isLoading.value = false;
  }  

  } else{
    return false;
  }

  
  
}
//notes section -save notes

//notes section-Publish notes
const publishNotes = async (desc)=>{
  console.log('publishhhh', desc);
  isLoading.value=true;
  let postDataNotes = {};
  if(desc){    
     postDataNotes = {
      description: desc,
      self_note: 1,       
      claim_id: props.claimid,       
    };
    try{
      if(await addnote(postDataNotes)){ 
          Swal.fire({
          toast: true,
          position: "top-end",
          icon: "success",
          title: "Note Add successfully!",
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
        })
      // postData.description='';
       desc='';
       postDataNotes={}; 
        emit('refreshNotes');
       console.log('post NOtes',editorContent.value)
      }
      
    }catch(err){
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Failed to forward. Please try again",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
      console.error('Error forwarding request:', err);
    } finally {
    isLoading.value = false;
  }
  } else{
    Swal.fire({
          toast: true,
          position: "top-end",
          icon: "error",
          title: "Note empty!",
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true,
        })
  }

  
  
}
//notes section -Publish notes 
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

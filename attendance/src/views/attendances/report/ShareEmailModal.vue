<template>
    <Modal 
      v-model="showModal"
      title="Share By Email"
      size="xl"
      @close="handleClose"
    >     
      <div>
       
  <form @submit.prevent="handleSubmit">
      <div class="mb-4">        
          <TextInput
            :isRequired="true"
            :modelValue="formData.to"
            label="To: "
            @update:modelValue="(data) => {
              formData = {...formData, to: data}
              formErrors = {...formErrors, to: ''}
            }
            "
          />
          <span v-if="formErrors?.to" class="text-sm text-red-500">{{ formErrors.to }}</span>
      </div>

      <div class="mb-4">
          <QuillEditorWrapper
            v-model:content="editorContent"
            editorHeight="170px"
            contentType="html"
            ref="editorRef"
            :defaultContent="defaultContent"
            @change="(data) => handleEditorContent(data)"
            theme="snow" />          
          <span v-if="formErrors?.email_body" class="text-sm text-red-500">{{ formErrors.email_body }}</span>
        
      </div>

    <div class="flex justify-end items-center space-x-2 mt-4">
      <Button type="submit" label="Send" size="sm" color="green-outline" />
      <Button type="reset" label="Clear" size="sm" color="red-outline" @click="resetForm" />
    </div>
  </form>
        
      </div>
    </Modal>
  </template>
  
  <script setup>
  import { ref, computed } from 'vue'
  import { Modal, Button, TextInput } from '@sds/oneui-common-ui';
  import { shareReportByEmail } from '@/services/attendanceService';
  import Swal from 'sweetalert2';
  import QuillEditorWrapper from '@/components/QuillEditorWrapper.vue';

  // Props
  const props = defineProps({
    modelValue: {
      type: Boolean,
      default: false
    },
    selectedDate: {
      type: String,
      default:''
    }
  })
  
  // Emits
  const emit = defineEmits(['update:modelValue', 'close', 'retry'])

  // Computed
  const showModal = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })
  
  const handleClose = () => {
    emit('close')
  }
  
  const editorRef = ref(null);
  const editorContent = ref('')
  const defaultContent = ref(`Dear Madam/Sir,
I am sharing the attendance report for {{DATE}} for your kind perusal.
 Please find the details enclosed below for your reference.
Thank you.
Yours faithfully,
 {{OFFICER_NAME}}
 Lobby Office`);
  const formInstance = {to: '', email_body: '', date: ''};
  const formData = ref({...formInstance})
  const formErrors = ref({});
  const sendAction = ref(false);

  const handleEditorContent = (data) => {
    formErrors.value.email_body = '';
  };

  const handleSubmit = async () => {
    if ( sendAction.value ) return;
    if ( editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim() == '' ) {
      formErrors.value.email_body = 'Please enter email content.'
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if ( formData.value.to == '' ) {
      formErrors.value.to = 'Please enter email id.'
    } else if ( !emailRegex.test(formData.value.to) ) {
      formErrors.value.to = 'Please enter valid email id.'
    }

    formData.value.email_body = editorContent.value;

    let isNotValid = false;
    Object.keys(formErrors.value).map(k => {
      if ( formErrors.value[k] != "" ) {
        isNotValid = true;
      }
    });    
    if ( isNotValid ) {
      return;
    }
    formData.value.date = props.selectedDate;
    sendAction.value = true;
    const response = await shareReportByEmail(formData.value);
    
    if (  response.isError ) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: response.customMessage,
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true
      });
    } else if (response.success_code === 200 && response.data) {
      resetForm();
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Email have sent successfully',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true
      });
    }
    sendAction.value = false;
  };
  
  const resetForm = () => {
    formData.value = { ...formInstance };
    formErrors.value = {};
    editorContent.value = '';
    editorRef.value?.clearEditor();
  };

</script>
<template>
  <div class="backdrop" @click.self="$emit('close')">
    <div class="modal-box">      
      <div style="float:right"><button @click="$emit('close')">X</button></div>
      <br>
     <div style="margin-left:33%"><img src="/assets/images/logo/success_loader.gif" width="100px;" alt="Success" class="logo" />
      </div>
      
      <div class="textcls">Card request submitted successfully for approval!</div>
    </div>
  </div>
</template>
<script setup>
import { defineEmits } from 'vue'
const emit = defineEmits(['close'])
</script>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}
.textcls {
    width:400;
    height:48px;
    top:207.42px; 
    font-weight: 700;
    size:20px;
    line-height: 27px;
    letter-spacing: 0px;
    padding-left:17px;
    margin-top:18px;
    text-align: center;
 
}
 
.modal-box {
  min-height: 300px; /* set your desired minimum height */
  max-width: 500px;  /* optional: max width */
  padding: 20px;     /* padding inside modal */
  background: white; /* make sure background is visible */
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>

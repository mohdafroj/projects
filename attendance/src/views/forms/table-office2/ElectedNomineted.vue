<template>
    <Textinput
      label="Member First Name"
      type="text"
      placeholder="Input First Name"
      name="re_userName"
      v-model="username"
      :error="errors.username"
    />
    <Textinput
      label="Member Middle Name"
      type="text"
      placeholder="Input Middle Name"
      name="m_middleName"
      v-model="username"
      :error="errors.username"
    />
    <Textinput
      label="Member Last Name"
      type="text"
      placeholder="Input Middle Name"
      name="m_lastName"
      v-model="username"
      :error="errors.username"
    />
  
    <Textinput
      label="Must only consist of numbers"
      type="date"
      placeholder="Enter Number Only"
      name="re_number"
      v-model="number"
      :error="errors.number"
    />
    <Select label="State / UT" v-model="formData.stateUT" :options="states" placeholder="Select State / UT" />
  
    
  
    
  
    
  
    <div class="lg:col-span-2 float-right">
      <Button text="Submit" btnClass="btn-primary"></Button>
    </div>
  </template>
  
  <script setup>
  import {  defineModel } from "vue";
  import Button from "@/ui-components/Button.vue";
  import Textarea from "@/ui-components/Textarea.vue";
  import Textinput from "@/ui-components/Textinput.vue";
import Select from "@/ui-components/Select.vue";
  
  // Props for form validation errors
  const props = defineProps({ errors: Object });
  
  // v-model bindings
  const username = defineModel("username");
  const number = defineModel("number");
  const betweenNumber = defineModel("betweenNumber");
  const alphabetic = defineModel("alphabetic");
  const length = defineModel("length");
  const password = defineModel("password");
  const url = defineModel("url");
  const message = defineModel("message");
  const states = [
  { value: "option1", label: "Option 1" },
  { value: "option2", label: "Option 2" },
  { value: "option3", label: "Option 3" },
];
  </script>
  
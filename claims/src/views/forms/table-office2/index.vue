<template>
    <Card title="Member Onboarding Initiation">
      <form @submit.prevent="onSubmit" class="lg:grid-cols-3 grid gap-5 grid-cols-1">
        
        <!-- Elected / Re-elected / Nominated -->
        <div class="form-group col-span-3">
          <label class="block text-sm font-semibold mb-2">Is member Elected, Re-elected or Nominated</label>
          <RadioGroup 
            v-model="electedOrReelected"
            :options="electedOrReelectedOptions"
            name="electedOrReelected"
            gridClass="grid grid-cols-2 md:grid-cols-4 gap-4"
          />
          <p v-if="errors.electedOrReelected" class="text-red-500 text-sm">
            {{ errors.electedOrReelected }}
          </p>
        </div>
        
        <hr class="col-span-3 my-4"/>
        
        <!-- Include the child components -->
        <SearchMember v-if="electedOrReelected === 'Re-elected'" v-model="SearchMethods" :errors="errors" />
        <ElectedNominated v-else
          v-model:username="username"
          v-model:number="number"
          v-model:betweenNumber="betweenNumber"
          v-model:alphabetic="alphabetic"
          v-model:length="length"
          v-model:password="password"
          v-model:url="url"
          v-model:message="message"
          :errors="errors"
        />
  
      </form>
    </Card>
  </template>
  
  <script setup>
  import Button from "@/ui-components/Button.vue";
  import Card from "@/ui-components/Card.vue";
  import RadioGroup from "@/ui-components/RadioGroup.vue";
  import SearchMember from "./SearchMember.vue";
  import ElectedNominated from "./ElectedNomineted.vue"
  import { useForm } from "vee-validate";
  import * as yup from "yup";
  import { ref } from "vue";
  import { toast } from "vue3-toastify";
  
  // ✅ Radio Group Options
  const electedOrReelectedOptions = ref([
    { label: "Re-elected", value: "Re-elected" },
    { label: "Elected", value: "Elected" },
    { label: "Nominated", value: "Nominated" }
  ]);
  
  
  
  // ✅ Validation Schema
  const schema = yup.object({
    electedOrReelected: yup.string().required("Please select an option"),
    username: yup.string().required("Username is required"),
    number: yup.number().required("Number is required").positive(),
    betweenNumber: yup.number().required("Number is required").positive().min(1).max(10),
    alphabetic: yup.string().required("This field is required").matches(/^[a-zA-Z]+$/, "Must only contain alphabetic characters"),
    length: yup.string().required("This field is required").min(3, "Must be at least 3 characters"),
    password: yup.string().required("Password is required").min(8, "Must be at least 8 characters"),
    url: yup.string().required("URL is required").url("Must be a valid URL"),
    message: yup.string().required("Message is required"),
  });
  
  // ✅ Form handling
  const { errors, handleSubmit, defineField } = useForm({
    validationSchema: schema,
    validateOnMount: false,
    validateOnBlur: true,   
    initialValues: { electedOrReelected: "Re-elected", SearchMethods: "search-by-ic" },
  });
  
  // ✅ Define Fields
  const [electedOrReelected, electedOrReelectedAttrs] = defineField("electedOrReelected");
  const [SearchMethods, SearchMethodsAttrs] = defineField("SearchMethods");
  const [username, usernameAttrs] = defineField("username");
  const [number, numberAttrs] = defineField("number");
  const [betweenNumber, betweenNumberAttrs] = defineField("betweenNumber");
  const [alphabetic, alphabeticAttrs] = defineField("alphabetic");
  const [length, lengthAttrs] = defineField("length");
  const [password, passwordAttrs] = defineField("password");
  const [url, urlAttrs] = defineField("url");
  const [message, messageAttrs] = defineField("message");
  
  const onSubmit = handleSubmit((formValues) => {
    toast.success("Form submitted successfully!");
    console.log("Form Submitted:", formValues);
  });
  </script>
  
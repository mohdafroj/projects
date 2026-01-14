<template>

  <Card title="Member Onboarding Form">
    <form @submit.prevent="onSubmit" class="lg:grid-cols-3 grid gap-5 grid-cols-1">

      <!-- First Name -->
      <Textinput label="First Name" type="text" placeholder="Enter First Name" name="first_name" v-model="firstName"
        v-bind="firstNameAttrs" :error="errors.first_name" />

      <!-- Middle Name -->
      <Textinput label="Middle Name" type="text" placeholder="Enter Middle Name" name="middle_name" v-model="middleName"
        v-bind="middleNameAttrs" :error="errors.middle_name" />

      <!-- Last Name -->
      <Textinput label="Last Name" type="text" placeholder="Enter Last Name" name="last_name" v-model="lastName"
        v-bind="lastNameAttrs" :error="errors.last_name" />

      <!-- Email ID -->
      <Textinput label="Email ID" type="email" placeholder="Enter Email" name="email" v-model="email"
        v-bind="emailAttrs" :error="errors.email" />

      <!-- Mobile No -->
      <Textinput label="Mobile No." type="text" placeholder="Enter Mobile Number" name="mobile" v-model="mobile"
        v-bind="mobileAttrs" :error="errors.mobile" />

      <div class="flex items-center w-full my-3 col-span-3">
        <div class="flex-grow border-t border-gray-600"></div>
        <span class="px-2 text-gray-700 font-semibold">Election/Nomination Details </span>
        <div class="flex-grow border-t border-gray-600"></div>
      </div>
    
      <!-- Elected/Nominated Radio Group -->
      <!-- <div class="form-group col-span-2">
          <label class="block text-sm font-semibold mb-2">Elected/Nominated</label>
          <RadioGroup v-model="electedOrNominated" :options="electedOptions" name="elected_nominated"
            gridClass="grid grid-cols-2 md:grid-cols-3 gap-4" />
          <p v-if="errors.elected_nominated" class="text-red-500 text-sm">
            {{ errors.elected_nominated }}
          </p>
        </div> -->
       

      <!-- Elected/Nominated Radio Group -->
      <div class="form-group col-span-2">
        <label class="block text-sm font-semibold mb-2">Elected/Nominated</label>
        <RadioGroup v-model="electedOrNominated" :options="electedOptions" name="elected_nominated"
          gridClass="grid grid-cols-2 md:grid-cols-3 gap-4" :error="errors.elected_nominated" />
        <!-- <p v-if="errors.elected_nominated" class="text-red-500 text-sm">
          {{ errors.elected_nominated }}
        </p> -->
      </div>
        <!-- Constituency State/UT -->
        <Select v-if="electedOrNominated === 'Elected' || electedOrNominated === 'Re-elected'" label="Constituency State/UT" name="constituency" v-model="constituency" :options="states" placeholder="Select State/UT" :error="errors.constituency" />
      
      <div>
        <!-- Party Selection (Shown only if Elected or Re-elected) -->
        <Select v-if="electedOrNominated === 'Elected' || electedOrNominated === 'Re-elected'" label="Select Party" name="selected_party"
          v-model="selectedParty" :options="parties" placeholder="Select Party" :error="errors.selected_party" class="mb-3"/>

        <!-- Date of Election / Nomination -->
        <Textinput label="Date of Election / Nomination" type="date" name="election_date" v-model="electionDate"
          v-bind="electionDateAttrs" :error="errors.election_date" />
      </div>
      <!-- Upload Gazette Notification -->
      <div class="">
        <label class="block text-sm font-semibold mb-2">Upload Gazette Notification</label>
        <!-- <DropZone label="Upload Gazette Notification" v-model="gazetteFile" :error="errors.gazette_file" placeholderText="Drop Gazette Notification here or click to upload." /> -->
        <DropZone
            v-model="gazetteFile" :error="errors.gazette_file" placeholderText="Drop Gazette Notification here or click to upload."
            :allowFileRemoval="true"
            :multiple="true"
            @files-changed="handleFilesChanged"
            
          />
      </div>
      <!-- Upload Election Certificate -->
      <div><label class="block text-sm font-semibold mb-2">Upload Election Certificate</label>
        <DropZone label="Upload Election Certificate" v-model="electionCertificateFile" :error="errors.election_certificate" placeholderText="Drop Election Certificate here or click to upload" />
      </div>
      <div class="flex items-center w-full my-3 col-span-3">
        <div class="flex-grow border-t border-gray-600"></div>
        <span class="px-2 text-gray-700 font-semibold"> Term Information </span>
        <div class="flex-grow border-t border-gray-600"></div>
      </div>
      <!-- Term Start Date -->
      <Textinput label="Term Start Date" type="date" name="term_start_date" v-model="termStartDate"
        v-bind="termStartDateAttrs" :error="errors.term_start_date" />

      <!-- Term End Date -->
      <Textinput label="Term End Date" type="date" name="term_end_date" v-model="termEndDate" v-bind="termEndDateAttrs"
        :error="errors.term_end_date" />


      <!-- Term in Rajya Sabha -->
      <Textinput label="Term in Rajya Sabha" type="text" name="term_in_rs" placeholder="Enter Term" v-model="termInRS"
        v-bind="termInRSAttrs" :error="errors.term_in_rs" />

      <!-- Oath Preference Date -->
      <Textinput label="Oath Preference Date" type="date" name="oath_date" v-model="oathDate" v-bind="oathDateAttrs"
        :error="errors.oath_date" />

      <!-- Oath Preference Language -->
      <Select label="Oath Preference Language" name="oath_language" v-model="oathLanguage" :options="languages"
        placeholder="Select Language" :error="errors.oath_language" />




      <!-- Submit Button -->
      <div class="lg:col-span-3 flex justify-end">
        <Button text="Submit" btnClass="btn-primary" />
      </div>

    </form>
  </Card>
</template>



<script setup>
import { ref, watch } from "vue";
import { useForm } from "vee-validate";
import * as yup from "yup";
import Button from "@/ui-components/Button.vue";
import Card from "@/ui-components/Card.vue";
import Textinput from "@/ui-components/Textinput.vue";
import Select from "@/ui-components/Select.vue";
import RadioGroup from "@/ui-components/RadioGroup.vue";
import DropZone from "@/ui-components/DropZone.vue";
import { toast } from "vue3-toastify";

// Radio options for Elected/Nominated
const electedOptions = ref([
  { label: "Elected", value: "Elected" },
  
  { label: "Nominated", value: "Nominated" }
]);

// Select options
const states = ref([
  { label: "Delhi", value: "Delhi" },
  { label: "Maharashtra", value: "Maharashtra" }
]);

const parties = ref([
  { label: "Party A", value: "Party A" },
  { label: "Party B", value: "Party B" },
  { label: "Party C", value: "Party C" }
]);

const languages = ref([
  { label: "English", value: "English" },
  { label: "Hindi", value: "Hindi" }
]);

// Validation schema
const schema = yup.object({
  first_name: yup.string().required("First Name is required"),
  middle_name: yup.string().required("Middle Name is required"),
  last_name: yup.string().required("Last Name is required"),
  email: yup.string().email("Invalid email format").required("Email is required"),
  mobile: yup.string().matches(/^\d{10}$/, "Mobile Number must be 10 digits").required("Mobile Number is required"),
  constituency: yup.string().required("Please select a Constituency"),
  elected_nominated: yup.string().required("Please select an option"),
  // selected_party: yup.string().when("elected_nominated", {
  //   is: (val) => val === "Elected" || val === "Re-elected",
  //   then: yup.string().required("Party selection is required for elected members")
  // }),
  selected_party: yup.string().when("elected_nominated", {
    is: (val) => val === "Elected" ,
    then: yup.string().required("Party selection is required for elected members")
  }),
  election_date: yup.date().required("Election/Nomination date is required"),
  gazette_file: yup.mixed().required("Gazette Notification is required"),
  election_certificate: yup.mixed().required("Election Certificate is required"),
  term_start_date: yup.date().required("Start date is required"),
  term_end_date: yup.date().required("End date is required"),
  term_in_rs: yup.string().required("Term in Rajya Sabha is required"),
  oath_date: yup.date().required("Oath preference date is required"),
  oath_language: yup.string().required("Oath Language is required"),
});

// Form handling
const { errors, handleSubmit, defineField } = useForm({
  validationSchema: schema,
  validateOnMount: false,
  validateOnBlur: true,
   initialValues: { elected_nominated: "Elected" },
});

// Define fields
const [firstName, firstNameAttrs] = defineField("first_name");
const [middleName, middleNameAttrs] = defineField("middle_name");
const [lastName, lastNameAttrs] = defineField("last_name");
const [email, emailAttrs] = defineField("email");
const [mobile, mobileAttrs] = defineField("mobile");
const [constituency, constituencyAttrs] = defineField("constituency");
const [electedOrNominated, electedOrNominatedAttrs] = defineField("elected_nominated");
const [selectedParty, selectedPartyAttrs] = defineField("selected_party");
const [electionDate, electionDateAttrs] = defineField("election_date");
const [gazetteFile, gazetteFileAttrs] = defineField("gazette_file");
const [electionCertificateFile, electionCertificateFileAttrs] = defineField("election_certificate");
const [termStartDate, termStartDateAttrs] = defineField("term_start_date");
const [termEndDate, termEndDateAttrs] = defineField("term_end_date");
const [termInRS, termInRSAttrs] = defineField("term_in_rs");
const [oathDate, oathDateAttrs] = defineField("oath_date");
const [oathLanguage, oathLanguageAttrs] = defineField("oath_language");

// Watch for changes in electedOrNominated to reset selectedParty when "Nominated" is selected
watch(electedOrNominated, (newValue) => {
  if (newValue === "Nominated") {
    selectedParty.value = ""; // Reset party selection
  }
});

// Submit function
const onSubmit = handleSubmit((formValues) => {
  console.log("Form Submitted:", formValues);
  toast.success("Form has been submitted successfully")
});
</script>

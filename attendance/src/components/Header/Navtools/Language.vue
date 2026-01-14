<!-- <template>
  <div>
    <Listbox v-model="selectLanguage">
      <div class="relative z-[22]">
        <ListboxButton
          class="relative w-full flex items-center cursor-pointer space-x-[6px]"
        >
          <span class="inline-block md:h-8 md:w-8 w-6 h-6 rounded-full"
            ><img
              :src="selectLanguage.image"
              alt=""
              class="h-full w-full object-cover rounded-full"
          /></span>
          <span
            class="text-sm md:block hidden font-medium text-slate-600 dark:text-slate-300"
            >{{ selectLanguage.name }}</span
          >
        </ListboxButton>

        <Transition
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <ListboxOptions
            class="absolute min-w-[100px] right-0  md:top-[44px] top-[34px] w-auto max-h-60 overflow-auto border border-slate-200 dark:border-slate-700 rounded bg-white dark:bg-slate-800 mt-1"
          >
            <ListboxOption
              v-slot="{ active }"
              v-for="item in months"
              :key="item.name"
              :value="item"
              as="template"
            >
              <li
                :class="[
                  active
                    ? 'bg-slate-100 dark:bg-slate-700 dark:bg-opacity-70 bg-opacity-50 dark:text-white '
                    : 'text-slate-600 dark:text-slate-300',
                  'w-full border-b border-b-gray-500 border-opacity-10 px-2 py-2 last:border-none last:mb-0 cursor-pointer first:rounded-t last:rounded-b',
                ]"
              >
                <div class="flex items-center space-x-2 ">
                  <span class="flex-none">
                    <-- <span
                      class="lg:w-6 lg:h-6 w-4 h-4 rounded-full inline-block"
                    >
                      <img
                        :src="item.image"
                        alt=""
                        class="w-full h-full object-cover relative top-1 rounded-full"
                      />
                    </span> ->
                  </span>
                  <span class="flex-1 lg:text-base text-sm capitalize">
                    {{ item.name }}</span
                  >
                </div>
              </li>
            </ListboxOption>
          </ListboxOptions>
        </Transition>
      </div>
    </Listbox>
  </div>
</template>

<script setup>

import { ref } from "vue";
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption,
} from "@headlessui/vue";

const months = [
  { name: "English", image: "/assets/images/all-img/flag.png" },
  { name: "Hindi", image: "/assets/images/all-img/flag.png" },
];
const selectLanguage = ref(months[0]);
</script> -->

<template>
  <div>
    <Listbox v-model="selectLanguage" @update:modelValue="changeLanguage">
      <div class="relative z-[22]">
        <ListboxButton class="relative w-full flex items-center cursor-pointer space-x-[6px]">
          <span class="inline-block md:h-8 md:w-8 w-6 h-6 rounded-full">
            <img :src="selectLanguage.image" alt="" class="h-full w-full object-cover rounded-full" />
          </span>
          <span class="text-sm md:block hidden font-medium text-slate-600 dark:text-slate-300">
            {{ selectLanguage.name }}
          </span>
        </ListboxButton>

        <Transition leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <ListboxOptions
            class="absolute min-w-[80px] right-0 md:top-[44px] top-[34px] w-auto max-h-60 overflow-auto border border-slate-200 dark:border-slate-700 rounded bg-white dark:bg-slate-800 mt-1"
          >
            <ListboxOption
              v-slot="{ active }"
              v-for="item in languages"
              :key="item.code"
              :value="item"
              as="template"
            >
              <li
                :class="[
                  active
                    ? 'bg-slate-100 dark:bg-slate-700 dark:bg-opacity-70 bg-opacity-50 dark:text-white'
                    : 'text-slate-600 dark:text-slate-300',
                  'w-full border-b border-b-gray-500 border-opacity-10 px-2 py-2 last:border-none last:mb-0 cursor-pointer first:rounded-t last:rounded-b',
                ]"
              >
                <div class="flex items-center space-x-2">
                  <!-- <span class="flex-none">
                    <span class="lg:w-6 lg:h-6 w-4 h-4 rounded-full inline-block">
                      <img :src="item.image" alt="" class="w-full h-full object-cover relative top-1 rounded-full" />
                    </span>
                  </span> -->
                  <span class="flex-1 lg:text-base text-sm capitalize">
                    {{ item.name }}
                  </span>
                </div>
              </li>
            </ListboxOption>
          </ListboxOptions>
        </Transition>
      </div>
    </Listbox>
  </div>
</template>

<script setup>
import { ref, watchEffect } from "vue";
import { useI18n } from "vue-i18n";
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption,
} from "@headlessui/vue";

// Use Vue I18n
const { locale } = useI18n();

// Define available languages
const languages = [
  { name: "English", code: "en", image: "/assets/images/all-img/flag.png" },
  { name: "हिन्दी", code: "hi", image: "/assets/images/all-img/flag.png" },
];

// Set default language from localStorage or fallback to 'en'
const selectedLanguageCode = ref(localStorage.getItem("language") || "en");

// Find the matching language object based on stored language code
const selectLanguage = ref(languages.find(lang => lang.code === selectedLanguageCode.value) || languages[0]);

// Function to update language
const changeLanguage = (lang) => {
  locale.value = lang.code; // Set i18n locale
  localStorage.setItem("language", lang.code); // Store selection in localStorage
  selectLanguage.value = lang; // Update selected language in UI
};

// Ensure selected language is set correctly when the component mounts
watchEffect(() => {
  locale.value = selectLanguage.value.code;
});
</script>


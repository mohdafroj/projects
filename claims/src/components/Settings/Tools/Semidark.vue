<template>
  <div>
    <div class="flex justify-between mt-6 items-center">
      <div class="text-slate-600 text-base dark:text-slate-300">Dark Navigation</div>
      <div>
        <label
          :class="semidark ? 'bg-primary-700' : 'bg-secondary-700'"
          class="relative inline-flex h-6 w-[46px] items-center rounded-full transition-all duration-150 cursor-pointer"
        >
          <input type="checkbox" v-model="semidark" class="hidden" />
          <span
            :class="
              semidark
                ? 'translate-x-6 '
                : 'translate-x-[2px]'
            "
            class="inline-block h-5 w-5 transform rounded-full bg-white transition-all duration-150"
          />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watchEffect } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; 

//  Pinia store
const themeSettingsStore = useThemeSettingsStore();

// Initialize the semidark
const semidark = ref(themeSettingsStore.semidark);

// Watch semidark variable and apply the corresponding changes
watchEffect(() => {
  if (semidark.value) {
    themeSettingsStore.semidark = semidark.value;
    document.body.classList.remove("semi-light");
    document.body.classList.add("semi-dark");
    localStorage.setItem("semiDark", semidark.value);
  } else {
    themeSettingsStore.semidark = semidark.value;
    document.body.classList.remove("semi-dark");
    document.body.classList.add("semi-light");
    localStorage.setItem("semiDark", semidark.value);
  }
});
</script>

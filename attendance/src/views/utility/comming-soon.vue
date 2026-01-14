<template>
  <div class="min-h-screen">
    <div class="xl:absolute left-0 top-0 w-full">
      <div class="flex flex-wrap justify-between items-center py-6 container">
        <div>
          <router-link :to="{ name: 'home' }">
            <img
              src="/assets/images/logo/rs-logo.png"
              alt=""
              v-if="!this.$store.themeSettingsStore.isDark"
              class="w-40"
            />
            <img
              src="/assets/images/logo/rs-logo-white.svg"
              alt=""
              v-if="this.$store.themeSettingsStore.isDark"
              class="w-40"
            />
          </router-link>
        </div>
        <div>
          <Button text="Contact us"  btnClass="btn-secondary" outline />
        </div>
      </div>
    </div>
    <div class="container">
      <div class="flex justify-between flex-wrap items-center min-h-screen">
        <div class="max-w-[500px] space-y-4">
          <div
            class="relative flex space-x-3 items-center text-2xl text-slate-900 dark:text-white"
          >
            <span class="inline-block w-[25px] bg-gray-500 h-[1px]"></span
            ><span>Coming soon</span>
          </div>
          <div
            class="xl:text-[40px] xl:leading-[50px] font-semibold text-slate-900 dark:text-white"
          >
          This module is being built and will be available shortly
          </div>
          <p
            class="font-normal text-slate-600 dark:text-slate-300 max-w-[400px]"
          >
          We are actively working on this module and will announce its availability when it's complete.
          </p>
          <div class="bg-white flex items-center px-3 rounded">
            <input
              type="text"
              placeholder="Enter your email"
              class="flex-1 bg-transparent h-full block w-full py-6 placeholder:text-gray-500 text-base focus:outline-none focus:ring-0"
            />
            <div class="flex-none">
              <Button type="button" class=" px-6" btnClass="btn-secondary" outline>
                Notify me
              </Button>
            </div>
          </div>
         
        </div>
        <div>
          <img src="/assets/images/svg/img-1.svg" alt="" />
        </div>
      </div>
    </div>
    <div class="xl:fixed bottom-0 w-full">
      <div class="container">
        <div
          class="md:flex justify-between items-center flex-wrap space-y-4 py-6"
        >
          <div>
            
          </div>
          <div>
            <ul class="flex md:justify-start justify-center space-x-3">
              <li>
                <a
                  href="#"
                  class="text-slate-500 dark:text-slate-400 text-sm transition duration-150 hover:text-slate-900"
                  >Privacy policy</a
                >
              </li>
              <li>
                <a
                  href="#"
                  class="text-slate-500 dark:text-slate-400 text-sm transition duration-150 hover:text-slate-900"
                  >Faq</a
                >
              </li>
              <li>
                <a
                  href="#"
                  class="text-slate-500 dark:text-slate-400 text-sm transition duration-150 hover:text-slate-900"
                  >Email us</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import Button from "@/ui-components/Button.vue";

</script>


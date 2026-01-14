<template>
  <div class="space-y-5">
    <div class="grid md:grid-cols-3 sm:grid-cols-2 grid-cols-1 gap-5">
      <div
        class="bg-no-repeat bg-cover bg-center p-4 rounded-[6px] relative"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-3.png' + ')',
        }"
      >
        <div class="max-w-[180px]">
          <h4 class="text-xl font-medium text-white mb-2">
            Welcome Pushpendra Singh
          </h4>
          <p class="text-sm text-white text-opacity-80">
            Sampoorna Digital Sansad
          </p>
        </div>
        <div
          class="absolute top-1/2 -translate-y-1/2 right-6  mt-2 h-12 w-12 bg-white rounded-full text-xs font-medium flex flex-col items-center justify-center"
        >
          Now
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center p-4 rounded-[6px] relative"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-1.png' + ')',
        }"
      >
        <div class="max-w-[180px]">
          <div class="text-xl font-medium text-slate-900 mb-2">
            Welcome Pushpendra Singh
          </div>
          <p class="text-sm text-slate-800">Sampoorna Digital Sansad</p>
        </div>
        <div
          class="absolute top-1/2 -translate-y-1/2 right-6  mt-2 h-12 w-12 bg-white rounded-full text-xs font-medium flex flex-col items-center justify-center"
        >
          Now
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center p-4 rounded-[6px] relative"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-3-1.png' + ')',
        }"
      >
        <div class="max-w-[180px]">
          <div class="text-xl font-medium text-slate-900 mb-2">
            Welcome Pushpendra Singh
          </div>
          <p class="text-sm text-slate-800">Sampoorna Digital Sansad</p>
        </div>
        <div
          class="absolute top-1/2 -translate-y-1/2 right-6 left-6 mt-2 h-12 w-12 bg-white rounded-full text-xs font-medium flex flex-col items-center justify-center"
        >
          Now
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center p-5 rounded-[6px] relative"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-2.png' + ')',
        }"
      >

        <div class="max-w-[180px]">
          <h4 class="text-xl font-medium text-white mb-2">
            <span class="block font-normal">Good evening,</span>
            <span class="block">Mr. Pushpendra</span>
          </h4>
          <p class="text-sm text-white font-normal">Welcome to SDS Dashboard</p>
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center p-5 rounded-[6px] relative flex items-center"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-4.png' + ')',
        }"
      >
        <div class="flex-1">
          <div class="max-w-[180px]">
            <div class="text-xl font-medium text-slate-900 mb-2">
              <span class="block font-normal">Good evening,</span>
              <span class="block">Mr. Pushpendra</span>
            </div>
            <p class="text-sm text-slate-900 font-normal">
              Welcome to SDS Dashboard
            </p>
          </div>
        </div>
        <div class="flex-none">
          <img
            src="/assets/images/svg/widgetvector.svg"
            alt=""
            class="ml-auto"
          />
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center p-5 rounded-[6px] relative flex items-center"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-5.png' + ')',
        }"
      >
        <div class="flex-1">
          <div class="max-w-[180px]">
            <div
              class="text-xl font-medium text-white dark:text-slate-800 mb-2"
            >
              <span class="block font-normal">Good evening,</span>
              <span class="block">Mr. Pushpendra</span>
            </div>
            <p
              class="text-sm text-wgite text-white dark:text-slate-800 font-normal"
            >
              Welcome to SDS Dashboard
            </p>
          </div>
        </div>
        <div class="flex-none">
          <img
            src="/assets/images/svg/widgetvector2.svg"
            alt=""
            class="ml-auto"
          />
        </div>
      </div>

      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center px-5 py-8 rounded-[6px] relative flex items-center"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-6.png' + ')',
        }"
      >
        <div class="flex-1">
          <div class="max-w-[180px]">
            <h4 class="text-2xl font-medium text-white mb-2">
              <span class="block text-sm">Current balance,</span>
              <span class="block">₹34,564</span>
            </h4>
          </div>
        </div>
        <div class="flex-none">
          <Button
            icon="heroicons-outline:eye"
            text="View details"
            btnClass="btn-light bg-white btn-sm "
          />
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center px-5 py-8 rounded-[6px] relative flex items-center"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-7.png' + ')',
        }"
      >
        <div class="flex-1">
          <div class="max-w-[180px]">
            <h4 class="text-2xl font-medium text-slate-900 mb-2">
              <span class="block text-sm dark:text-slate-800"
                >Current balance,</span
              >
              <span class="block dark:text-slate-800">₹34,564</span>
            </h4>
          </div>
        </div>
        <div class="flex-none">
          <Button
            icon="heroicons-outline:eye"
            text="View details"
            btnClass="btn-light bg-white btn-sm "
          />
        </div>
      </div>
      <!-- ?? end Single ?? -->
      <div
        class="bg-no-repeat bg-cover bg-center px-5 py-8 rounded-[6px] relative flex items-center"
        :style="{
          backgroundImage:
            'url(' + '/assets/images/all-img/widget-bg-8.png' + ')',
        }"
      >
        <div class="flex-1">
          <div class="max-w-[180px]">
            <h4 class="text-2xl font-medium text-slate-900 mb-2">
              <span class="block text-sm dark:text-slate-800"
                >Current balance,</span
              >
              <span class="block dark:text-slate-800">₹34,564</span>
            </h4>
          </div>
        </div>
        <div class="flex-none">
          <Button
            icon="heroicons-outline:eye"
            text="View details"
            btnClass="btn-light bg-white btn-sm "
          />
        </div>
      </div>
    </div>
   
  </div>
</template>
<script setup>
import Button from "@/ui-components/Button.vue";

</script>
<style lang=""></style>

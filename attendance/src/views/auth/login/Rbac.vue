<template>
  <!-- <div class="relative w-full h-screen bg-cover bg-center" style="background: url(/assets/images/all-img/collage4.jpg) "> -->
  <div
    class="relative w-full h-screen bg-cover bg-center"
    :style="{ backgroundImage: `url(${backgroundImage})` }"
  >
    <div class="absolute inset-0 bg-black-900 bg-opacity-80"></div>

    <div class="absolute top-5 left-5">
      <img
        src="/assets/images/logo/rs-logo-white.png"
        class="w-40"
        alt="RS Logo"
      />
      <!-- <h1 class="text-red-600 text-3xl font-semibold">Rajya Sabha</h1> -->
    </div>

    <!--  Form Container -->
    <div class="relative z-10 flex justify-center items-center h-full">
      <div class="bg-[#000] bg-opacity-50 p-8 rounded-md shadow-lg w-[400px]">
        <h2 class="text-white text-3xl font-semibold mb-4">Sign In</h2>

        <form @submit.prevent="handleLogin" class="mt-4">
          <!-- Sign-in Code -->
          <button
            class="w-full bg-red-700 text-white py-3 rounded hover:bg-red-600 transition"
          >
            Use Parichay to Sign in
          </button>
          <!-- OR Divider -->
          <div class="flex items-center my-4">
            <div class="flex-grow border-t border-gray-600"></div>
            <span class="px-2 text-gray-400">OR</span>
            <div class="flex-grow border-t border-gray-600"></div>
          </div>

          <div class="mb-4">
            <input
              v-model="email"
              type="email"
              placeholder="Email or mobile number"
              class="w-full p-3 bg-gray-700 text-white rounded focus:outline-none focus:ring-2 focus:ring-red-500"
            />
          </div>

          <div class="mb-4">
            <input
              v-model="password"
              type="password"
              placeholder="Password"
              class="w-full p-3 bg-gray-700 text-white rounded focus:outline-none focus:ring-2 focus:ring-red-500"
            />
          </div>

          <button
            type="submit"
            class="w-full bg-black-600 text-white font-bold py-3 rounded hover:bg-black-700 transition"
          >
            Sign In
          </button>

          <p
            class="text-gray-400 text-sm text-center mt-4 hover:underline cursor-pointer"
          >
            Forgot password?
          </p>

          <div
            class="flex items-center justify-between mt-4 text-gray-400 text-sm"
          >
            <label class="flex items-center cursor-pointer">
              <input type="checkbox" class="mr-2" />
              Remember me
            </label>
          </div>

        </form>
      </div>
    </div>
  </div>
</template>



<script setup>
import { ref } from "vue";
import { toast } from "vue3-toastify";
import axios from "axios";
import { cookieService } from '@sds/oneui-layout';

const backgroundImage = "/assets/images/all-img/collage4.jpg";
const email = ref("");
const password = ref("");

const handleLogin = async () => {
  try {
    const response = await axios.post(import.meta.env.VITE_RBAC_BASE_URL + "rbac/login", {
      email: email.value,
      password: password.value,
    });
    if (response.data.success_code == 200 && response.data.data.token) {      
      let users = [{ id: response.data.data.user.id, name: response.data.data.user.name, email: response.data.data.user.email }];
      let rbac = response.data.data.user;
      let userAppData = { id: response.data.data.user.id, name: response.data.data.user.name, email: response.data.data.user.email, token: response.data.data.token, users };
      await cookieService.setData({ name: "userAppData", value: userAppData, non_primitive: 1, encode: 1, days: 10 });
      await cookieService.setLocalStorageData({ name: "rbacAppData", value: rbac, non_primitive: 1, encode: 1, days: 10 });
      toast.success("Login successful!");
      const base = import.meta.env.VITE_BASE_URL || '';
      window.location.href = base.replace(/\/$/, '') + '/attendance';     
    } else {
      toast.error("Login failed: " + response.data.message);
    }
  } catch (error) {
    console.error("Login error:", error);
    toast.error("An error occurred during login.");
  }
};
</script>
<template>
  <router-link :to="{ name: 'home' }">
    <img
      src="/assets/images/logo/rs-logo-white.png"
      alt=""
      class="w-12 h-auto"
      v-if="!this.$store.themeSettingsStore.isDark"
    />

    <img
      src="/assets/images/logo/rs-logo-c.png"
      alt=""
      class="w-12 h-auto"
      v-if="this.$store.themeSettingsStore.isDark"
    />
  </router-link>
</template>
<script></script>

import { cookieService } from '@sds/oneui-layout';
import { defineStore } from 'pinia';
export const useThemeSettingsStore = defineStore('themeSettings', {
  state: () => ({
    sidebarCollaspe: true,
    sidebarHidden: false,
    mobielSidebar: false,
    semidark: false,
    monochrome: false,
    semiDarkTheme: "semi-light",
    isDark: false,
    skin: "default",
    theme: "light",
    isOpenSettings: false,
    cWidth: "full",
    menuLayout: "vertical",
    navbarType: "sticky",
    isMouseHovered: false,
    footerType: "static",
    direction: false,
    chartColors: {
      title: "red",
    },
    login: '',
    encrypted: 'false',
    notification: { message: '', connection_id: '' }
  }),

  actions: {
    setSidebarCollaspe() {
      this.sidebarCollaspe = !this.sidebarCollaspe;
    },
    setRBAC(data) {
      this.login = data;
    },

    toggleDark() {
      this.isDark = !this.isDark;
      document.body.classList.remove(this.theme);
      this.theme = this.theme === "dark" ? "light" : "dark";
      document.body.classList.add(this.theme);
      localStorage.setItem("theme", this.theme);
    },

    toggleMonochrome() {
      const isMonochrome = localStorage.getItem('monochrome') !== null;
      if (isMonochrome) {
        localStorage.removeItem("monochrome");
        document.getElementsByTagName('html')[0].classList.remove('grayscale');
        return;
      }
      localStorage.setItem("monochrome", true);
      document.getElementsByTagName('html')[0].classList.add('grayscale');
    },

    toggleSettings() {
      this.isOpenSettings = !this.isOpenSettings;
    },

    toggleMsidebar() {
      this.mobielSidebar = !this.mobielSidebar;
    },

    toggleSemiDark() {
      this.semidark = !this.semidark;
      this.semiDarkTheme = this.semidark ? "semi-dark" : "semi-light";
      document.body.classList.toggle(this.semiDarkTheme);
      localStorage.setItem("semiDark", this.semidark);
    },
    initEncrypted() {
      const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });
      if (!userAppData || !userAppData.id) {
        localStorage.removeItem('encrypted');
      }
      let encrypted = localStorage.getItem('encrypted');
      if (!['true', 'false'].includes(encrypted)) {
        localStorage.setItem('encrypted', this.encrypted);
      }
    },

  },
});

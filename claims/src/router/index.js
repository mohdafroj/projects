import { createRouter, createWebHistory } from "vue-router";
import middlewarePipeline from "../middleware/middlewarePipeline";
import routes from './route';
import i18n from './../i18n';
const BASE = (import.meta.env.VITE_BASE_PATH || '/').replace(/\/+$/, '').replace(/^([^/])/, '/$1');
const router = createRouter({
  history: createWebHistory(BASE),
  base: BASE,
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    } else {
      return { top: 0 };
    }
  },
});
router.beforeEach((to, from, next) => {
  const t = i18n.global.t
  document.title = t('oneui') + " - ";
  if (to.meta && to.meta.title) {
    let siteTitle = '';
    if (Array.isArray(to.meta.title)) {
      siteTitle = to.meta.title.map(item => {
        return t(item);
      }).join(" ");
    } else {
      siteTitle = t(to.meta.title);
    }
    document.title += " " + siteTitle;
  } else {
    const nameText = to.name;
    const words = nameText.split(" ");
    const wordslength = words.length;
    for (let i = 0; i < wordslength; i++) {
      words[i] = words[i][0].toUpperCase() + words[i].substr(1);
    }
    document.title += words;
  }

  /** Navigate to next if middleware is not applied */
  if (!to.meta.middleware) {
    return next()
  }

  const middleware = to.meta.middleware;
  const context = { to, from, next }
  return middleware[0]({
    ...context,
    next: middlewarePipeline(context, middleware, 1)
  })
});

router.afterEach(() => {
  // Remove initial loading
  const appLoading = document.getElementById("loading-bg");
  if (appLoading) {
    appLoading.style.display = "none";
  }
});

export default router;

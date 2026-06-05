import "./bootstrap";
import "../css/app.css";

import { createApp } from "vue";
import App from "./App.vue";
import router from "./router/index";
import VCalendar from "v-calendar";
import { Calendar } from "v-calendar";
import "v-calendar/dist/style.css";
import store from "./store";
import { Modal } from "@kouts/vue-modal";
import "@kouts/vue-modal/dist/vue-modal.css";
import { createHead } from "@unhead/vue/client";

const app = createApp(App);
app.component("VCalendar", Calendar);
app.component("Modal", Modal);
app.use(VCalendar).use(store).use(router).use(Modal);
app.use(createHead());
app.mount("#app");

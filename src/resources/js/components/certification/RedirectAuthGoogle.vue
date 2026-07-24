<template></template>

<script setup lang="ts">
import { useRoute, useRouter } from "vue-router";
import { onMounted } from "vue";
import { useStore } from "vuex";
import axios from "axios";

const route = useRoute();
const router = useRouter();
const store = useStore();

onMounted(() => {
  axios.get("/sanctum/csrf-cookie").then(() => {
    axios
      .post(`${import.meta.env.VITE_APP_API_LOGIN_URL}/google/callback`, {
        // GoogleのURIで登録する場合以下２つ必須
        code: route.query.code,
        state: route.query.code,
      })
      .then((res) => {
        if (res.data.status_code === "404") {
          router.push("/login");
        } else if (res.data.provider) {
          const params = {
            provider: res.data.provider,
            email: res.data.email,
            token: res.data.token,
          };
          router.push({
            path: `/api/register/${params.provider}`,
            query: {
              email: params.email,
              token: params.token,
            },
          });
        } else {
          const token: string = res.data.access_token;
          localStorage.setItem("auth", token);
          router.push("/home");
          store.dispatch("loginState");
        }
      });
  });
});
</script>

<style></style>

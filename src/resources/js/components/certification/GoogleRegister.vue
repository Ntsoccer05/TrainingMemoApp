<template>
  <form @submit.prevent="register">
    <!-- UserName input -->
    <div class="relative mb-6" data-te-input-wrapper-init>
      <label for="username" class="block text-sm font-medium text-gray-700"
        >ユーザ名<span class="text-sm text-black-600">(※任意)</span>
      </label>
      <input
        type="text"
        class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-colors duration-300"
        id="username"
        placeholder=""
        v-model="name"
      />
      <p :class="dispNameErrMsg">{{ errors.name[0] }}</p>
    </div>

    <!-- Email input -->
    <div class="relative mb-6" data-te-input-wrapper-init>
      <label for="email" class="block text-sm font-medium text-gray-700"
        >メールアドレス
      </label>
      <input
        type="text"
        class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-colors duration-300"
        id="email"
        placeholder=""
        v-model="email"
        readonly
      />
    </div>

    <!--Submit button-->
    <div class="mb-12 pb-1 pt-1 text-center">
      <button
        class="mb-3 inline-block w-full rounded px-6 pb-2 pt-2.5 text-xs font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
        type="submit"
        data-te-ripple-init
        data-te-ripple-color="light"
        style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
      >
        新規登録
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import useValidationMsg from "../../composables/certification/useValidationMsg";
import dispValidationMsg from "../../composables/certification/useDispValidationMsg";
import axios from "axios";
import { DispErrorMsg, Errors } from "../../types/certification";

const route = useRoute();
const router = useRouter();
const store = useStore();

const name = ref<string>("");
const email: string = route.query.email as string;

const errors: Errors = reactive({
  name: [],
  email: [],
  password: [],
});
const dispErrorMsg: DispErrorMsg = reactive({
  name: false,
  email: false,
  password: false,
});

//バリデーションエラーメッセージのレイアウト
const { dispNameErrMsg } = dispValidationMsg(dispErrorMsg);

const register = async () => {
  await axios
    // CSRF保護
    .get("/sanctum/csrf-cookie")
    .then(() => {
      axios
        .post("/api/register/google", {
          name: name.value,
          email,
          // GoogleのURIを用いて登録する場合tokenが必須
          token: route.query.token,
        })
        .then((res) => {
          if (res.data.status_code === 200) {
            router.push("/");
            store.dispatch("loginState");
          }
        })
        .catch((err) => {
          // POST時のバリデーションエラー
          const errorMsgs: Errors = err.response.data.errors;
          useValidationMsg(errorMsgs, errors, dispErrorMsg);
        });
    })
    .catch((err) => {});
};
</script>

<style></style>

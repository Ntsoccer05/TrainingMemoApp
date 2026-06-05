<template>
  <form @submit.prevent="login">
    <!-- UserName input -->
    <!-- <div class="relative mb-6">
      <input
        type="text"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="name"
        v-model="name"
        placeholder=""
      />
      <label
        for="name"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >ユーザ名
      </label>
      <p :class="dispNameErrMsg">{{ errors.name[0] }}</p>
    </div> -->

    <!-- Email input -->
    <div class="relative mb-6">
      <input
        type="text"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="email"
        v-model="email"
        placeholder=""
        required
      />
      <label
        for="email"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >メールアドレス
      </label>
      <p :class="dispEmailErrMsg">{{ errors.email[0] }}</p>
    </div>

    <!-- Password input -->
    <div class="relative mb-6">
      <input
        :type="inputType"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="password"
        v-model="password"
        placeholder=""
      />
      <label
        for="password"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/3 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >パスワード
      </label>
      <label
        ><input type="checkbox" v-model="displayPass" class="mr-1" />パスワードを表示する
      </label>
      <div class="absolute top-3 text-right w-11/12 pointer-events-none">
        <span class="pointer-events-auto"
          ><i :class="iconType" @click="toggleDisplayPass"></i
        ></span>
      </div>
      <p :class="dispPassErrMsg">{{ errors.password }}</p>
    </div>

    <!--Submit button-->
    <div class="mb-6 md:mb-0 pb-1 pt-1 text-center">
      <button
        class="mb-3 inline-block w-full rounded px-6 pb-2 pt-2.5 text-base font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
        type="submit"
        data-te-ripple-init
        data-te-ripple-color="light"
        style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
      >
        ログイン
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive, computed, ComputedRef } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import useValidationMsg from "../../composables/certification/useValidationMsg";
import dispValidationMsg from "../../composables/certification/useDispValidationMsg";
import axios from "axios";
import { DispErrorMsg, Errors } from "../../types/certification";

const router = useRouter();
const store = useStore();
const name = ref<string>("");
const email = ref<string>("");
const password = ref<string>("");
const getUserMessage = ref<string>("");
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
const displayPass = ref(false);

// パスワードの表示／非表示
const inputType: ComputedRef<string> = computed(() => {
  return displayPass.value ? "text" : "password";
});
const iconType: ComputedRef<string> = computed(() => {
  return displayPass.value ? "fa-solid fa-eye-slash" : "fa-solid fa-eye";
});

//バリデーションエラーメッセージのレイアウト
const { dispNameErrMsg, dispEmailErrMsg, dispPassErrMsg } = dispValidationMsg(
  dispErrorMsg
);

// ログイン処理
const login = async () => {
  await axios
    // CSRF保護
    .get("/sanctum/csrf-cookie")
    .then((res) => {
      axios
        .post("/api/login", {
          // バリデーションをLoginRequest.phpで行っているため、オブジェクト名を合わせる必要がある。
          // name: name.value,
          email: email.value,
          password: password.value,
        })
        .then((res) => {
          if (res.data.status_code == "200") {
            router.push("/");
            // ログイン状態を変更するためVuexより呼び出し
            store.dispatch("loginState");
          }
          getUserMessage.value = "ログインに失敗しました。";
        })
        .catch((err) => {
          // POST時のバリデーションエラー
          const errorMsgs: Errors = err.response.data.errors;
          useValidationMsg(errorMsgs, errors, dispErrorMsg);

          getUserMessage.value = "ログインに失敗しました。";
        });
    })
    .catch((err) => {
      getUserMessage.value = "ログインに失敗しました。";
    });
};
// 学習のため後にfetchを使って実装

// 戻り値がない場合はvoid
const toggleDisplayPass = (): void => {
  displayPass.value = !displayPass.value;
};
</script>

<style></style>

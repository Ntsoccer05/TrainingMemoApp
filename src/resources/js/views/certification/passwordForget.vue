<script setup lang="ts">
import { reactive, ref } from "vue";
import axios from "axios";
import useValidationMsg from "../../composables/certification/useValidationMsg";
import dispValidationMsg from "../../composables/certification/useDispValidationMsg";
import { DispErrorMsg, Errors, Form } from "../../types/certification";
import { setSeo } from "../../utils/setSeo";

setSeo("PasswordForget");

// 送信データ
const form: Form = reactive({
  email: "",
});

// エラーメッセージ用
const errors: Errors = reactive({
  email: [],
});
const dispErrorMsg: DispErrorMsg = reactive({
  email: false,
});

const dispAlertModal = ref<boolean>(false);

//バリデーションエラーメッセージのレイアウト
const { dispEmailErrMsg } = dispValidationMsg(dispErrorMsg);

const sendForgotPasswordEmail = async () => {
  await axios.get("sanctum/csrf-cookie");
  await axios
    .post("/api/password/forget", {
      email: form.email,
    })
    .then((res) => {
      // 成功時
      dispAlertModal.value = true;
    })
    .catch((err) => {
      // POST時のバリデーションエラー
      const errorMsgs: Errors = err.response.data.errors;
      useValidationMsg(errorMsgs, errors, dispErrorMsg);
    });
};
</script>

<template>
  <form @submit.prevent="sendForgotPasswordEmail" class="w-11/12 mx-auto">
    <!-- Email input -->
    <div class="relative mb-6 mt-10" data-te-input-wrapper-init>
      <input
        type="text"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="email"
        v-model="form.email"
        placeholder=""
      />
      <label
        for="email"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >メールアドレス
      </label>
      <p :class="dispEmailErrMsg">{{ errors.email[0] }}</p>
    </div>

    <!--Submit button-->
    <div class="mb-6 md:mb-0 pb-1 pt-1 text-center">
      <button
        class="mb-3 inline-block w-full rounded px-6 pb-2 pt-2.5 text-lg font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
        type="submit"
        data-te-ripple-init
        data-te-ripple-color="light"
        style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
      >
        パスワード変更メールを送る
      </button>
    </div>
  </form>
  <Modal
    v-model="dispAlertModal"
    title="パスワード変更メール送信完了"
    wrapper-class="modal-wrapper"
    class="flex align-center"
  >
    <p>メールを送信しました。確認してください。</p>
  </Modal>
</template>

<style scoped></style>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import useValidationMsg from "../../composables/inquiry/useValidationMsg";
import dispValidationMsg from "../../composables/inquiry/useDispValidationMsg";
import { DispErrorMsg, Errors, FormData } from "../../types/inquiry";

const router = useRouter();

// 送信データ
const form: FormData = reactive({
  name: "",
  email: "",
  content: "",
});

// 送信ボタン表示文字
const btnText = ref<string>("送信する");

const btnStyle = ref<string>("");

// エラーメッセージ格納
const errors: Errors = reactive({
  email: [],
  content: [],
});
// エラーメッセージを表示するか
const dispErrorMsg: DispErrorMsg = reactive({
  email: false,
  content: false,
});

const dispAlertModal = ref<boolean>(false);
const btnEnabled = ref<boolean>(true);
const btnColor = ref<string>(
  "background: linear-gradient(to right, rgb(238 119 36 / 30%), rgb(216 54 58 / 30%), rgb(221 54 117 / 30%), rgb(180 69 147 / 30%))"
);

watch(form, () => {
  if (form.email !== "" && form.content !== "") {
    btnEnabled.value = false;
    btnColor.value =
      "background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)";
    btnStyle.value =
      "hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]";
  } else {
    btnEnabled.value = true;
    btnColor.value =
      "background: linear-gradient(to right, rgb(238 119 36 / 30%), rgb(216 54 58 / 30%), rgb(221 54 117 / 30%), rgb(180 69 147 / 30%)); pointer-events: none";
    btnStyle.value = "";
  }
});

//バリデーションエラーメッセージのレイアウト
const { dispEmailErrMsg, dispContentErrMsg } = dispValidationMsg(dispErrorMsg);

// 送信するボタン押下処理
const sendEmail = async () => {
  btnEnabled.value = true;
  btnColor.value =
    "background: linear-gradient(to right, rgb(238 119 36 / 30%), rgb(216 54 58 / 30%), rgb(221 54 117 / 30%), rgb(180 69 147 / 30%))";
  btnText.value = "送信中です。。。";
  btnStyle.value = "";
  await axios
    .post("/api/inquiry", {
      name: form.name,
      email: form.email,
      content: form.content,
    })
    .then((res) => {
      // 成功時
      dispAlertModal.value = true;
      btnEnabled.value = false;
      btnColor.value =
        "background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)";
      btnText.value = "送信する";
      btnStyle.value =
        "hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]";
      //エラーメッセージを非表示
      //バリデーションメッセージを初期化
      dispErrorMsg.email = false;
      dispErrorMsg.content = false;
      //バリデーションエラーメッセージのレイアウト
      const { dispEmailErrMsg, dispContentErrMsg } = dispValidationMsg(dispErrorMsg);
      // alert("お問い合わせ内容を送信しました。");
      // フォーム内容初期化
      form.name = "";
      form.email = "";
      form.content = "";
    })
    .catch((err) => {
      btnEnabled.value = false;
      btnColor.value =
        "background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)";
      btnText.value = "送信する";
      btnStyle.value =
        "hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]";
      // POST時のバリデーションエラー
      const errorMsgs: Errors = err.response.data.errors;
      useValidationMsg(errorMsgs, errors, dispErrorMsg);
    });
};

// エンターキーを押すと次の要素入力可
function keydown(e) {
  if (e.keyCode === 13) {
    const obj: HTMLTextAreaElement | HTMLInputElement = document.activeElement as
      | HTMLInputElement
      | HTMLTextAreaElement;
    if (obj.parentNode) {
      const parentNode: HTMLDivElement = obj.parentNode as HTMLDivElement;
      if (obj.nextElementSibling && parentNode.nextElementSibling) {
        if (
          parentNode.nextElementSibling.children &&
          parentNode.nextElementSibling.children[0]
        ) {
          if (parentNode.nextElementSibling.children[0].nodeName == "INPUT") {
            const input: HTMLInputElement = parentNode.nextElementSibling
              .children[0] as HTMLInputElement;
            input.focus();
          }
        } else if (
          parentNode.nextElementSibling.nextElementSibling &&
          parentNode.nextElementSibling.nextElementSibling.children &&
          parentNode.nextElementSibling.nextElementSibling.children[0]
        ) {
          if (
            parentNode.nextElementSibling.nextElementSibling.children[0].nodeName ==
            "TEXTAREA"
          ) {
            const textarea: HTMLTextAreaElement = parentNode.nextElementSibling
              .nextElementSibling.children[0] as HTMLTextAreaElement;
            textarea.focus();
            // 1行目指定のため(無いと2行目指定となる)
            e.returnValue = false;
          }
        }
      }
    }
  }
}

window.onkeydown = keydown;
</script>

<template>
  <form id="form" @submit.prevent="sendEmail" class="h-full w-full">
    <!-- Name input -->
    <div class="relative mb-6" data-te-input-wrapper-init>
      <input
        type="text"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="name"
        v-model="form.name"
        placeholder=""
      />
      <label
        for="name"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >お名前
      </label>
    </div>

    <!-- Email input -->
    <div class="relative mb-6" data-te-input-wrapper-init>
      <input
        type="text"
        class="peer block min-h-[auto] w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="email"
        v-model="form.email"
        placeholder=""
        required
      />
      <label
        for="email"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >メールアドレス<span class="text-sm text-red-600">(※必須)</span>
      </label>
      <p :class="dispEmailErrMsg">{{ errors.email[0] }}</p>
    </div>

    <!-- content input -->
    <p class="text-sm text-center italic mt-6 mb-3 md:mb-0">
      追加してほしい機能や相談ごとなど承ります
    </p>
    <div class="relative h-2/3 mb-6" data-te-input-wrapper-init>
      <textarea
        class="peer block h-full w-full rounded border bg-transparent px-3 py-[0.32rem] leading-[2.15] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-600 dark:placeholder:text-neutral-600 dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
        id="content"
        v-model="form.content"
        placeholder=""
        required
      />
      <label
        for="content"
        class="pointer-events-none absolute duration-300 bg-white scale-[0.8] transform -translate-y-[1.15rem] top-2 origin-[0] text-neutral-500 px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/3 peer-focus:top-2 peer-focus:scale-[0.8] peer-focus:-translate-y-[1.15rem] left-1 dark:text-neutral-600 dark:peer-focus:text-primary"
        >お問い合わせ内容<span class="text-sm text-red-600">(※必須)</span>
      </label>
      <p :class="dispContentErrMsg">{{ errors.content[0] }}</p>
    </div>

    <!--Submit button-->
    <div class="mb-6 mt-10 md:mb-0 pb-1 pt-1 text-center">
      <button
        :class="[
          btnStyle,
          'mb-3 inline-block w-full rounded px-6 pb-2 pt-2.5 text-base font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out',
        ]"
        type="submit"
        data-te-ripple-init
        data-te-ripple-color="light"
        :style="btnColor"
        :disabled="btnEnabled"
      >
        {{ btnText }}
      </button>
    </div>
  </form>
  <Modal
    v-model="dispAlertModal"
    title="お問い合わせ送信完了"
    wrapper-class="modal-wrapper"
    class="flex align-center"
  >
    <p>お問い合わせ内容を送信しました。</p>
  </Modal>
</template>

<style scoped></style>

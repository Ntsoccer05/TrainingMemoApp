<template>
  <div class="mt-10">
    <template v-if="isInputMenu">
      <div class="addPart text-center md:grid grid-cols-2">
        <label for="addPart" class="text-bold mt-3 justify-self-end block sm:inline"
          >追加する部位を記入してください：</label
        >
        <div class="addPart md:grid md:grid-cols-3">
          <input
            class="bg-slate-100 border-black border-x border-y mt-3 justify-self-start self-start"
            id="addPart"
            type="text"
            v-model="addCategory"
            required
          />
        </div>
        <p :class="dispCategoryErrMsg">{{ errors.category_content }}</p>
      </div>
    </template>
    <template v-else>
      <div class="addPart text-center md:grid grid-cols-2">
        <label for="addPart" class="bold justify-self-end block sm:inline"
          >追加する部位を選択してください：</label
        >
        <select
          id="addPart"
          v-model="selectedCategory"
          ref="addPart"
          @change="toggleBtnInput"
          class="bg-slate-200 border-black border-x border-y justify-self-start"
          required
        >
          <option v-for="category in categories" :key="category.id" :value="category.id">
            {{ category.content }}
          </option>
          <option ref="addPartBtn" class="block">
            <button>新規追加する</button>
          </option>
        </select>
        <p :class="dispCategoryErrMsg">{{ errors.category_content }}</p>
      </div>
      <div class="md:grid text-center grid-cols-2 mt-10">
        <label for="exercise" class="text-bold justify-self-end block sm:inline"
          >追加する種目を記入してください：</label
        >
        <input
          class="bg-slate-100 border-black border-x border-y justify-self-start"
          id="exercise"
          type="text"
          v-model="addMenu"
          required
        />
        <p :class="dispMenuErrMsg">{{ errors.menu }}</p>
      </div>
      <div class="lg:grid text-center lg:grid-cols-6 xl:grid-cols-9 mt-5">
        <input
          class="bg-slate-100 border-black border-x border-y md:col-start-3 xl:col-start-4 md:mr-0 xl:mr-2 mr-2 justify-self-end"
          id="separate"
          type="checkbox"
          v-model="sepereteRecord"
        />
        <label for="separate" class="text-bold">左右別々で記録する</label>
      </div>
    </template>
    <div class="addPart grid grid-cols-2 mt-10 w-full mb-10">
      <button
        type="submit"
        class="bg-blue-500 text-white w-28 h-8 rounded-md mr-5 mb-1 justify-self-end"
        @click="addMenuContent"
      >
        追加する
      </button>
      <button
        class="bg-red-500 text-white w-28 h-8 rounded-md ml-5 justify-self-start"
        @click="cancelAddMenu"
      >
        前へ戻る
      </button>
    </div>
    <MenuTable :categories="categories" />
  </div>
  <Modal
    v-model="dispAlertModal"
    title="権限エラー"
    wrapper-class="modal-wrapper"
    class="flex align-center"
    @closing="toHome()"
  >
    <p>画面表示するにはログインしてください。</p>
    <button
      class="col-12 mt-5 text-center inline-block w-full rounded px-6 pb-2 pt-2.5 text-base font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
      style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
      @click="toLogin"
    >
      ログイン画面へ
    </button>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, nextTick, computed, ComputedRef } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import useValidationMsg from "../../composables/menu/useValidationMsg";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import dispValidationMsg from "../../composables/menu/useDispValidationMsg";
import MenuTable from "../record/MenuTable.vue";
import { Errors, DispErrorMsg, Post, Category } from "../../types/trainingMenu";
import axios from "axios";
import userSessionStorage from "../../utils/userSessionStorage";
import session from "../../utils/sessionStorageUtil";

const router = useRouter();
const route = useRoute();
const store = useStore();
const recordedAt = ref<string>("");

const editable = ref<boolean>(false);

const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

const recorded_day: string = route.params.recordId as string;

const errors = reactive<Errors>({
  category_content: [],
  menu: [],
});
const dispErrorMsg = reactive<DispErrorMsg>({
  category_content: false,
  menu: false,
});

const toHome = (): void => {
  //router.pushが効かない
  window.location.href = "/";
};
const toLogin = (): void => {
  router.push("/login");
};

const post = reactive<Post>({
  user_id: -1,
  category_content: "",
  category_id: "",
  menu: "",
  oneSide: false,
});

//以下の形でデータが入っている。
const categories = ref<Category>([]);

const selectedCategory = ref<string>("");
const addCategory = ref<string>("");
const addMenu = ref<string>("");
const sepereteRecord = ref<boolean>(false);
const isInputMenu = ref<boolean>(false);

// DOM取得(Returnに追記しないとDOM取得できない)
const addPart = ref<HTMLElement>(null);
const addPartBtn = ref<HTMLElement>(null);

// computedはreturnする必要がある
const recorded_at: ComputedRef<string> = computed(() => store.getters.getRecordedAt);
if (recorded_at) {
  recordedAt.value = recorded_at.value;
}

//バリデーションエラーメッセージのレイアウト
const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
const { getSessionLoginUser } = userSessionStorage();

const toggleBtnInput = () => {
  if (selectedCategory.value == "新規追加する") {
    isInputMenu.value = true;

    //バリデーションメッセージを初期化
    dispErrorMsg.category_content = false;
    dispErrorMsg.menu = false;
    //バリデーションエラーメッセージのレイアウト
    const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
    return { dispCategoryErrMsg, dispMenuErrMsg };
  }
};

// 追加するボタン押下時
const addMenuContent = async () => {
  if (isInputMenu.value === true) {
    post.user_id = loginUser.value.id;
    if (addCategory.value === "") {
      post.category_content = false;
    } else {
      post.category_content = addCategory.value;
    }
  } else {
    post.user_id = loginUser.value.id;
    post.category_content = "";
    post.category_id = selectedCategory.value;
    post.menu = addMenu.value;
    post.oneSide = sepereteRecord.value;
  }
  await axios
    .post("/api/menus/store", post)
    .then((res) => {
      if (res.data.status === 400) {
        // POST時のバリデーションエラー
        const errorMsgs = res.data.errors;
        useValidationMsg(errorMsgs, errors, dispErrorMsg);
        //バリデーションエラーメッセージのレイアウト
        const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
        return { dispCategoryErrMsg, dispMenuErrMsg };
      }
      session.remove("trainingMenus");
      if (selectedCategory.value == "新規追加する") {
        isInputMenu.value = false;
        // この状態だとDOMにセレクトボックスがないためaddPartは取得できないためv-modelの状態を初期化することでセレクトボックスの中身を初期化できる
        selectedCategory.value = "";
        addCategory.value = "";
        getMenus();
        alert("部位を追加しました。");
        //バリデーションメッセージを初期化
        dispErrorMsg.category_content = false;
        dispErrorMsg.menu = false;
        //バリデーションエラーメッセージのレイアウト
        const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
        return { dispCategoryErrMsg, dispMenuErrMsg };
      } else {
        if (addMenu.value !== "") {
          addMenu.value = "";
          getMenus();
          alert("種目を追加しました。");
          //バリデーションメッセージを初期化
          dispErrorMsg.category_content = false;
          dispErrorMsg.menu = false;
          //バリデーションエラーメッセージのレイアウト
          const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
          return { dispCategoryErrMsg, dispMenuErrMsg };
        }
      }
    })
    .catch((err) => {
      // POST時のバリデーションエラー
      const errorMsgs: Errors = err.response.data.errors;
      useValidationMsg(errorMsgs, errors, dispErrorMsg);
    });
};

// キャンセルボタン押下時
const cancelAddMenu = () => {
  if (selectedCategory.value == "新規追加する") {
    isInputMenu.value = false;
    // この状態だとDOMにセレクトボックスがないためaddPartは取得できないためv-modelの状態を初期化することでセレクトボックスの中身を初期化できる
    selectedCategory.value = "";

    //バリデーションメッセージを初期化
    dispErrorMsg.category_content = false;
    dispErrorMsg.menu = false;
    //バリデーションエラーメッセージのレイアウト
    const { dispCategoryErrMsg, dispMenuErrMsg } = dispValidationMsg(dispErrorMsg);
    return { dispCategoryErrMsg, dispMenuErrMsg };
  } else {
    addMenu.value = "";
    if (isInputMenu.value) {
      //前の画面へ戻りたいため
      history.back();
    } else {
      router.push({
        name: "selectMenu",
        params: { recordId: recorded_day },
      });
    }
  }
};

const { getLoginUser, loginUser } = useGetLoginUser();

//メニュー内容を取得
const getMenus = async () => {
  await axios
    .get("/api/menus", {
      // get時にパラメータを渡す際はparamsで指定が必要
      params: {
        user_id: loginUser.value.id,
      },
    })
    .then((res) => {
      categories.value = res.data.categorylist;
    })
    .catch((err) => {});
};

onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  if (dispModal.value) {
    dispAlertModal.value = true;
  }

  getMenus();
  // DOM取得のため
  const addPartDom: HTMLElement = addPart.value;

  getMenus();
  //動的に要素を追加したものに対する処理にはnextTickを用いる
  nextTick(() => {
    if (!sessionLoginUser) {
      getLoginUser();
    }
    const addPartBtnDom: HTMLElement = addPartBtn.value;
    toggleBtnInput();
  });
});
</script>

<style></style>

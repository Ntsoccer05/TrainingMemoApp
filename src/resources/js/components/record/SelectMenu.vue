<template>
  <div>
    <h3 class="text-lg text-center font-bold mt-6">{{ dispHeadText }}</h3>
    <button
      class="block w-50 bg-blue-500 hover:bg-blue-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 ml-auto rounded-full mr-5"
      @click="toAddMenu()"
    >
      部位・種目を追加する
    </button>
    <button
      :class="[
        'w-50 bg-red-500 hover:bg-red-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 ml-auto rounded-full mr-5',
        editable ? 'hidden' : 'block',
      ]"
      @click="editMenu()"
    >
      編集／削除する
    </button>
    <button
      :class="[
        'w-50 bg-red-500 hover:bg-red-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 ml-auto rounded-full mr-5',
        editable ? 'block' : 'hidden',
      ]"
      @click="compEditMenu()"
    >
      編集／削除を終了する
    </button>
    <template v-if="hasRecord">
      <div class="text-right mr-5 md:mr-10">
        <i class="fa-solid fa-minus text-green-400 text-xl"></i>
        <span class="text-lg">：記録済み</span>
      </div>
    </template>
    <div :class="['text-right mr-5 md:mr-10', editable ? 'block' : 'hidden']">
      <div><i class="fa-solid fa-trash"></i><span class="font-bold">：削除</span></div>
      <div>
        <span class="font-bold text-red-600">※部位を削除すると種目も削除されます</span>
      </div>
    </div>
    <div class="mx-auto w-11/12 mt-5 mb-5">
      <label for="weight block sm:inline"> 体重：</label>
      <input
        class="border border-black text-right w-28"
        name="weight"
        type="text"
        placeholder="kg"
        maxlength="6"
        :value="weight"
        @change="
                    weight = validateWeight(
                        ($event.target as HTMLInputElement).value
                    )
                "
        @blur="postWeight"
      />
    </div>

    <!-- :子供のprops名：データ値 -->
    <EditableMenuTable
      :dispHeadText="dispHeadText"
      :editable="editable"
      :records="records"
      :dataMenu="dataMenu"
    />
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
import EditableMenuTable from "./EditableMenuTable.vue";
import { ref, onMounted, watch, computed, ComputedRef, nextTick } from "vue";
import { useStore } from "vuex";
import {
  useRoute,
  useRouter,
  onBeforeRouteLeave,
  NavigationGuardNext,
  RouteLocationNormalized,
} from "vue-router";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetRecordState from "../../composables/record/useGetRecordState";
import useGetRecords from "../../composables/record/useGetRecords";
import axios from "axios";
import userSessionStorage from "../../utils/userSessionStorage";
import session from "../../utils/sessionStorageUtil";

const router = useRouter();
const route = useRoute();
const store = useStore();

store.commit("compGetData", false);

const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);

const dispHeadText = ref<string>("");

const recorded_day: string = route.params.recordId as string;

const weight = ref<string>(null);

let dataCategory = ref([]);
let dataMenu = ref([]);

const editable = ref<boolean>(false);
const hasCategory = ref<boolean>(true);
const hasMenu = ref<boolean>(true);
const hasRecord = ref<boolean>(false);
// DOM取得のため
const deleteFunc = ref(null);
const deleteCategory = ref(null);

const dispAlertModal = ref<boolean>(false);

const recorded_at = ref<string>("");

// レコード画面から戻った際に選択したメニューまでスクロール処理
const fromPath = ref<string>("");
const currentPath = ref<string>("");
const scrollTop = ref<number>(0);

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

const { getLatestRecordState, latestRecord } = useGetRecordState();

const { records, compGetData, getRecords } = useGetRecords();

watch(records, () => {
  records.value.forEach((record) => {
    if (record.category) {
      dataCategory.value.push(record.category[0].category_id);
    }
    if (record.menu) {
      hasRecord.value = true;
      for (const index in record.menu) {
        dataMenu.value.push(record.menu[index].menu_id);
      }
    }
    if (record.recorded_at.recorded_at) {
      let formatRecord = ref<string[]>([]);
      formatRecord.value = record.recorded_at.recorded_at.split("-");
      recorded_at.value = `${formatRecord.value[0]}年${formatRecord.value[1]}月${formatRecord.value[2]}`;
    }
  });
});

const toHome = (): void => {
  //router.pushが効かない
  window.location.href = "/";
};

const toLogin = (): void => {
  router.push("/login");
};

//トレーニングメニュー追加画面に遷移
const toAddMenu = (): void => {
  router.push({ name: "addMenu", params: { recordId: recorded_day } });
};

const editMenu = (): void => {
  editable.value = true;
  dispHeadText.value = "編集する部位・種目を選択してください";
};

const compEditMenu = (): void => {
  editable.value = false;
  dispHeadText.value = "";
  getMenus();
  if (hasCategory.value === false) {
    dispHeadText.value = "部位・種目を追加してください";
  } else if (hasMenu.value === false) {
    dispHeadText.value = "種目を追加してください";
  } else {
    dispHeadText.value = "鍛える部位を選択してください";
  }
};

const handleMenus = (data: any) => {
  if (!editable.value) {
    if (data.categories.length === 0) {
      dispHeadText.value = "部位・種目を追加してください";
      hasCategory.value = false;
      hasMenu.value = false;
    } else if (data.menulist.length === 0) {
      dispHeadText.value = "種目を追加してください";
      hasMenu.value = false;
      hasCategory.value = true;
    } else {
      dispHeadText.value = "鍛える部位を選択してください";
      hasCategory.value = true;
      hasMenu.value = true;
    }
  }
};

//メニュー内容を取得
const getMenus = async () => {
  const cache = session.get<any>("trainingMenus");
  if (cache && !editable.value) {
    // キャッシュあったら使う
    handleMenus(cache);
    return;
  }
  await axios
    .get("/api/menus", {
      // get時にパラメータを渡す際はparamsで指定が必要
      params: {
        user_id: loginUser.value.id,
      },
    })
    .then((res) => {
      //編集画面でなければ
      if (!editable.value) {
        session.set("trainingMenus", res.data);
        if (res.data.categories.length === 0) {
          dispHeadText.value = "部位・種目を追加してください";
          hasCategory.value = false;
          hasMenu.value = false;
        } else if (res.data.menulist.length === 0) {
          dispHeadText.value = "種目を追加してください";
          hasMenu.value = false;
          hasCategory.value = true;
        } else {
          dispHeadText.value = "鍛える部位を選択してください";
          hasCategory.value = true;
          hasMenu.value = true;
        }
        if (res.data.categorylist.length === 0) {
          dispHeadText.value = "部位・種目を追加してください";
          hasCategory.value = false;
          hasMenu.value = false;
        } else if (res.data.menulist2[0].length === 0) {
          dispHeadText.value = "種目を追加してください";
          hasMenu.value = false;
          hasCategory.value = true;
        } else {
          dispHeadText.value = "鍛える部位を選択してください";
          hasCategory.value = true;
          hasMenu.value = true;
        }
      }
    })
    .catch((err) => {});
};

//体重を記録する
const postWeight = async () => {
  await axios
    .post("/api/record/edit", {
      user_id: loginUser.value.id,
      recording_day: latestRecord.value.recorded_at,
      weight: weight.value,
    })
    .then((res) => {})
    .catch((err) => {});
};

//全角→半角
const replaceFullToHalf = (str: string) => {
  return str.replace(/[！-～]/g, function (s) {
    return String.fromCharCode(s.charCodeAt(0) - 0xfee0);
  });
};

// valはString
const validateWeight = (val: string) => {
  val = replaceFullToHalf(val);
  // 小数点を含むか？
  let oldVal = val;
  const decPoint = oldVal.indexOf(".");
  // replaceは型がStringのもののみ適用できる(replaceはそのものの値自体は変えないので代入する必要あり)
  // 数字または小数点以外を無効とする
  val = val.replace(/[^0-9|.]/g, "");
  // parseFloatで整数型へ変換している
  if (val !== "") {
    val = parseFloat(val).toString();
    // toFixedで小数第一位で四捨五入する
    val = parseFloat(Number(val).toFixed(1)).toString();
    // matchは型がStringのもののみ適用できる
    val.toString().match(/^(\d+)(\.\d*)?/u) ? val : "";
  }
  return val;
};

// レコード画面から戻った際に選択したメニューまでスクロール処理
const menuScroll = () => {
  // 前画面パスを設定
  if (window.history.state.forward) {
    fromPath.value = window.history.state.forward.split("/")[1];
  } else if (window.history.state.back) {
    fromPath.value = window.history.state.back.split("/")[1];
  }
  // 現在画面パスを設定
  currentPath.value = window.history.state.current.split("/")[1];
  // スクロール位置を設定
  if (sessionStorage.getItem("OffsetTop")) {
    scrollTop.value = Number(sessionStorage.getItem("OffsetTop"));
    sessionStorage.removeItem("OffsetTop");
  } else {
    scrollTop.value = 0;
  }

  if (
    fromPath.value === "record" &&
    currentPath.value === "selectMenu" &&
    scrollTop.value > 0
  ) {
    nextTick(() => {
      // レコード画面から戻った際に選択したメニューまでスクロール処理
      window.scrollTo({
        top: scrollTop.value,
        left: 0,
        behavior: "smooth",
      });
    });
  }
};

onMounted(async () => {
  // DOM取得のため
  const deleteFuncDom = deleteFunc.value;
  const deleteCategoryDom = deleteCategory.value;

  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  if (dispModal.value) {
    dispAlertModal.value = true;
  }
  if (route.params.recordId) {
    await getRecords(loginUser.value.id, recorded_day).then((res) => {
      store.commit("compGetData", true);
    });
  } else if (loginUser.value.id) {
    await getRecords(loginUser.value.id).then((res) => {
      store.commit("compGetData", true);
    });
  }
  await getLatestRecordState();
  await getMenus();
  if (latestRecord.value.bodyWeight) {
    weight.value = latestRecord.value.bodyWeight.toString();
  }

  // レコード画面から戻った際に選択したメニューまでスクロール処理
  menuScroll();
});

const deleteMenu = async (next: NavigationGuardNext) => {
  await axios
    .post("/api/record/destroy", {
      user_id: loginUser.value.id,
      recorded_at: recorded_day,
    })
    .then((res) => {
      store.commit("compGetData", false);
      next();
    })
    .catch(() => {});
};

const deleteOrMaintainRecordState = async (next: NavigationGuardNext) => {
  if (records.value.length === 0) {
    await deleteMenu(next);
  } else if (records.value.length === 1 && !records.value[0].category) {
    await deleteMenu(next);
  } else {
    store.commit("compGetData", false);
    next();
  }
};

//遷移前処理
onBeforeRouteLeave(
  async (
    to: RouteLocationNormalized,
    from: RouteLocationNormalized,
    next: NavigationGuardNext
  ) => {
    // store.commit("compGetData", false);
    if (to.name === "home") {
      if (compGetData.value === false) {
        await getLoginUser();
        if (route.params.recordId) {
          await getRecords(loginUser.value.id, recorded_day).then(async (res) => {
            await deleteOrMaintainRecordState(next);
          });
        } else if (loginUser.value.id) {
          await getRecords(loginUser.value.id).then(async (res) => {
            await deleteOrMaintainRecordState(next);
          });
        }
      } else {
        store.commit("compGetData", false);
        await deleteOrMaintainRecordState(next);
      }
    } else if (to.name === "record") {
      store.commit("compGetData", false);
      // レコード画面へ遷移時現在のスクロール位置を保存
      sessionStorage.setItem("OffsetTop", String(window.scrollY));
      next();
    } else {
      store.commit("compGetData", false);
      next();
    }
  }
);
</script>

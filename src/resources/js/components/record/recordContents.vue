<template>
  <div>
    <template v-if="compGetData">
      <table class="border border-collapse table-fixed mx-auto">
        <caption
          class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800"
        >
          <button
            class="block w-11/12 bg-blue-500 hover:bg-blue-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 mx-auto"
            ref="fillBeforeBtn"
            @click="fillBeforeRecord"
          >
            {{ BeforeBtnTxt }}
          </button>
          <button
            class="block w-11/12 bg-green-500 hover:bg-green-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 mx-auto"
            @click="confirmHistory()"
          >
            履歴を確認
          </button>
          <p
            :class="[
              'mx-auto text-red-500 text-sm mt-1 mb-2 text-center',
              isDispTxt ? 'block' : 'hidden',
            ]"
          >
            ※前回の記録を埋めるためには今回の記録を埋めてください
          </p>
          <div class="text-center mt-5">
            <input
              class="bg-slate-100 border-black border-x border-y mr-2"
              id="complementContents"
              type="checkbox"
              v-model="complementContents"
            />
            <label for="complementContents" class="text-base align-[1px]"
              >重量・回数を補完する</label
            >
          </div>
          <div class="grid grid-cols-2 w-full">
            <div>
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の体重：{{ bodyWeight }}
              </p>
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の合計セット数：{{ thisTotalSet }}
              </p>
            </div>
            <template v-if="isBeforeData">
              <div>
                <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                  {{ BeforeWeightTxt }}：{{ beforeBodyWeight }}
                </p>
                <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                  {{ BeforeTotalSetTxt }}：{{ beforeTotalSet }}
                </p>
              </div>
            </template>
            <template v-else>
              <div>
                <p>{{ msgNoBeforeData }}</p>
              </div>
            </template>
          </div>
        </caption>

        <RecordTable
          :second_record="secondRecord"
          :hasSecondRecord="hasSecondRecord"
          :hasOneHand="hasOneHand"
          :category_id="category_id"
          :menu_id="menu_id"
          :record_state_id="record_state_id"
          :menu_content="menuContent"
          :complementContents="complementContents"
          :beforeHeaderTxt="BeforeHeaderTxt"
          @beforeTotalSet="fillBeforeTodalSet"
          @totalSet="fillThisTodalSet"
          @canClick="ableToClickBefore"
        />
      </table>
    </template>
    <template v-else>
      <p class="mx-auto mt-10 md:w-6/12 w-11/12 mb-5 font-bold text-center">
        データ取得中です。しばらくお待ちください。
      </p>
    </template>
    <Modal v-model="showModal" :title="menuContent" modal-class="scrollable-modal">
      <div class="scrollable-content">
        <HistoryRecordContents
          :historyMenus="historyMenus"
          :historyRecords="historyRecords"
          :hasHistoryRecord="hasHistoryRecord"
          :hasOneHand="hasOneHand"
        />
      </div>
      <div class="row scrollable-modal-footer">
        <div class="col-sm-12">
          <div class="text-center">
            <button
              class="block w-11/12 bg-blue-500 hover:bg-blue-700 text-white font-bold border-2 border-black mx-auto"
              type="button"
              @click="showModal = false"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Modal>
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
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, ComputedRef } from "vue";
import {
  useRoute,
  useRouter,
  onBeforeRouteLeave,
  NavigationGuardNext,
  RouteLocationNormalized,
} from "vue-router";
import { useStore } from "vuex";
import useGetRecordState from "../../composables/record/useGetRecordState";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetSecondRecordContent from "../../composables/record/useGetSecondRecordContent.js";
import RecordTable from "./RecordTable.vue";
import HistoryRecordContents from "./HistoryRecordContents.vue";
import useGetTgtRecordContent from "../../composables/record/useGetTgtRecordContent.js";
import useGetHistoryRecordContent from "../../composables/record/useGetHistoryRecordContent.js";
import axios from "axios";
import userSessionStorage from "../../utils/userSessionStorage";
import menuContentSessionStorage from "../../utils/menuContentSessionStorage";

const route = useRoute();
const store = useStore();
const router = useRouter();

const category_id: string = route.query.categoryId as string;
const menu_id: string = route.query.menuId as string;
const record_state_id: string = route.query.recordId as string;

const {
  setMenuContentSession,
  getMenuContentSession,
  removeMenuContentSession,
  getFillBeforeRecordSession,
  setFillBeforeRecordSession,
  removeFillBeforeRecordSession,
  getHistoryRecordSession,
  setHistoryRecordSession,
  removeHistoryRecordSession,
} = menuContentSessionStorage(category_id, menu_id, record_state_id);
const fillBeforeRecordSession = getFillBeforeRecordSession();

const hasOneHand = ref<boolean>(false);

const bodyWeight = ref<string>("");
const beforeBodyWeight = ref<string>(fillBeforeRecordSession?.bodyWeight || "");

const thisTotalSet = ref<string>("");
const beforeTotalSet = ref<string>("");

const msgNoBeforeData = ref<string>("");

const fillBeforeBtn = ref<string>("");
const compGetData = ref<boolean>(false);

const showModal = ref<boolean>(false);

// 前回か前々回か
const BeforeBtnTxt = ref<string>("");
const isDispTxt = ref<boolean>(false);
const BeforeWeightTxt = ref<string>("");
const BeforeTotalSetTxt = ref<string>("");
const BeforeHeaderTxt = ref<string>("");

const menuContent = ref<string>("");

const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

// 自動補完するか
const complementContents = ref<boolean>(false);

//前回データが存在するか？
const isBeforeData = ref<boolean>(!!fillBeforeRecordSession);

// 最新のレコード状態を取得
const { getLatestRecordState, latestRecord } = useGetRecordState();

//今回記録するデータの値を取得
const { hasTgtRecord, getTgtRecords } = useGetTgtRecordContent();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

//前回のデータを取得
const {
  secondRecord,
  secondRecordState,
  hasSecondRecord,
  getSecondRecord,
} = useGetSecondRecordContent(fillBeforeRecordSession);

const toHome = (): void => {
  //router.pushが効かない
  window.location.href = "/";
};
const toLogin = (): void => {
  router.push("/login");
};

// 片方ずつ記録するかどうかmenusテーブルのoneSideカラムにて判断
const getMenuContent = async () => {
  const menuContentSession = getMenuContentSession();
  if (menuContentSession) {
    const data = menuContentSession;
    menuContent.value = data.content;
    hasOneHand.value = data.oneSide === 1;
    return;
  }
  await axios
    .get("/api/menus", {
      params: {
        user_id: loginUser.value.id,
        category_id: category_id,
        menu_id: menu_id,
      },
    })
    .then((res) => {
      menuContent.value = res.data.menu.content;
      setMenuContentSession(res.data.menu);
      if (res.data.menu.oneSide === 1) {
        hasOneHand.value = true;
      } else {
        hasOneHand.value = false;
      }
    })
    .catch((err) => {});
};

const fillBeforeRecord = async () => {
  const fillBeforeRecordSession = getFillBeforeRecordSession();

  if (fillBeforeRecordSession) {
    const data = fillBeforeRecordSession;
    hasSecondRecord.value = true;
    isBeforeData.value = true;
    beforeBodyWeight.value = data.bodyWeight || "";
    secondRecord.value = data.record;
    return;
  }

  await getSecondRecord(
    loginUser.value.id,
    category_id,
    menu_id,
    record_state_id,
    route.params.recordId as string,
    thisTotalSet.value
  );
  if (hasSecondRecord.value) {
    isBeforeData.value = true;
    beforeBodyWeight.value = secondRecordState.value.bodyWeight
      ? secondRecordState.value.bodyWeight.toString()
      : "";
    setFillBeforeRecordSession(secondRecordState.value.bodyWeight, secondRecord.value);
  } else {
    msgNoBeforeData.value = "記録がありません";
  }
  if (BeforeBtnTxt.value === "前回の記録を埋める") {
    BeforeWeightTxt.value = "前回の体重";
    BeforeTotalSetTxt.value = "前回の合計セット数";
    BeforeHeaderTxt.value = "前回の記録";
  } else {
    // BeforeWeightTxt.value = "前々回の体重";
    // BeforeTotalSetTxt.value = "前々回の合計セット数";
    // BeforeHeaderTxt.value = "前々回の記録";
  }
};

//第一引数に子供の値が入っている。
const fillThisTodalSet = (e: string): void => {
  thisTotalSet.value = e;
};

const fillBeforeTodalSet = (e: string) => {
  beforeTotalSet.value = e;
};

const ableToClickBefore = (e: boolean) => {
  if (e) {
    BeforeBtnTxt.value = "前回の記録を埋める";
    isDispTxt.value = false;
  } else {
    // BeforeBtnTxt.value = "前々回の記録を埋める";
    // isDispTxt.value = true;
  }
};

const {
  historyRecords,
  historyMenus,
  hasHistoryRecord,
  getHistoryRecords,
} = useGetHistoryRecordContent();

const confirmHistory = async () => {
  const historyRecordSession = getHistoryRecordSession();

  if (historyRecordSession) {
    const data = historyRecordSession;
    historyRecords.value = data.historyRecords;
    historyMenus.value = data.historyMenus;
    hasHistoryRecord.value = data.hasHistoryRecord;
    showModal.value = true;
    return;
  }

  //今回記録するデータの値を取得
  await getHistoryRecords(
    loginUser.value.id,
    category_id,
    menu_id,
    record_state_id,
    route.params.recordId as string
  );
  setHistoryRecordSession(
    historyRecords.value,
    historyMenus.value,
    hasHistoryRecord.value
  );
  showModal.value = true;
};

const deleteFirstRecord = async () => {
  await axios
    .post("/api/recordContent/delete", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      set: 0,
    })
    .then((res) => {})
    .catch((err) => {});
};

const firstRecord = async () => {
  await axios
    .post("/api/recordContent/create", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      set: 0,
    })
    .then((res) => {})
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
  await getLatestRecordState();
  await getMenuContent();
  await getTgtRecords(loginUser.value.id, category_id, menu_id, record_state_id);
  BeforeBtnTxt.value = "前回の記録を埋める";
  BeforeWeightTxt.value = "前回の体重";
  BeforeTotalSetTxt.value = "前回の合計セット数";
  BeforeHeaderTxt.value = "前回の記録";
  isDispTxt.value = false;
  // compGetData.value = true;
  // } else {
  // BeforeBtnTxt.value = "前々回の記録を埋める";
  // BeforeWeightTxt.value = "前々回の体重";
  // BeforeTotalSetTxt.value = "前々回の合計セット数";
  // BeforeHeaderTxt.value = "前々回の記録";
  // isDispTxt.value = true;
  // compGetData.value = true;
  // }
  compGetData.value = true;
  const fillBeforeBtnDom: string = fillBeforeBtn.value;

  if (latestRecord.value.bodyWeight) {
    bodyWeight.value = `${latestRecord.value.bodyWeight} kg`;
  } else {
    // bodyWeight.value = "記録されていません";
  }
});
</script>

// vue-modalのレイアウト作成
<style deep>
.scrollable-modal {
  display: flex;
  flex-direction: column;
  height: calc(100% - 50px);
}
.scrollable-modal .vm-titlebar {
  flex-shrink: 0;
}
.scrollable-modal .vm-content {
  padding: 0;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.scrollable-modal .vm-content .scrollable-content {
  position: relative;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 10px 15px 10px 15px;
  flex-grow: 1;
}
.scrollable-modal .scrollable-modal-footer {
  padding: 15px 0px 15px 0px;
  border-top: 1px solid #e5e5e5;
  margin-left: 0;
  margin-right: 0;
}
.vm-titlebar {
  text-align: center;
}
</style>

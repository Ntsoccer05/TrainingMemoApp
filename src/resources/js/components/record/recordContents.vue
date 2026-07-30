<template>
  <div>
    <template v-if="compGetData">
      <table class="border border-collapse table-fixed mx-auto">
        <caption
          class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800"
        >
          <button
            class="block w-11/12 bg-green-500 hover:bg-green-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 mx-auto"
            @click="confirmHistory()"
          >
            履歴を確認
          </button>
          <div class="text-center mt-5">
            <input
              class="bg-slate-100 border-black border-x border-y mr-2"
              id="complementContents"
              type="checkbox"
              v-model="complementContents"
            />
            <label for="complementContents" class="text-base align-[1px]"
              >前セットと同じ値を自動入力</label
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
          :second_record="previousRecords"
          :hasSecondRecord="hasPreviousRecord"
          :tgtRecord="tgtRecords"
          :hasTgtRecord="hasTgtRecord"
          :hasOneHand="hasOneHand"
          :category_id="category_id"
          :menu_id="menu_id"
          :record_state_id="record_state_id"
          :menu_content="menuContent"
          :complementContents="complementContents"
          :beforeHeaderTxt="BeforeHeaderTxt"
          @beforeTotalSet="fillBeforeTodalSet"
          @totalSet="fillThisTodalSet"
        />
      </table>
    </template>
    <template v-else>
      <LoadingSpinner />
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
import { ref, onMounted, computed, watch, ComputedRef } from "vue";
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
import useGetRecordContent from "../../composables/record/useGetRecordContent";
import RecordTable from "./RecordTable.vue";
import HistoryRecordContents from "./HistoryRecordContents.vue";
import LoadingSpinner from "../common/LoadingSpinner.vue";
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
  getRecordDataSession,
  setRecordDataSession,
  getHistoryRecordSession,
  setHistoryRecordSession,
  removeHistoryRecordSession,
  getComplementContentsSession,
  setComplementContentsSession,
} = menuContentSessionStorage(category_id, menu_id, record_state_id);

const hasOneHand = ref<boolean>(false);

const bodyWeight = ref<string>("");
const beforeBodyWeight = ref<string>("");

const thisTotalSet = ref<string>("");
const beforeTotalSet = ref<string>("");

const msgNoBeforeData = ref<string>("");

const compGetData = ref<boolean>(false);

const showModal = ref<boolean>(false);

const BeforeWeightTxt = "前回の体重";
const BeforeTotalSetTxt = "前回の合計セット数";
const BeforeHeaderTxt = "前回の記録";

const menuContent = ref<string>("");

const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

// 自動補完するか(部位+種目単位でsessionStorageに保存された値を初期値として復元する)
const complementContents = ref<boolean>(getComplementContentsSession());

// チェックボックスの状態が変わるたびに部位+種目単位で保存する
watch(complementContents, (value) => {
  setComplementContentsSession(value);
});

//前回データが存在するか？
const isBeforeData = ref<boolean>(false);

// 最新のレコード状態を取得
const { getLatestRecordState, latestRecord } = useGetRecordState();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

// 今回の記録と前回の記録をまとめて取得
const {
  tgtRecords,
  hasTgtRecord,
  previousRecords,
  previousRecordState,
  hasPreviousRecord,
  getRecordContent,
} = useGetRecordContent();

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

//第一引数に子供の値が入っている。
const fillThisTodalSet = (e: string): void => {
  thisTotalSet.value = e;
};

const fillBeforeTodalSet = (e: string) => {
  beforeTotalSet.value = e;
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

  const recordDataSession = getRecordDataSession();
  if (recordDataSession) {
    tgtRecords.value = recordDataSession.tgtRecords || [];
    hasTgtRecord.value = recordDataSession.hasTgtRecord;
    previousRecords.value = recordDataSession.previousRecords || [];
    previousRecordState.value = recordDataSession.previousRecordState;
    hasPreviousRecord.value = recordDataSession.hasPreviousRecord;
  } else {
    const fetchedRecordContent = await getRecordContent(
      loginUser.value.id,
      category_id,
      menu_id,
      record_state_id,
      route.params.recordId as string
    );
    if (fetchedRecordContent) {
      setRecordDataSession(
        tgtRecords.value,
        hasTgtRecord.value,
        previousRecords.value,
        previousRecordState.value,
        hasPreviousRecord.value
      );
    }
  }
  isBeforeData.value = hasPreviousRecord.value;
  beforeBodyWeight.value = previousRecordState.value?.bodyWeight
    ? previousRecordState.value.bodyWeight.toString()
    : "";
  msgNoBeforeData.value = hasPreviousRecord.value ? "" : "前回の記録がありません";

  compGetData.value = true;

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

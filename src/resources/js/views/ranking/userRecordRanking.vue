<script setup lang="ts">
import { onMounted, computed, ref, ComputedRef } from "vue";
import userRecordRankingTable from "../../components/ranking/userRecordRankingTable.vue";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetRecordRanking from "../../composables/ranking/useGetRecordRanking";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import userSessionStorage from "../../utils/userSessionStorage";
import { setSeo } from "../../utils/setSeo";

setSeo("userRecordRanking");

const router = useRouter();
const store = useStore();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();
const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

// メニュー別ランキングを取得
const {
  rankingContents,
  compGetData,
  categoryContents,
  getRecords,
} = useGetRecordRanking();

//前の画面へ戻る
const toBeforeScreen = (): void => {
  history.back();
};

const toHome = (): void => {
  //router.pushが効かない
  window.location.href = "/";
};

const toLogin = (): void => {
  router.push("/login");
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
  await getRecords(loginUser.value.id);
});
</script>

<template>
  <div>
    <div class="mx-auto md:w-6/12 w-11/12 mb-5 font-bold md:text-left">
      <button
        class="mx-auto mt-10 font-bold md:text-center bg-red-500 text-white w-28 h-8 rounded-md ml-5"
        @click="toBeforeScreen"
      >
        前画面へ戻る
      </button>
    </div>
    <p class="mx-auto mt-5 md:w-6/12 w-11/12 mb-5 font-bold md:text-center">
      メニュー別の最高重量、最高ボリュームを表示しています。
    </p>
    <div class="text-right mx-auto w-11/12 md:w-6/12">
      <i class="fa-solid fa-minus text-red-500 text-xl"></i>
      <span class="text-lg">：部位</span>
    </div>
    <div class="text-right mx-auto w-11/12 md:w-6/12">
      <i class="fa-solid fa-minus text-blue-500 text-xl"></i>
      <span class="text-lg">：種目</span>
    </div>
    <template v-if="compGetData">
      <userRecordRankingTable
        :ranking_contents="rankingContents"
        :category_contents="categoryContents"
      />
    </template>
    <template v-else>
      <p class="mx-auto mt-10 md:w-6/12 w-11/12 mb-5 font-bold md:text-center">
        データ取得中です。しばらくお待ちください。
      </p>
    </template>
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

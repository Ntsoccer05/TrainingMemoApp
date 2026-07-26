<template>
  <div class="h-full mt-8">
    <!-- トレーニング記録画面へ遷移 -->
    <RecordToday :compGetData="compGetData" />
    <div class="bar-kind w-full mt-2 mb-2">
      <div class="category-1 grid grid-cols-4 ml-10 md:ml-96 items-center mb-1">
        <div class="event-bar1 h-0.5 w-5 bg-red-600 col-start-3 ml-auto"></div>
        <p class="text-sm col-start-4">：筋トレ日</p>
      </div>
    </div>
    <!-- カレンダーコンポーネント -->
    <Calendar @compGetData="IsCompGetData" />
    <div class="text-center text-xs text-gray-400 mt-8 mb-4">
      <a href="/guide/free-browser-training-log.html" class="mx-1 hover:underline">ブラウザで無料に記録する</a>・
      <a href="/guide/no-install-training-log.html" class="mx-1 hover:underline">インストール不要という選択</a>・
      <a href="/guide/web-training-memo.html" class="mx-1 hover:underline">Web版の使い方</a>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useStore } from "vuex";
import Calendar from "../components/record/Calendar.vue";
import RecordToday from "../components/record/RecordToday.vue";
import { ref } from "vue";
import { setSeo } from "../utils/setSeo";

setSeo("home");
const store = useStore();
const compGetData = ref<boolean>(false);
// 記録日を初期化
store.commit("setRecordedAt", "");

const IsCompGetData = (e: boolean): void => {
  compGetData.value = e;
};
</script>

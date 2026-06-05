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

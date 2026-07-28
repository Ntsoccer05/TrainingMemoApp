<template>
  <div class="max-w-3xl mx-auto mt-8 px-2">
    <h2 class="text-xl font-bold mb-4">体重管理</h2>

    <LoadingSpinner v-if="isLoading" />
    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="border p-3 rounded">
          <h3 class="font-semibold mb-2">体重を記録</h3>
          <div class="mb-2 flex items-center gap-2">
            <label class="text-sm font-medium whitespace-nowrap">日付</label>
            <input type="date" v-model="selectedDate" class="border p-1" />
          </div>
          <WeightRecordForm
            :key="`${selectedDate}-${tagsVersion}`"
            :recordedAt="selectedDate"
            :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
            :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
            :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
            :weightTags="weightTags"
            @saved="onSaved"
          />
        </div>

        <div>
          <WeightTargetSetting
            :targetWeight="targetWeight"
            :targetWeightDate="targetWeightDate"
            :latestBodyWeight="latestBodyWeight"
            @updated="onTargetWeightUpdated"
          />
        </div>
      </div>

      <div class="mt-6">
        <div class="flex gap-2 mb-4">
          <button
            v-for="option in periodOptions"
            :key="option.months"
            class="px-3 py-1 border rounded text-sm"
            :class="selectedMonths === option.months ? 'bg-teal-600 text-white' : 'bg-white'"
            @click="changePeriod(option.months)"
          >
            {{ option.label }}
          </button>
        </div>

        <WeightChart
          :records="weightRecords"
          :targetWeight="targetWeight"
          @pointClick="openRecordModal"
        />
      </div>

      <WeightTagStats :stats="tagStats" />

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>

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
import { computed, ComputedRef, onMounted, ref, Ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import dayjs from "dayjs";
import useGetWeightDashboard from "../../composables/weight/useGetWeightDashboard";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import userSessionStorage from "../../utils/userSessionStorage";
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
import WeightChart from "../../components/weight/WeightChart.vue";
import WeightTagStats from "../../components/weight/WeightTagStats.vue";
import WeightRecordForm from "../../components/weight/WeightRecordForm.vue";
import WeightRecordModal from "../../components/weight/WeightRecordModal.vue";
import WeightTargetSetting from "../../components/weight/WeightTargetSetting.vue";
import WeightTagEditor from "../../components/weight/WeightTagEditor.vue";
import { WeightRecord } from "../../types/weight";
import { setSeo } from "../../utils/setSeo";

setSeo("weight");

const router = useRouter();
const store = useStore();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();
const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

const toHome = (): void => {
  window.location.href = "/";
};

const toLogin = (): void => {
  router.push("/login");
};

const today = dayjs().format("YYYY-MM-DD");

const selectedDate: Ref<string> = ref(today);
const tagsVersion: Ref<number> = ref(0);

const {
  weightRecords,
  targetWeight,
  targetWeightDate,
  weightTags,
  tagStats,
  selectedDateRecord,
  getWeightDashboard,
} = useGetWeightDashboard();

const periodOptions = [
  { label: "1ヶ月", months: 1 },
  { label: "3ヶ月", months: 3 },
  { label: "6ヶ月", months: 6 },
];
const PERIOD_STORAGE_KEY = "weightManagement.selectedMonths";

const getInitialSelectedMonths = (): number => {
  const stored = localStorage.getItem(PERIOD_STORAGE_KEY);
  const parsed = stored ? Number(stored) : NaN;
  return [1, 3, 6].includes(parsed) ? parsed : 1;
};

const selectedMonths: Ref<number> = ref(getInitialSelectedMonths());
const isLoading: Ref<boolean> = ref(true);

const showModal: Ref<boolean> = ref(false);
const selectedRecord: Ref<WeightRecord | null> = ref(null);

const openRecordModal = (record: WeightRecord): void => {
  selectedRecord.value = record;
  showModal.value = true;
};

const latestBodyWeight: ComputedRef<number | null> = computed(() => {
  if (weightRecords.value.length === 0) {
    return null;
  }
  return weightRecords.value[weightRecords.value.length - 1].bodyWeight;
});

const onTargetWeightUpdated = (value: { targetWeight: number; targetWeightDate: string | null }): void => {
  targetWeight.value = value.targetWeight;
  targetWeightDate.value = value.targetWeightDate;
};

const fetchDashboard = async (): Promise<void> => {
  const from = dayjs().subtract(selectedMonths.value, "month").format("YYYY-MM-DD");
  const to = dayjs().format("YYYY-MM-DD");
  await getWeightDashboard(from, to, selectedDate.value);
};

const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  localStorage.setItem(PERIOD_STORAGE_KEY, String(months));
  await fetchDashboard();
};

const onSaved = async (): Promise<void> => {
  await fetchDashboard();
};

const onTagsChanged = async (): Promise<void> => {
  await fetchDashboard();
  tagsVersion.value++;
};

watch(selectedDate, async () => {
  await fetchDashboard();
});

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
  try {
    await fetchDashboard();
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="max-w-3xl mx-auto mt-8 px-2">
    <h2 class="text-xl font-bold mb-4">体重管理</h2>

    <LoadingSpinner v-if="isLoading" />
    <template v-else>
      <div class="flex gap-2 mb-4">
        <button
          v-for="option in periodOptions"
          :key="option.months"
          class="px-3 py-1 border rounded text-sm"
          :class="selectedMonths === option.months ? 'bg-blue-500 text-white' : 'bg-white'"
          @click="changePeriod(option.months)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">日付</label>
        <input type="date" v-model="selectedDate" class="border p-1" />
      </div>

      <WeightTargetSetting
        :targetWeight="targetWeight"
        :latestBodyWeight="latestBodyWeight"
        @updated="onTargetWeightUpdated"
      />

      <WeightChart
        :records="weightRecords"
        :targetWeight="targetWeight"
        @pointClick="openRecordModal"
      />

      <WeightTagStats :stats="tagStats" />

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <div class="mt-6">
        <h3 class="font-semibold mb-2">{{ formTitle }}</h3>
        <WeightRecordForm
          :key="`${selectedDate}-${tagsVersion}`"
          :recordedAt="selectedDate"
          :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
          :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
          :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
          @saved="onSaved"
        />
      </div>

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, onMounted, ref, Ref, watch } from "vue";
import dayjs from "dayjs";
import useGetWeightHistory from "../../composables/weight/useGetWeightHistory";
import axios from "axios";
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
import WeightChart from "../../components/weight/WeightChart.vue";
import WeightTagStats from "../../components/weight/WeightTagStats.vue";
import WeightRecordForm from "../../components/weight/WeightRecordForm.vue";
import WeightRecordModal from "../../components/weight/WeightRecordModal.vue";
import WeightTargetSetting from "../../components/weight/WeightTargetSetting.vue";
import WeightTagEditor from "../../components/weight/WeightTagEditor.vue";
import { WeightRecord, TagStatistic } from "../../types/weight";
import { setSeo } from "../../utils/setSeo";
import useGetWeightTags from "../../composables/weight/useGetWeightTags";

setSeo("weight");

const today = dayjs().format("YYYY-MM-DD");

const selectedDate: Ref<string> = ref(today);
const selectedDateRecord: Ref<WeightRecord | null> = ref(null);

const { weightTags, getWeightTags } = useGetWeightTags();
const tagsVersion: Ref<number> = ref(0);

const onTagsChanged = async (): Promise<void> => {
  await getWeightTags();
  tagsVersion.value++;
};

const formTitle: ComputedRef<string> = computed(() => {
  return selectedDate.value === today ? "今日の体重を記録" : `${selectedDate.value}の体重を記録`;
});

const fetchSelectedDateRecord = async (): Promise<void> => {
  await axios
    .get("/api/weight", { params: { from: selectedDate.value, to: selectedDate.value } })
    .then((res) => {
      selectedDateRecord.value = res.data.records.length > 0 ? res.data.records[0] : null;
    })
    .catch(() => {
      selectedDateRecord.value = null;
    });
};

watch(selectedDate, async () => {
  await fetchSelectedDateRecord();
});

const periodOptions = [
  { label: "1ヶ月", months: 1 },
  { label: "3ヶ月", months: 3 },
  { label: "6ヶ月", months: 6 },
];
const selectedMonths: Ref<number> = ref(1);
const isLoading: Ref<boolean> = ref(true);

const { weightRecords, targetWeight, getWeightHistory } = useGetWeightHistory();
const tagStats: Ref<TagStatistic[]> = ref([]);

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

const onTargetWeightUpdated = (value: number): void => {
  targetWeight.value = value;
};

const fetchHistory = async (): Promise<void> => {
  const from = dayjs().subtract(selectedMonths.value, "month").format("YYYY-MM-DD");
  const to = dayjs().format("YYYY-MM-DD");
  await getWeightHistory(from, to);
};

const fetchTagStats = async (): Promise<void> => {
  await axios
    .get("/api/weight/tagStats")
    .then((res) => {
      tagStats.value = res.data.stats;
    })
    .catch(() => {
      tagStats.value = [];
    });
};

const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  await fetchHistory();
};

const onSaved = async (): Promise<void> => {
  await fetchHistory();
  await fetchTagStats();
  await fetchSelectedDateRecord();
};

onMounted(async () => {
  try {
    await fetchHistory();
    await fetchTagStats();
    await fetchSelectedDateRecord();
    await getWeightTags();
  } finally {
    isLoading.value = false;
  }
});
</script>

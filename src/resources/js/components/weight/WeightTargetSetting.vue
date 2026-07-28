<template>
  <div class="border p-3 rounded mb-4">
    <div class="flex gap-2 items-center flex-wrap mb-2">
      <label class="text-sm font-medium whitespace-nowrap">目標体重(kg)</label>
      <input
        type="text"
        class="border p-1 w-24"
        v-model="targetWeightInput"
        placeholder="例: 60"
      />
      <span
        v-if="isAchieved"
        class="ml-2 text-sm font-bold text-orange-600 border border-orange-400 rounded px-2 py-0.5"
      >
        目標達成！
      </span>
    </div>
    <div class="flex gap-2 items-center flex-wrap mb-2">
      <label class="text-sm font-medium whitespace-nowrap">期限(任意)</label>
      <input type="date" class="border p-1" v-model="targetWeightDateInput" />
    </div>
    <button
      type="button"
      class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded"
      @click="save"
    >
      設定する
    </button>
    <p v-if="diffText" class="text-sm text-gray-600 mt-2">{{ diffText }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, ref, Ref, watch } from "vue";
import axios from "axios";
import dayjs from "dayjs";

const props = defineProps<{
  targetWeight: number | null;
  targetWeightDate: string | null;
  latestBodyWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "updated", value: { targetWeight: number; targetWeightDate: string | null }): void;
}>();

const targetWeightInput: Ref<string> = ref(props.targetWeight?.toString() ?? "");
const targetWeightDateInput: Ref<string> = ref(props.targetWeightDate ?? "");

watch(
  () => props.targetWeight,
  (value) => {
    targetWeightInput.value = value?.toString() ?? "";
  }
);

watch(
  () => props.targetWeightDate,
  (value) => {
    targetWeightDateInput.value = value ?? "";
  }
);

const isAchieved: ComputedRef<boolean> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return false;
  }
  return Math.abs(props.latestBodyWeight - props.targetWeight) <= 0.5;
});

const diffText: ComputedRef<string> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return "";
  }

  const diff = Math.round((props.targetWeight - props.latestBodyWeight) * 10) / 10;
  const diffLabel = diff > 0 ? `+${diff}` : `${diff}`;
  let text = `現在: ${props.latestBodyWeight}kg／目標まで: ${diffLabel}kg`;

  if (props.targetWeightDate) {
    const daysLeft = dayjs(props.targetWeightDate)
      .startOf("day")
      .diff(dayjs().startOf("day"), "day");
    if (daysLeft >= 0) {
      text += `(残り${daysLeft}日)`;
    }
  }

  return text;
});

const save = async (): Promise<void> => {
  if (targetWeightInput.value === "") {
    return;
  }
  const value = parseFloat(targetWeightInput.value);
  const dateValue = targetWeightDateInput.value === "" ? null : targetWeightDateInput.value;
  await axios
    .post("/api/weight/targetWeight", { target_weight: value, target_weight_date: dateValue })
    .then(() => {
      emits("updated", { targetWeight: value, targetWeightDate: dateValue });
    })
    .catch(() => {});
};
</script>

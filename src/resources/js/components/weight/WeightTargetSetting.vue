<template>
  <div class="border p-3 rounded mb-4">
    <label class="block text-sm font-medium mb-1">目標体重(kg)</label>
    <div class="flex gap-2 items-center">
      <input type="text" class="border p-1 w-24" v-model="targetWeightInput" />
      <button
        type="button"
        class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded"
        @click="save"
      >
        設定する
      </button>
      <span
        v-if="isAchieved"
        class="ml-2 text-sm font-bold text-orange-600 border border-orange-400 rounded px-2 py-0.5"
      >
        目標達成！
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, ref, Ref, watch } from "vue";
import axios from "axios";

const props = defineProps<{
  targetWeight: number | null;
  latestBodyWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "updated", value: number): void;
}>();

const targetWeightInput: Ref<string> = ref(props.targetWeight?.toString() ?? "");

watch(
  () => props.targetWeight,
  (value) => {
    targetWeightInput.value = value?.toString() ?? "";
  }
);

const isAchieved: ComputedRef<boolean> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return false;
  }
  return Math.abs(props.latestBodyWeight - props.targetWeight) <= 0.5;
});

const save = async (): Promise<void> => {
  if (targetWeightInput.value === "") {
    return;
  }
  const value = parseFloat(targetWeightInput.value);
  await axios
    .post("/api/weight/targetWeight", { target_weight: value })
    .then(() => {
      emits("updated", value);
    })
    .catch(() => {});
};
</script>

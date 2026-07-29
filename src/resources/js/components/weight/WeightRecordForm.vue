<template>
  <div>
    <div class="mb-2 flex items-center gap-2">
      <label class="text-sm font-medium whitespace-nowrap">体重(kg)</label>
      <input
        type="text"
        class="border p-1 w-32"
        placeholder="例: 65.5"
        v-model="bodyWeightInput"
      />
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">タグ</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tag in props.weightTags"
          :key="tag.id"
          type="button"
          class="px-2 py-1 rounded text-sm border"
          :class="
            selectedTagIds.includes(tag.id)
              ? 'bg-teal-600 text-white border-teal-600'
              : 'bg-gray-100 text-gray-700 border-gray-300'
          "
          :aria-pressed="selectedTagIds.includes(tag.id)"
          @click="toggleTag(tag.id)"
        >
          {{ tag.content }}
        </button>
      </div>
    </div>
    <div class="mb-2">
      <button
        type="button"
        class="text-sm font-medium text-gray-700 flex items-center gap-1"
        @click="toggleMemo"
      >
        メモ{{ memoInput ? "(入力あり)" : "" }} {{ isMemoExpanded ? "▲" : "▼" }}
      </button>
      <textarea
        v-if="isMemoExpanded"
        class="border w-full p-1 mt-1"
        rows="3"
        v-model="memoInput"
      ></textarea>
    </div>
    <button
      type="button"
      class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-4 rounded"
      :disabled="isSaving"
      @click="submit"
    >
      保存する
    </button>
    <p v-if="hasError" class="text-sm text-red-600 mt-2">
      保存に失敗しました。時間をおいて再度お試しください。
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import usePostWeightRecord from "../../composables/weight/usePostWeightRecord";
import { WeightTag } from "../../types/weight";

const props = defineProps<{
  recordedAt: string;
  initialBodyWeight?: number | null;
  initialMemo?: string | null;
  initialTagIds?: number[];
  weightTags: WeightTag[];
}>();

const emits = defineEmits<{
  (e: "saved"): void;
}>();

const { isSaving, hasError, postWeightRecord } = usePostWeightRecord();

const bodyWeightInput: Ref<string> = ref(
  props.initialBodyWeight != null ? props.initialBodyWeight.toString() : ""
);
const memoInput: Ref<string> = ref(props.initialMemo ?? "");
const selectedTagIds: Ref<number[]> = ref(props.initialTagIds ? [...props.initialTagIds] : []);

const MEMO_EXPANDED_STORAGE_KEY = "weightRecordForm.memoExpanded";

const getInitialMemoExpanded = (): boolean => {
  const stored = localStorage.getItem(MEMO_EXPANDED_STORAGE_KEY);
  if (stored !== null) {
    return stored === "true";
  }
  return window.innerWidth >= 768;
};

const isMemoExpanded: Ref<boolean> = ref(getInitialMemoExpanded());

const toggleMemo = (): void => {
  isMemoExpanded.value = !isMemoExpanded.value;
  localStorage.setItem(MEMO_EXPANDED_STORAGE_KEY, String(isMemoExpanded.value));
};

const toggleTag = (tagId: number): void => {
  const index = selectedTagIds.value.indexOf(tagId);
  if (index === -1) {
    selectedTagIds.value.push(tagId);
  } else {
    selectedTagIds.value.splice(index, 1);
  }
};

const submit = async () => {
  const bodyWeight = bodyWeightInput.value !== "" ? parseFloat(bodyWeightInput.value) : null;
  await postWeightRecord(props.recordedAt, bodyWeight, memoInput.value || null, selectedTagIds.value);
  if (!hasError.value) {
    emits("saved");
  }
};
</script>

<template>
  <div class="border p-3 rounded">
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">体重(kg)</label>
      <input
        type="text"
        class="border w-full p-1"
        placeholder="例: 65.5"
        v-model="bodyWeightInput"
      />
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">タグ</label>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="tag in weightTags"
          :key="tag.id"
          class="flex items-center gap-1 text-sm border rounded px-2 py-1 cursor-pointer"
        >
          <input type="checkbox" :value="tag.id" v-model="selectedTagIds" />
          {{ tag.content }}
        </label>
      </div>
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">メモ</label>
      <textarea class="border w-full p-1" rows="3" v-model="memoInput"></textarea>
    </div>
    <button
      type="button"
      class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-4 rounded"
      :disabled="isSaving"
      @click="submit"
    >
      保存する
    </button>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, Ref } from "vue";
import useGetWeightTags from "../../composables/weight/useGetWeightTags";
import usePostWeightRecord from "../../composables/weight/usePostWeightRecord";

const props = defineProps<{
  recordedAt: string;
  initialBodyWeight?: number | null;
  initialMemo?: string | null;
  initialTagIds?: number[];
}>();

const emits = defineEmits<{
  (e: "saved"): void;
}>();

const { weightTags, getWeightTags } = useGetWeightTags();
const { isSaving, postWeightRecord } = usePostWeightRecord();

const bodyWeightInput: Ref<string> = ref(
  props.initialBodyWeight != null ? props.initialBodyWeight.toString() : ""
);
const memoInput: Ref<string> = ref(props.initialMemo ?? "");
const selectedTagIds: Ref<number[]> = ref(props.initialTagIds ? [...props.initialTagIds] : []);

const submit = async () => {
  const bodyWeight = bodyWeightInput.value !== "" ? parseFloat(bodyWeightInput.value) : null;
  await postWeightRecord(props.recordedAt, bodyWeight, memoInput.value || null, selectedTagIds.value);
  emits("saved");
};

onMounted(async () => {
  await getWeightTags();
});
</script>

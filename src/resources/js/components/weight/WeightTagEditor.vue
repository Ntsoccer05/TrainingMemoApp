<template>
  <div class="border p-3 rounded mb-4">
    <h3 class="font-semibold mb-2">タグを編集</h3>
    <div class="flex flex-wrap gap-2 mb-2">
      <span
        v-for="tag in tags"
        :key="tag.id"
        class="flex items-center gap-1 px-2 py-1 bg-gray-200 rounded text-sm"
      >
        {{ tag.content }}
        <button type="button" class="text-red-500" @click="remove(tag.id)">×</button>
      </span>
    </div>
    <div v-if="tags.length < 5" class="flex gap-2">
      <input
        type="text"
        class="border p-1 flex-1"
        v-model="newTagContent"
        placeholder="新しいタグ名"
      />
      <button
        type="button"
        class="bg-blue-500 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded"
        @click="add"
      >
        追加する
      </button>
    </div>
    <p v-else class="text-sm text-gray-500">タグは5個までです。</p>
    <p v-if="errorMessage" class="text-sm text-red-500 mt-1">{{ errorMessage }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import { WeightTag } from "../../types/weight";
import usePostWeightTag from "../../composables/weight/usePostWeightTag";
import useDeleteWeightTag from "../../composables/weight/useDeleteWeightTag";

const props = defineProps<{
  tags: WeightTag[];
}>();

const emits = defineEmits<{
  (e: "changed"): void;
}>();

const newTagContent: Ref<string> = ref("");
const errorMessage: Ref<string> = ref("");

const { postWeightTag } = usePostWeightTag();
const { deleteWeightTag } = useDeleteWeightTag();

const add = async (): Promise<void> => {
  if (newTagContent.value === "") {
    return;
  }
  errorMessage.value = "";
  const result = await postWeightTag(newTagContent.value);
  if (result === null) {
    errorMessage.value = "タグは5個までです。";
    return;
  }
  newTagContent.value = "";
  emits("changed");
};

const remove = async (tagId: number): Promise<void> => {
  await deleteWeightTag(tagId);
  emits("changed");
};
</script>

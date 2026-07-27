<template>
  <Modal
    v-model="showModal"
    :title="record ? `${record.recorded_at} の記録` : ''"
    wrapper-class="modal-wrapper"
  >
    <template v-if="record">
      <p class="mb-2">体重: {{ record.bodyWeight }}kg</p>
      <div class="flex flex-wrap gap-1 mb-2">
        <span
          v-for="tag in record.weight_tags"
          :key="tag.id"
          class="px-2 py-0.5 bg-gray-200 rounded text-sm"
        >
          {{ tag.content }}
        </span>
      </div>
      <p class="whitespace-pre-wrap">{{ record.weight_memo || "メモはありません" }}</p>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { computed, WritableComputedRef } from "vue";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  modelValue: boolean;
  record: WeightRecord | null;
}>();

const emits = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
}>();

const showModal: WritableComputedRef<boolean> = computed({
  get: () => props.modelValue,
  set: (value) => emits("update:modelValue", value),
});
</script>

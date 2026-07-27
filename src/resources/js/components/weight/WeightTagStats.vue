<template>
  <div v-if="stats.length > 0" class="mt-4">
    <h3 class="font-semibold mb-2">タグ別の翌日体重変動</h3>
    <ul>
      <li v-for="stat in stats" :key="stat.tag" class="text-sm mb-1">
        {{ stat.tag }}の翌日は平均{{ stat.average_diff > 0 ? "+" : "" }}{{ stat.average_diff }}kg
        <span class="text-gray-500">({{ stat.sample_count }}件)</span>
      </li>
    </ul>
  </div>
  <p v-else class="mt-4 text-sm text-gray-500">
    タグ別の集計を表示するには、タグ付きの記録が必要です。
  </p>
</template>

<script setup lang="ts">
import { TagStatistic } from "../../types/weight";

defineProps<{
  stats: TagStatistic[];
}>();
</script>

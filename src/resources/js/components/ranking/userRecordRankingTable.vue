<script setup lang="ts">
import { ComputedRef, Ref, computed, ref } from "vue";
import { CategorySummary, dispRecordContents } from "../../types/recordRanking";
import {
  groupContentsByCategory,
  parseStoredClosedCategoryIds,
  toggleAllClosedCategoryIds,
  toggleCategoryInClosedIds,
} from "../../utils/recordRanking";

const props = defineProps<{
  ranking_contents: dispRecordContents;
  category_contents: CategorySummary[];
}>();

const dispContents: ComputedRef<dispRecordContents> = computed(() => props.ranking_contents);
const categoryContents: ComputedRef<CategorySummary[]> = computed(
  () => props.category_contents
);

const contentsByCategory: ComputedRef<Map<number, dispRecordContents>> = computed(() =>
  groupContentsByCategory(dispContents.value)
);

const CLOSED_CATEGORIES_STORAGE_KEY = "recordRanking.closedCategoryIds";

const closedCategoryIds: Ref<number[]> = ref(
  parseStoredClosedCategoryIds(localStorage.getItem(CLOSED_CATEGORIES_STORAGE_KEY))
);

const persistClosedCategoryIds = (): void => {
  localStorage.setItem(
    CLOSED_CATEGORIES_STORAGE_KEY,
    JSON.stringify(closedCategoryIds.value)
  );
};

const isCategoryOpen = (categoryId: number): boolean =>
  !closedCategoryIds.value.includes(categoryId);

const toggleCategory = (categoryId: number): void => {
  closedCategoryIds.value = toggleCategoryInClosedIds(closedCategoryIds.value, categoryId);
  persistClosedCategoryIds();
};

const allOpen: ComputedRef<boolean> = computed(() => closedCategoryIds.value.length === 0);

const toggleAll = (): void => {
  closedCategoryIds.value = toggleAllClosedCategoryIds(
    closedCategoryIds.value,
    categoryContents.value.map((category) => category.id)
  );
  persistClosedCategoryIds();
};
</script>

<template>
  <div class="max-w-3xl mx-auto w-11/12">
    <div class="flex justify-end mb-2">
      <button type="button" class="text-sm text-teal-700 underline" @click="toggleAll">
        {{ allOpen ? "すべて閉じる" : "すべて開く" }}
      </button>
    </div>

    <div v-for="category in categoryContents" :key="category.id" class="mb-3">
      <button
        type="button"
        class="w-full flex items-center justify-between rounded-md bg-teal-600 px-3 py-2 text-white font-semibold"
        @click="toggleCategory(category.id)"
      >
        <span>{{ category.content }}</span>
        <svg
          :class="{ 'rotate-180': isCategoryOpen(category.id) }"
          class="w-4 h-4 transition-transform"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div
        v-if="isCategoryOpen(category.id)"
        class="grid grid-cols-2 gap-2 p-3 border border-t-0 rounded-b-md"
      >
        <template v-for="item in contentsByCategory.get(category.id)" :key="item.menu.id">
          <div
            v-if="item.emptyData === 1"
            class="col-span-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3"
          >
            <div class="text-base font-semibold text-gray-400">{{ item.menu.content }}</div>
            <div class="text-sm text-gray-400 mt-1">記録なし</div>
          </div>
          <div v-else class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-base font-semibold mb-2">{{ item.menu.content }}</div>
            <template v-if="item.menu.oneSide === 1">
              <div class="text-sm text-gray-800">
                <template v-if="item.menuBestRightWeight !== null && item.menuBestRightWeight !== undefined">右 {{ item.menuBestRightWeight }}kg</template>
                <template v-else>右記録なし</template>
                /
                <template v-if="item.menuBestLeftWeight !== null && item.menuBestLeftWeight !== undefined">左 {{ item.menuBestLeftWeight }}kg</template>
                <template v-else>左記録なし</template>
              </div>
              <div class="text-xs text-gray-500 mt-1">
                <template v-if="item.menuBestRightVolume">
                  ボリューム 右{{ item.menuBestRightVolume.right_volume }}({{
                    item.menuBestRightVolume.right_weight
                  }}kg×{{ item.menuBestRightVolume.right_rep }}回)
                </template>
                <template v-else>ボリューム 右記録なし</template>
              </div>
              <div class="text-xs text-gray-500">
                <template v-if="item.menuBestLeftVolume">
                  左{{ item.menuBestLeftVolume.left_volume }}({{
                    item.menuBestLeftVolume.left_weight
                  }}kg×{{ item.menuBestLeftVolume.left_rep }}回)
                </template>
                <template v-else>左記録なし</template>
              </div>
            </template>
            <template v-else>
              <div class="text-sm text-gray-800">重量 {{ item.bestWeight }}kg</div>
              <div class="text-xs text-gray-500 mt-1">
                <template v-if="item.menuBestVolume">
                  ボリューム {{ item.menuBestVolume.volume }}({{ item.menuBestVolume.weight }}kg×{{
                    item.menuBestVolume.rep
                  }}回)
                </template>
                <template v-else>ボリューム記録なし</template>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>
    <div class="h-12"></div>
  </div>
</template>

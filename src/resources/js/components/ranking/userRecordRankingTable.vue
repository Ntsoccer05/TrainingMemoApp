<script setup lang="ts">
import { ComputedRef, computed } from "vue";
import { dispRecordContents } from "../../types/recordRanking";

const props = defineProps<{
  ranking_contents: dispRecordContents;
  category_contents: Array<string>;
}>();
const dispContents: ComputedRef<dispRecordContents> = computed(
  () => props.ranking_contents
);
const categoryContents: ComputedRef<string[]> = computed(() => props.category_contents);
</script>

<template>
  <div>
    <div
      v-for="(categoryContent, index) in categoryContents"
      :key="index"
      class="mx-auto mt-3 md:w-6/12 w-11/12 text-center"
    >
      <p class="text-lg font-bold bg-red-400">{{ categoryContent }}</p>
      <div v-for="(dispContent, index) in dispContents" :key="index">
        <template v-if="categoryContent === dispContent.category.content">
          <table class="border border-collapse table-fixed mx-auto w-full mt-3">
            <thead class="block border">
              <tr class="mx-auto w-full grid">
                <th class="text-center bg-blue-200">
                  {{ dispContent.menu.content }}
                </th>
              </tr>
            </thead>
            <tbody class="block">
              <template
                v-if="dispContent.menu.oneSide == 1 && dispContent.emptyData === 0"
              >
                <tr class="relative grid grid-cols-5 border-b-2">
                  <td class="border-r-2">重量</td>
                  <td class="border-r-2 col-span-2">
                    右：{{ dispContent.menuBestRightWeight }}kg
                  </td>
                  <td class="col-span-2">左：{{ dispContent.menuBestLeftWeight }}kg</td>
                </tr>
                <tr class="relative grid grid-cols-5">
                  <td class="border-r-2">ボリューム</td>
                  <td class="border-r-2 col-span-2">
                    右：{{ dispContent.menuBestRightVolume.right_volume }} ({{
                      dispContent.menuBestRightVolume.right_weight
                    }}kg × {{ dispContent.menuBestRightVolume.right_rep }}回)
                  </td>
                  <td class="col-span-2">
                    左：{{ dispContent.menuBestLeftVolume.left_volume }} ({{
                      dispContent.menuBestLeftVolume.left_weight
                    }}kg × {{ dispContent.menuBestLeftVolume.left_rep }}回)
                  </td>
                </tr>
              </template>
              <template
                v-else-if="dispContent.menu.oneSide == 0 && dispContent.emptyData === 0"
              >
                <tr class="relative grid grid-cols-3 border-b-2">
                  <td class="border-r-2">重量</td>
                  <td class="col-span-2">{{ dispContent.bestWeight }}kg</td>
                </tr>
                <tr class="relative grid grid-cols-3">
                  <td class="border-r-2">ボリューム</td>
                  <td class="col-span-2">
                    {{ dispContent.menuBestVolume.volume }} ({{
                      dispContent.menuBestVolume.weight
                    }}kg × {{ dispContent.menuBestVolume.rep }}回)
                  </td>
                </tr>
              </template>
              <template v-else>
                <tr class="relative grid grid-cols-3 border-b-2">
                  <td class="border-r-2">重量</td>
                  <td class="col-span-2">記録なし</td>
                </tr>
                <tr class="relative grid grid-cols-3">
                  <td class="border-r-2">ボリューム</td>
                  <td class="col-span-2">記録なし</td>
                </tr>
              </template>
            </tbody>
          </table>
        </template>
      </div>
    </div>
    <div class="h-12"></div>
  </div>
</template>

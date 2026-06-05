<template>
  <div>
    <div class="text-center">最大過去５件を表示しています。</div>
    <template v-for="(historyMenu, index) in historyMenus" :key="historyMenu.id">
      <table class="border border-collapse table-fixed mx-auto mt-5">
        <thead>
          <tr>
            <th class="text-center bg-green-400 indent-1 md:indent-0">
              <div class="border" ref="todayRecordedAt">
                <span class="border-r-2 border-black text-sm xs:text-base">
                  {{ historyMenu.recorded_at }}の記録
                </span>
                <span class="ml-1 text-sm xs:text-base"
                  >体重:
                  <template v-if="historyMenu.record_state.bodyWeight">
                    {{ historyMenu.record_state.bodyWeight }}kg
                  </template>
                </span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="historyRecord in historyRecords[index]" :key="historyRecord.id">
            <td>
              <div class="bg-gray-200 border indent-1">
                {{ historyRecord.set }}セット目
              </div>
              <div :class="hasOneHand ? 'hidden' : 'block'">
                <!-- readonlyだとfocusできるが、disabledだとfocusもできない -->
                <!-- inputのvalue値にデータを入力するにはv-bindを用いる -->
                <input
                  class="border w-full"
                  type="text"
                  placeholder="重さ(kg)"
                  :value="historyRecord.weight"
                  disabled
                />
                <input
                  class="border w-full"
                  type="text"
                  placeholder="回数"
                  :value="historyRecord.rep"
                  disabled
                />
              </div>
              <div :class="hasOneHand ? 'block' : 'hidden'">
                <input
                  class="border w-full"
                  type="text"
                  placeholder="重さ（右）(kg)"
                  :value="historyRecord.right_weight"
                  disabled
                />
                <input
                  class="border w-full"
                  type="text"
                  placeholder="回数（右）"
                  :value="historyRecord.right_rep"
                  disabled
                />
                <input
                  class="border w-full"
                  type="text"
                  placeholder="重さ（左）(kg)"
                  :value="historyRecord.left_weight"
                  disabled
                />
                <input
                  class="border w-full"
                  type="text"
                  placeholder="回数（左）"
                  :value="historyRecord.left_rep"
                  disabled
                />
              </div>
              <div class="border">
                <textarea
                  class="w-full leading-4 pl-0.5"
                  name=""
                  id=""
                  cols="20"
                  rows="4"
                  placeholder="メモ"
                  :value="historyRecord.memo"
                  disabled
                  ref="historyMemo"
                ></textarea>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, onMounted, ref } from "vue";
import { HistoryMenu, HistoryRecord } from "../../types/record";
type props = {
  historyMenus: HistoryMenu[];
  historyRecords: HistoryRecord[][];
  hasHistoryRecord: boolean;
  hasOneHand: boolean;
};
const props = defineProps<props>();

const historyMemo = ref<HTMLInputElement[] | null>(null);

const historyMenus: ComputedRef<HistoryMenu[]> = computed(() => props.historyMenus);
const historyRecords: ComputedRef<HistoryRecord[][]> = computed(
  () => props.historyRecords
);
const hasHistoryRecord: ComputedRef<boolean> = computed(() => props.hasHistoryRecord);
const hasOneHand: ComputedRef<boolean> = computed(() => props.hasOneHand);

// 高さを調整する関数
const adjustHeight = (element: HTMLInputElement) => {
  // 内容を改行文字で分割して行数をカウント
  const textLines = element.value.split("\n").length;
  const lineHeight = 1;

  const rows: number = Number(element.getAttribute("rows"));

  const newHeight = lineHeight * textLines;

  element.style.height = `${newHeight}rem`; // スクロールの高さに基づいて高さを設定
};
onMounted(() => {
  historyMemo.value &&
    historyMemo.value.forEach((elm) => {
      elm.value !== "" && adjustHeight(elm);
    });
});
</script>

<style scoped>
textarea {
  resize: none;
  overflow: hidden;
}
</style>

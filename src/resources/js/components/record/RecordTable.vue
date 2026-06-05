<template>
  <div>
    <thead>
      <tr class="grid grid-cols-2 w-2full">
        <th class="mx-auto block w-full col-span-2">
          {{ menuContent }}
        </th>
      </tr>

      <tr>
        <th class="text-left md:text-center indent-1 md:indent-0">
          <div class="border" ref="todayRecordedAt">今回の記録</div>
        </th>
        <th class="text-left md:text-center indent-1 md:indent-0">
          <div class="border" ref="beforeRecordedAt">
            {{ beforeHeaderTxt }}
          </div>
        </th>
      </tr>
    </thead>
    <tbody>
      <!-- <tr v-for="(content, index) in contents" :key="index"> -->
      <tr v-for="index in maxSet" :key="index">
        <td @keyup.enter="nextInputFocus($event)">
          <div class="bg-gray-200 border indent-1">{{ index + 1 }}セット目</div>
          <div :class="hasOneHand ? 'hidden' : 'block'">
            <!-- changeだとfocusが外れた時、inputは入力したとき -->
            <!-- v-modelは:valueと@changeで表せる -->
            <input
              class="border w-full pl-0.5"
              type="text"
              name="weight"
              placeholder="重さ(kg)"
              maxlength="6"
              :value="weight[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    weight,
                                    index
                                )
                            "
              @change="
                                validateDecimalNumber(
                                    ($event.target as HTMLInputElement).value,
                                    weight,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
            <input
              class="border w-full pl-0.5"
              type="text"
              name="rep"
              placeholder="回数"
              maxlength="3"
              :value="rep[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    rep,
                                    index
                                )
                            "
              @change="
                                validateNumber(
                                    ($event.target as HTMLInputElement).value,
                                    rep,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
          </div>
          <div :class="hasOneHand ? 'block' : 'hidden'">
            <input
              class="border w-full pl-0.5"
              type="text"
              name="rightWeight"
              placeholder="重さ（右）(kg)"
              maxlength="6"
              :value="rightWeight[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    rightWeight,
                                    index
                                )
                            "
              @change="
                                validateDecimalNumber(
                                    ($event.target as HTMLInputElement).value,
                                    rightWeight,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
            <input
              class="border w-full pl-0.5"
              type="text"
              name="rightRep"
              placeholder="回数（右）"
              maxlength="3"
              :value="rightRep[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    rightRep,
                                    index
                                )
                            "
              @change="
                                validateNumber(
                                    ($event.target as HTMLInputElement).value,
                                    rightRep,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
            <input
              class="border w-full pl-0.5"
              type="text"
              name="leftWeight"
              placeholder="重さ（左）(kg)"
              maxlength="6"
              :value="leftWeight[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    leftWeight,
                                    index
                                )
                            "
              @change="
                                validateDecimalNumber(
                                    ($event.target as HTMLInputElement).value,
                                    leftWeight,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
            <input
              class="border w-full pl-0.5"
              type="text"
              name="leftRep"
              placeholder="回数（左）"
              maxlength="3"
              :value="leftRep[index]"
              @focus="
                                complementData(
                                    ($event.target as HTMLInputElement).value,
                                    leftRep,
                                    index
                                )
                            "
              @change="
                                validateNumber(
                                    ($event.target as HTMLInputElement).value,
                                    leftRep,
                                    index
                                )
                            "
              @blur="postRecordContent(index)"
            />
          </div>
          <div class="border">
            <textarea
              class="w-full leading-4 pl-0.5"
              v-model="memo[index]"
              name="memo"
              cols="20"
              rows="4"
              placeholder="メモ"
              @blur="postRecordContent(index)"
              ref="thisMemo"
              @input="
                                adjustHeight(
                                    $event.target as HTMLInputElement,
                                    beforeMemo[index]
                                )
                            "
            ></textarea>
          </div>
        </td>
        <td>
          <div class="bg-gray-200 border indent-1">{{ index + 1 }}セット目</div>
          <!-- 前回のセットがある場合 -->
          <!-- <template v-if="contents[index].set"> -->
          <div :class="hasOneHand ? 'hidden' : 'block'">
            <!-- readonlyだとfocusできるが、disabledだとfocusもできない -->
            <!-- inputのvalue値にデータを入力するにはv-bindを用いる -->
            <input
              class="border w-full"
              type="text"
              placeholder="重さ(kg)"
              :value="contents[index].set ? contents[index].weight : ''"
              disabled
            />
            <input
              class="border w-full"
              type="text"
              placeholder="回数"
              :value="contents[index].set ? contents[index].rep : ''"
              disabled
            />
          </div>
          <div :class="hasOneHand ? 'block' : 'hidden'">
            <input
              class="border w-full"
              type="text"
              placeholder="重さ（右）(kg)"
              :value="contents[index].set ? contents[index].right_weight : ''"
              disabled
            />
            <input
              class="border w-full"
              type="text"
              placeholder="回数（右）"
              :value="contents[index].set ? contents[index].right_rep : ''"
              disabled
            />
            <input
              class="border w-full"
              type="text"
              placeholder="重さ（左）(kg)"
              :value="contents[index].set ? contents[index].left_weight : ''"
              disabled
            />
            <input
              class="border w-full"
              type="text"
              placeholder="回数（左）"
              :value="contents[index].set ? contents[index].left_rep : ''"
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
              :value="contents[index].set ? inputBeforeMemo(index) : ''"
              disabled
              ref="beforeMemo"
            ></textarea>
          </div>
          <!-- </template> -->
          <!-- 前回のセットがない場合 -->
          <!-- <template v-else>
            <div :class="hasOneHand ? 'hidden' : 'block'">
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="重さ(kg)"
                ref="beforeWeight"
                disabled
              />
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="回数"
                ref="beforeReps"
                disabled
              />
            </div>
            <div :class="hasOneHand ? 'block' : 'hidden'">
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="重さ（右）(kg)"
                ref="beforeRightWeight"
                disabled
              />
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="回数（右）"
                ref="beforeRightReps"
                disabled
              />
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="重さ（左）(kg)"
                ref="beforeLeftWeight"
                disabled
              />
              <input
                class="border w-full pl-0.5"
                type="text"
                placeholder="回数（左）"
                ref="beforeLeftReps"
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
                disabled
                ref="beforeMemo"
              ></textarea>
            </div>
          </template> -->
        </td>
      </tr>
    </tbody>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch, ComputedRef } from "vue";
import { onBeforeRouteLeave, useRoute } from "vue-router";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetTgtRecordContent from "../../composables/record/useGetTgtRecordContent.js";
import axios from "axios";
import { useStore } from "vuex";
import { HistoryRecord } from "../../types/record";
import userSessionStorage from "../../utils/userSessionStorage";

// エンターキーを押すと次の要素入力可
// function keydown(e) {
//   if (e.keyCode === 13) {
//     var obj: HTMLTextAreaElement | HTMLInputElement = document.activeElement as
//       | HTMLInputElement
//       | HTMLTextAreaElement;
//     if (obj.parentNode) {
//       const parentNode: HTMLDivElement = obj.parentNode as HTMLDivElement;
//       if (obj.nextElementSibling && parentNode.nextElementSibling) {
//         const input: HTMLInputElement = obj.nextElementSibling as HTMLInputElement;
//         input.focus();
//       } else if (obj.parentNode.nextSibling) {
//         const nextSibling: HTMLDivElement = obj.parentNode.nextSibling as HTMLDivElement;
//         if (nextSibling.children) {
//           if (nextSibling.children[0].nodeName == "TEXTAREA") {
//             const nextSiblingChild0: HTMLInputElement = nextSibling
//               .children[0] as HTMLInputElement;
//             nextSiblingChild0.focus();
//           }
//           if (nextSibling.children[2]) {
//             const grandNextSibling: HTMLDivElement = nextSibling.children[2].parentNode
//               .nextSibling as HTMLDivElement;
//             if (grandNextSibling.children) {
//               if (grandNextSibling.children[0].nodeName == "TEXTAREA") {
//                 const grandNextSiblingChild0: HTMLInputElement = grandNextSibling
//                   .children[0] as HTMLInputElement;
//                 grandNextSiblingChild0.focus();
//                 // 1行目指定のため(無いと2行目指定となる)
//                 e.returnValue = false;
//               }
//             }
//           }
//         }
//       }
//     }
//   }
// }

// window.onkeydown = keydown;

const props = defineProps<{
  second_record: HistoryRecord[];
  hasSecondRecord: boolean;
  hasOneHand: boolean;
  category_id: string;
  menu_id: string;
  record_state_id: string;
  menu_content: string;
  beforeHeaderTxt: string;
  complementContents: boolean;
}>();
const route = useRoute();
const store = useStore();
store.commit("compGetData", false);

const hasOneHand: ComputedRef<boolean> = computed(() => props.hasOneHand);
const second_record: ComputedRef<HistoryRecord[]> = computed(() => props.second_record);
const menuContent: ComputedRef<string> = computed(() => props.menu_content);
const beforeHeaderTxt: ComputedRef<string> = computed(() => props.beforeHeaderTxt);
const complementContents: ComputedRef<boolean> = computed(() => props.complementContents);
const weight = ref<string[]>([]);
const rep = ref<string[]>([]);
const rightWeight = ref<string[]>([]);
const rightRep = ref<string[]>([]);
const leftWeight = ref<string[]>([]);
const leftRep = ref<string[]>([]);
const memo = ref<string[]>([]);
const doRecord = ref<boolean>(false);
const doDelete = ref<boolean>(false);
const maxSet: number[] = Array(10)
  .fill(0)
  .map((_, i) => i);

// DOM要素を取得するにはref属性と同じ名前にする
const beforeMemo = ref<HTMLInputElement[] | null>(null);
const thisMemo = ref<HTMLInputElement[] | null>(null);

const maxBeforeLength = ref<string>("");

const canClickFillBefore = ref<boolean>(false);

// メニューはセレクトボックス、休憩時間はタイムピッカー
type DispContents = {
  set: number;
  menu?: string;
  weight?: number;
  rep?: number;
  rest?: number;
  left_rep?: number | null;
  left_volume?: number | null;
  left_weight?: number | null;
  memo?: string | null;
  right_rep?: number | null;
  right_volume?: number | null;
  right_weight?: number | null;
  volume?: number | null;
};

const isDisabled = ref<boolean>(false);

//ログインユーザー情報取得
const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

//今回記録するデータの値を取得
const { tgtRecord, hasTgtRecord, getTgtRecords } = useGetTgtRecordContent();

const contents = ref<DispContents[]>([
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
  { set: 0, menu: "", weight: 0, rep: 0, rest: 0 },
]);

const emits = defineEmits<{
  (e: "totalSet", value: string): void;
  (e: "beforeTotalSet", value: string): void;
  (e: "canClick", value: boolean): void;
}>();

// watchは引数を二つ持つ(一つ目：監視対象、二つ目：新しい値と古い値)
watch(
  second_record,
  () => {
    if (props.hasSecondRecord) {
      maxBeforeLength.value = "0";
      second_record.value.forEach((record) => {
        const index: number = record.set - 1;
        contents.value[index] = record;
        if (Number(maxBeforeLength.value) < record.set) {
          maxBeforeLength.value = record.set.toString();
        }
      });
      //emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
      emits("beforeTotalSet", second_record.value.length.toString());
      const tempObj = ref<DispContents[]>([]);
      for (let i = 1; i <= 5 - Number(maxBeforeLength.value); i++) {
        tempObj.value[i] = { set: Number(maxBeforeLength.value) };
        contents.value = [...contents.value, tempObj.value[i]];
      }
    }
  },
  { immediate: true }
);

// エンターキーを押すと次の入力要素フォーカス
const nextInputFocus = (e: KeyboardEvent) => {
  const inputTags = (e.currentTarget as HTMLInputElement)?.querySelectorAll("input");
  const textareaTags = (e.currentTarget as HTMLInputElement)?.querySelectorAll(
    "textarea"
  );

  for (let index = 0; index <= inputTags.length - 1; index++) {
    //通常の場合
    if (index === 0 && inputTags[index] === document.activeElement) {
      // インプットエリアにフォーカス
      inputTags[index + 1].focus();
      break;
    } else if (index === 1 && inputTags[index] === document.activeElement) {
      // テキストエリアにフォーカス
      textareaTags[0].focus();
      break;
    } else if (
      2 <= index &&
      index < inputTags.length - 1 &&
      inputTags[index] === document.activeElement
    ) {
      // 片方ずつ記録する場合
      inputTags[index + 1].focus();
      break;
    } else if (
      index === inputTags.length - 1 &&
      inputTags[index] === document.activeElement
    ) {
      // テキストエリアにフォーカス
      textareaTags[0].focus();
      break;
    }
  }
};

//全角→半角
const replaceFullToHalf = (str: string) => {
  return str.replace(/[！-～]/g, function (s) {
    return String.fromCharCode(s.charCodeAt(0) - 0xfee0);
  });
};

// valはString
const validateDecimalNumber = (val: string, tgtVal: string[], index: number) => {
  // tgtvalの変更がない場合代入してもvalueの値が変化しないため
  tgtVal[index] = val;
  val = replaceFullToHalf(val);
  // 小数点を含むか？
  let oldVal = val;
  const decPoint: number = oldVal.indexOf(".");
  // replaceは型がStringのもののみ適用できる(replaceはそのものの値自体は変えないので代入する必要あり)
  // 数字または小数点以外を無効とする
  val = val.replace(/[^0-9|.]/g, "");
  // parseFloatで少数型へ変換している
  if (val !== "") {
    val = parseFloat(val).toString();
    // toFixedで小数第一位で四捨五入する
    val = parseFloat(Number(val).toFixed(1)).toString();
    // matchは型がStringのもののみ適用できる
    val.toString().match(/^(\d+)(\.\d*)?/u) ? val : "";
  }
  tgtVal[index] = val.toString();
};

const validateNumber = (val: string, tgtVal: string[], index: number) => {
  // tgtvalの変更がない場合代入してもvalueの値が変化しないため
  tgtVal[index] = val;
  val = replaceFullToHalf(val);
  // 数字または小数点以外を無効とする
  val = val.replace(/[^0-9]/g, "");
  // parseFloatで少数型へ変換している
  if (val !== "") {
    val = parseFloat(val).toString();
    // toFixedで小数第一位で四捨五入する
    val = parseFloat(Number(val).toFixed(1)).toString();
    // matchは型がStringのもののみ適用できる
    val.toString().match(/^(\d+)(\.\d*)?/u) ? val : "";
  }
  tgtVal[index] = val.toString();
};

const postRecordContent = (index: number) => {
  axios
    .post("/api/recordContent/create", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      weight: weight.value[index],
      right_weight: rightWeight.value[index],
      right_rep: rightRep.value[index],
      left_weight: leftWeight.value[index],
      left_rep: leftRep.value[index],
      rep: rep.value[index],
      set: index + 1,
      memo: memo.value[index],
    })
    .then((res) => {
      // 今回の合計セット数
      canClickFillBefore.value = true;
      // emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
      emits("totalSet", res.data.totalSet);
      if (res.data.totalSet > 0) {
        canClickFillBefore.value = true;
      } else {
        canClickFillBefore.value = false;
      }
      emits("canClick", canClickFillBefore.value);
    })
    .catch((err) => {
      canClickFillBefore.value = false;
      emits("canClick", canClickFillBefore.value);
    });
};

// 重量と回数を自動補完
const complementData = (val: string, tgtVal: string[], index: number) => {
  if (
    index - 1 > -1 &&
    complementContents.value &&
    tgtVal[index - 1] &&
    (tgtVal[index] == "" || tgtVal[index] == undefined)
  ) {
    val = tgtVal[index - 1].toString();
    tgtVal[index] = tgtVal[index - 1];
    postRecordContent(index);
  } else {
    tgtVal[index];
  }
};

//tgtRecordを初期レンダリング時に取得するため、変更を常にwatchする。
watch(tgtRecord, () => {
  if (hasTgtRecord.value) {
    canClickFillBefore.value = true;
    emits("canClick", canClickFillBefore.value);
    //emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
    emits("totalSet", tgtRecord.value.length.toString());
    tgtRecord.value.forEach((record) => {
      const index: number = record.set - 1;
      weight.value[index] = record.weight !== null ? record.weight : "";
      rep.value[index] = record.rep !== null ? record.rep : "";
      rightWeight.value[index] = record.right_weight !== null ? record.right_weight : "";
      rightRep.value[index] = record.right_rep !== null ? record.right_rep : "";
      leftWeight.value[index] = record.left_weight !== null ? record.left_weight : "";
      leftRep.value[index] = record.left_rep !== null ? record.left_rep : "";
      memo.value[index] = record.memo !== null ? record.memo : "";
      if (record.set > 5) {
        const tempObj = ref([]);
        for (let i = 6; i <= record.set; i++) {
          tempObj.value[i] = { set: record.set + i };
          contents.value = [...contents.value, tempObj.value[i]];
        }
      }
    });
  } else {
    canClickFillBefore.value = false;
    emits("canClick", canClickFillBefore.value);
  }
});
// 高さを調整する関数
const adjustHeight = (
  element: HTMLInputElement,
  tgtTxtElm: HTMLInputElement | null = null
) => {
  // 現在の高さを取得
  const currentHeight = element.offsetHeight;
  const currentTgtHeight = tgtTxtElm.offsetHeight;
  const newHeight = element.scrollHeight;
  // 新しい高さが現在の高さより小さくない場合のみ、高さを更新
  if (newHeight > Math.max(currentHeight, currentTgtHeight)) {
    element.style.height = `${newHeight}px`; // スクロールの高さに基づいて高さを設定
    tgtTxtElm.style.height = `${newHeight}px`; // スクロールの高さに基づいて高さを設定
  } else {
    element.style.height = `${Math.max(currentHeight, currentTgtHeight)}px`;
    tgtTxtElm.style.height = `${Math.max(currentHeight, currentTgtHeight)}px`;
  }
};

const inputBeforeMemo = (index) => {
  if (!beforeMemo.value) return;
  beforeMemo.value[index].value = contents.value[index].memo;
  adjustHeight(beforeMemo.value[index], thisMemo.value[index]);
  return contents.value[index].memo;
};

onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  await getTgtRecords(
    loginUser.value.id,
    props.category_id,
    props.menu_id,
    props.record_state_id
  );
  if (tgtRecord.value.length == 0) {
    emits("totalSet", "0");
  }
  store.commit("compGetData", true);

  thisMemo.value &&
    thisMemo.value.forEach((elm, index) => {
      elm.value !== "" && adjustHeight(elm, beforeMemo.value[index]);
    });
});

// 戻るボタン押下時に入力中内容を保存する
onBeforeRouteLeave(async (to, from, next) => {
  // 現在フォーカスが当たっている要素
  const activeElem = document.activeElement;
  // セット数
  let index: number = -1;

  if (activeElem.tagName == "INPUT") {
    if (activeElem) {
      switch ((activeElem as HTMLInputElement).name) {
        case "weight":
        case "rep":
          index =
            Number(
              activeElem.parentElement.previousElementSibling.innerHTML.slice(0, 1)
            ) - 1;
          if ((activeElem as HTMLInputElement).name == "weight") {
            validateDecimalNumber(
              (activeElem as HTMLInputElement).value,
              weight.value,
              index
            );
          } else if ((activeElem as HTMLInputElement).name == "rep") {
            validateNumber((activeElem as HTMLInputElement).value, rep.value, index);
          }
          break;
        case "rightWeight":
        case "rightRep":
        case "leftWeight":
        case "leftRep":
          index =
            Number(
              activeElem.parentElement.previousElementSibling.previousElementSibling.innerHTML.slice(
                0,
                1
              )
            ) - 1;
          if ((activeElem as HTMLInputElement).name == "rightWeight") {
            validateDecimalNumber(
              (activeElem as HTMLInputElement).value,
              rightWeight.value,
              index
            );
          } else if ((activeElem as HTMLInputElement).name == "rightRep") {
            validateNumber((activeElem as HTMLInputElement).value, rightRep.value, index);
          } else if ((activeElem as HTMLInputElement).name == "leftWeight") {
            validateDecimalNumber(
              (activeElem as HTMLInputElement).value,
              leftWeight.value,
              index
            );
          } else if ((activeElem as HTMLInputElement).name == "leftRep") {
            validateNumber((activeElem as HTMLInputElement).value, leftRep.value, index);
          }
          break;
      }
    }
    if (index > -1) {
      postRecordContent(index);
    }
  } else if (activeElem.tagName === "TEXTAREA") {
    if (activeElem) {
      switch ((activeElem as HTMLTextAreaElement).name) {
        case "memo":
          index =
            Number(
              activeElem.parentElement.parentElement.firstElementChild.innerHTML.slice(
                0,
                1
              )
            ) - 1;
          break;
      }
      if (index > -1) {
        postRecordContent(index);
      }
    }
  }
  next();
});
</script>

<style scoped>
textarea {
  resize: none;
  overflow: hidden;
}
</style>

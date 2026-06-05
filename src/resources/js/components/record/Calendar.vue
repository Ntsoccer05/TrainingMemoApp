// setup() 関数内で定義した変数や関数を return しないと template //
内で使用することができなかったが、 script setup>内で宣言した場合すべて使用可能となる
<script setup lang="ts">
// https://v2.vcalendar.io/attributes.html#_2-scoped-slot
import { useRouter, useRoute } from "vue-router";
import { useStore } from "vuex";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useSelectedDay from "../../composables/record/useSelectedDay";
import useGetRecords from "../../composables/record/useGetRecords";
import axios from "axios";
import { reactive, ref, computed, ComputedRef, watch, onMounted, nextTick } from "vue";
import userSessionStorage from "../../utils/userSessionStorage";

const emits = defineEmits<{
  (e: "compGetData", value: boolean): void;
}>();

const router = useRouter();
const route = useRoute();
const store = useStore();
// 祝日の情報を取得
const url = "https://holidays-jp.github.io/api/v1/date.json";
const options = { method: "get" };

type LoginUser = {
  created_at: string;
  email: string;
  email_verified_at: null;
  id: number;
  name: string;
  updated_at: string;
};

const authUser = ref<LoginUser>({
  created_at: "",
  email: "",
  email_verified_at: null,
  id: 0,
  name: "",
  updated_at: "",
});

type Attrs = {
  key?: string;
  highlight: boolean | string;
  dates: Date;
};

type Popover = {
  label: string;
  visibility: string;
  autoHide: boolean;
};
type Bar = {
  style: {
    backgroundColor: string;
  };
};
type Event = {
  popover: Popover;
  bar: Bar;
  dates: Date;
};

type Obj = {
  dot: boolean;
  content: string;
  dates: Date;
};

type Data = {
  [key: string]: string;
};

// 当日をハイライト
const attrs = ref<(Attrs | Event | Obj)[]>([
  { key: "today", highlight: true, dates: new Date() },
]);

const holidays = ref<string[]>([]);
let data = reactive<Data>({});

// データ取得完了したかどうか
// const isLoaded = ref<boolean>(false);

const dispAlertModal = ref(false);

// /データを送信したか
const isSendData = ref<Boolean>(false);

const loginState = computed(() => store.getters.isLogined);
const isLogined: ComputedRef<Boolean> = computed(() => store.state.isLogined);

// script setup内だとDom取得はreturnしなくていい Vueコンポーネントだから型付けると特有のメソッドを使えない
const calendar = ref();

const selected_day = ref<String>("");

// 選択された月日へ移動
const fromPath = ref<string>("");
const currentPath = ref<string>("");

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

const { records, compGetData, isLoaded, getRecords } = useGetRecords();

watch(records, () => {
  let label: string = "";
  records.value.forEach((record) => {
    if (record.menu !== undefined) {
      label = record.menu[0].menu_content;
    } else {
      label = "記録がありません";
    }
    const event: Event = {
      popover: {
        label: label,
        visibility: "click",
        autoHide: false,
      },
      bar: {
        style: {
          backgroundColor: "red",
        },
      },
      // safariだと年-月-日だとNanとなるため年/月/日に変更
      dates: new Date(record.recorded_at.recorded_at.replace(/-/g, "/") as string),
    };
    attrs.value = [...attrs.value, event];
  });
});

watch(holidays.value, () => {
  holidays.value.forEach((holiday) => {
    const obj: Obj = {
      dot: true,
      // Text styles
      content: "red",
      // safariだと年-月-日だとNanとなるため年/月/日に変更
      dates: new Date(holiday.replace(/-/g, "/") as string),
    };
    attrs.value = [...attrs.value, obj];
  });
});

const toLogin = () => {
  router.push("/login");
};

//日付フォーマットを修正
const changeDayFormat = (day) => {
  // 年-月-日の形に修正
  day = day.replace("日", "");
  day = day.replace(/年|月/g, "-");
  // safariだと年-月-日だとNanとなるため年/月/日に変更
  const date = new Date(day.replace(/-/g, "/"));
  day = `${date.getFullYear().toString()}-${(date.getMonth() + 1)
    .toString()
    .padStart(2, "0")}-${date.getDate().toString().padStart(2, "0")}`;
  selected_day.value = day;
  return day;
};

// 選択された月日へ移動
const menuScroll = (calendarDom) => {
  // 前画面パスを設定
  if (window.history.state.forward) {
    fromPath.value = window.history.state.forward.split("/")[2];
  } else if (window.history.state.back) {
    fromPath.value = window.history.state.back.split("/")[2];
  }
  const day = ref<Date>(null);
  day.value = new Date(fromPath.value);
  // 現在画面パスを設定
  currentPath.value = window.history.state.current.split("/")[1];
  // クエリパラメータに選択されていたdateを指定することで移動
  if (!Number.isNaN(day.value.getDate())) {
    calendarDom.move(new Date((fromPath.value as string).replace(/-/g, "/")));
    // 選択された日付のクエリパラメータを付与
    router.push({ name: "home", query: { day: fromPath.value } });
    // 選択された日付をマーク
    const obj = {
      key: "selected_day",
      highlight: "green",
      // safariだと年-月-日だとNanとなるため年/月/日に変更
      dates: new Date((fromPath.value as string).replace(/-/g, "/")),
    };
    attrs.value = [...attrs.value, obj];
  } else {
    router.push({ name: "home", query: { day: fromPath.value } });
  }
};

//ログインしているかの判別をする場合DOMが生成されていない状態だとログイン状態を判別できないため
//getLoginUser はApp.vueで行う
onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  getHolidays();
  if (loginUser.value.id) {
    await getRecords(loginUser.value.id);
  } else {
    await getRecords(0);
  }
  isLoaded.value = true;
  emits("compGetData", true);

  // getLoginUser()内でnextTickを実行
  authUser.value = loginUser.value;
  nextTick(() => {
    // DOM取得のため<-script setupではnextTickの中でないとDOM取得できない。
    calendar.value !== undefined;
    const calendarDom = calendar.value !== undefined ? calendar.value : "";

    // ログイン状態をVuexより取得<-このタイミングだとカレンダーの描画が完了しているためVuexの値を取得できる。
    // isLogined.value = computed(() => store.state.isLogined);

    // toDetailPage();
    // クエリパラメータがあればリロード時にその日付が存在するページを表示
    menuScroll(calendarDom);
    // if (route.query.day && calendarDom !== "") {
    //   // safariだと年-月-日だとNanとなるため年/月/日に変更
    //   calendarDom.move(new Date((route.query.day as string).replace(/-/g, "/")));
    // }
  });
});

// 日付選択時にレコード記録
const selectedDayRecord = async (day) => {
  await axios
    .post("/api/record/create", {
      user_id: loginUser.value.id,
      recording_day: day,
    })
    .then((res) => {
      store.commit("setRecordedAt", day);
      router.push({ name: "selectMenu", params: { recordId: day } });
    })
    .catch((err) => {});
};

// 日付選択時処理
const selectedDay = (day) => {
  // 日付をクリック時
  if (day.ariaLabel !== null) {
    //ログインしていなかったらメッセージを表示
    if (!loginUser.value) {
      dispAlertModal.value = true;
      return;
    }
    // 年-月-日の形に修正
    day = day.ariaLabel.split("日");
    day = day[0].replace(/年|月/g, "-");
    changeDayFormat(day);
    const isRecord = ref(false);
    for (let record of records.value) {
      if (record.recorded_at.recorded_at === selected_day.value) {
        isRecord.value = true;
      }
    }
    //レコードがあればPOST送信しない
    if (isRecord.value === true) {
      return;
    }
    const { postDay } = useSelectedDay(day);
    if (!isSendData.value) {
      isSendData.value = true;
      // 日付クリック時にPOST送信する
      selectedDayRecord(postDay);
    }
  }
};

// 休日を取得
const getHolidays = async () => {
  // 祝日の情報を取得
  await fetch(url, options).then((response) => {
    // data = response.json();
    // data.value.then((val) => {
    response.json().then((val) => {
      for (const days in val) {
        holidays.value.push(days);
      }
    });
  });
};

// 詳細ページへ遷移
const toDetailPage = async (day) => {
  if (day) {
    // 年-月-日の形に修正
    day = day.ariaLabel.split("日");
    day = day[0].replace(/年|月/g, "-");
    const { postDay } = useSelectedDay(day);
    await axios
      .post("/api/record/create", {
        user_id: loginUser.value.id,
        recording_day: postDay,
      })
      .then((res) => {
        store.commit("setRecordedAt", day);
        router.push({ name: "selectMenu", params: { recordId: day } });
      });
  }
};

//今日のカレンダーを表示
const moveToday = () => {
  calendar.value.move(new Date());
};
</script>

<template>
  <div class="calendar md:w-11/12 ml:h-2/3 mx-auto h-2/3">
    <!-- $event.targetでクリックした要素を取得できる -->
    <template v-if="compGetData && isLoaded">
      <v-calendar
        ref="calendar"
        locale="ja-jp"
        :attributes="attrs"
        @click="selectedDay($event.target)"
      >
        <!-- Calendarの中に以下でもタイトル名変更可能
          :masks = masks -->
        <!-- タイトル変更：header-titleのslot-scopeの中のpropを利用 (#はv-slotの省略記法) -->
        <template #header-title="props">
          {{ props.yearLabel }}年 {{ props.monthLabel }}
        </template>
        <!-- day-popoverのslot-scopeの中のpropの中にdayがある ← 分割代入 -->
        <template #day-popover="{ day, format, masks }" class="z-50">
          <div class="text-xs text-gray-300 font-semibold text-center">
            {{ format(day.date, masks.L) }}
          </div>
          <div class="text-xs text-gray-300 font-semibold text-center">鍛えた部位</div>
          <div class="text-xs text-gray-300 font-semibold text-center">
            <span v-for="record in records" :key="record.recorded_at.recorded_at">
              <template
                v-if="
                  record.recorded_at.recorded_at ==
                  changeDayFormat(format(day.date, masks.L))
                "
                ><span
                  v-for="(category, index) in record.category"
                  :key="category.category_id"
                >
                  <template
                    v-if="category.category_content && record.category !== undefined"
                  >
                    <template v-if="index != Object.keys(record.category).length - 1">
                      {{ category.category_content }}、
                    </template>
                    <!-- 最後の値を表示 -->
                    <template
                      v-else-if="index == Object.keys(record.category).length - 1"
                    >
                      {{ category.category_content }}
                    </template>
                  </template>
                </span>
                <!-- 登録がない場合 -->
                <template v-if="record.category === undefined">
                  <span>登録なし</span>
                </template>
              </template>
            </span>
          </div>
          <div class="text-xs text-gray-300 font-semibold text-center">メニュー</div>
          <span v-for="record in records" :key="record.recorded_at.recorded_at">
            <template
              v-if="
                record.recorded_at.recorded_at ==
                changeDayFormat(format(day.date, masks.L))
              "
              ><span v-for="(menu, index) in record.menu" :key="menu.menu_id">
                <template v-if="menu.menu_content && record.menu !== undefined">
                  <template v-if="index != Object.keys(record.menu).length - 1">
                    {{ menu.menu_content }}、
                  </template>
                  <!-- 最後の値を表示 -->
                  <template v-else-if="index == Object.keys(record.menu).length - 1">
                    {{ menu.menu_content }}
                  </template>
                </template>
              </span>
              <!-- 登録がない場合 -->
              <template v-if="record.menu === undefined">
                <span class="block text-center">登録なし</span>
              </template>
            </template>
          </span>
          <div class="flex flex-col items-center justify-center mt-2">
            <button
              class="bg-blue-500 hover:bg-blue-400 text-white font-bold py-1 px-4 border-b-4 border-blue-700 hover:border-blue-500 rounded"
              @click.prevent="toDetailPage(day)"
            >
              詳細へ
            </button>
          </div>
        </template>
        <template #footer>
          <div class="w-full px-4 pb-3">
            <button
              class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold w-full px-3 py-1 rounded-md"
              @click="moveToday"
            >
              今月のカレンダー表示
            </button>
          </div>
        </template>
      </v-calendar>
    </template>
    <template v-else-if="isLoaded">
      <!-- 親の@clickイベントに引きつられるため修飾子stopを追加 -->
      <v-calendar
        ref="calendar"
        locale="ja-jp"
        :attributes="attrs"
        @click.stop="selectedDay($event.target)"
      >
        <!-- Calendarの中に以下でもタイトル名変更可能
          :masks = masks -->
        <!-- タイトル変更：header-titleのslot-scopeの中のpropを利用 (#はv-slotの省略記法) -->
        <template #header-title="props">
          {{ props.yearLabel }}年 {{ props.monthLabel }}
        </template>
        <template #footer>
          <div class="w-full px-4 pb-3">
            <button
              class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold w-full px-3 py-1 rounded-md"
              @click="moveToday"
            >
              今月のカレンダー表示
            </button>
          </div>
        </template>
      </v-calendar>
    </template>
    <template v-else>
      <p class="text-center mt-5">データ読み込み中です。少々お待ちください。</p>
    </template>
    <Modal
      v-model="dispAlertModal"
      title="権限エラー"
      wrapper-class="modal-wrapper"
      class="flex align-center"
    >
      <p>ログインしてください。</p>
      <button
        class="col-12 mt-5 text-center inline-block w-full rounded px-6 pb-2 pt-2.5 text-base font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
        style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
        @click="toLogin"
      >
        ログイン画面へ
      </button>
    </Modal>
  </div>
</template>

<style scoped>
.vc-container {
  width: 100%;
  height: 100%;
}
/* ::v-deepで他のファイル要素にも反映 */
.vc-container::v-deep(.vc-pane-layout) {
  height: 100%;
}
.vc-container::v-deep(.vc-weeks) {
  height: 90%;
  align-items: center;
}
/* ヘッダークリック時のポップアップのタイトルに「年」を追加 */
.vc-container::v-deep(.vc-nav-title::after) {
  content: "年";
}
/* 土曜日を青色 */
.vc-container::v-deep(.weekday-7 span),
.vc-container::v-deep(.vc-weekday:nth-child(7)) {
  color: blue !important;
}

/* 日曜日を赤色 */
.vc-container::v-deep(.weekday-1 span.vc-day-content),
.vc-container::v-deep(.vc-weekday:nth-child(1)) {
  color: red;
}

/* ドットを無効化 */
.vc-container::v-deep(.vc-dots) {
  display: none;
}

/* 祝日を赤色に変更 */
.vc-container::v-deep(.vc-day:has(.vc-dots) span) {
  color: red !important;
}
.vc-container::v-deep(.vc-popover-content-wrapper) {
  /* 背景を押下できなくするため pointer-events: none;だと背景を押下できてしまう。 */
  pointer-events: painted;
}
@media (1024px <= width) {
  .vc-container::v-deep(.vc-popover-content-wrapper) {
    max-width: 230px;
  }
}
@media (768px <= width < 1024px) {
  .vc-container::v-deep(.vc-popover-content-wrapper) {
    max-width: 170px;
  }
}
@media (320px <= width < 768px) {
  .vc-container::v-deep(.vc-popover-caret) {
    display: none;
  }
  .vc-container::v-deep(.vc-day-content:hover) {
    background-color: #adff2f;
  }
}
</style>

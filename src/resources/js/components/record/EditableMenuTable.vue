<template>
  <div class="md:flex md:items-start md:flex-wrap">
    <table
      v-for="category in categories"
      :key="category.id"
      class="border border-collapse table-fixed mx-auto mt-3 md:w-5/12 w-11/12"
    >
      <thead>
        <tr class="relative">
          <th :class="['border', editable ? '' : '']">
            <span :class="[editable ? 'hidden' : 'block']">{{ category.content }}</span>
            <div :class="['grid grid-cols-12 gap-2', editable ? 'block' : 'hidden']">
              <!--- @blurでPOSTする -->
              <input
                @blur="postEditCategory(category)"
                type="text"
                v-model="category.content"
                class="border-2 col-span-11 text-center"
              />
              <button class="mr-1" @click="deleteCategoryMenu(category.id)">
                <i class="fa-solid fa-trash mt-2 hover:text-red-600"></i>
              </button>
            </div>
            <div class="hidden" ref="deleteCategory" :category_id="category.id">
              <div class="absolute top-0 bg-gray-400 w-full h-full opacity-80"></div>
              <div
                class="absolute grid top-0 grid-cols-12 justify-center align-center w-full h-full"
              >
                <div class="md:col-span-1 xl:col-span-3"></div>
                <p
                  class="text-sm xl:text-base col-start-1 col-span-6 md:col-span-5 mt-1 md:mt-1.5 xl:mt-1 text-white font-bold"
                >
                  部位を削除しますか？
                </p>
                <button
                  @click="deleteCategoryContent(category)"
                  class="text-sm xl:text-base col-span-3 md:col-span-3 xl:col-span-2 rounded-2xl font-bold bg-white mt-0.5 md:mt-1.5 xl:mt-1 ml-1 mr-1 sm:mr-2 h-5 xl:h-6 text-red-600"
                >
                  削除する
                </button>
                <button
                  @click="cancelEditCategoryClick(category)"
                  class="text-sm xl:text-base col-span-3 md:col-span-3 xl:col-span-2 ml-1 sm:ml-2 sm:mr-2 rounded-2xl font-bold bg-white mt-0.5 md:mt-1.5 xl:mt-1 h-5 xl:h-6 text-black"
                >
                  キャンセル
                </button>
              </div>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="relative" v-for="menu in category.menus" :key="menu.id">
          <td
            :class="[
              'border cursor-pointer',
              dataMenu.indexOf(menu.id) > -1 && !editable
                ? 'bg-green-400 hover:bg-green-600'
                : 'hover:bg-gray-200',
              editable ? '' : '',
            ]"
            @click="toRecordContents(category, menu)"
          >
            <span :class="['ml-2', editable ? 'hidden' : 'block']"
              >{{ menu.content }}
            </span>
            <div :class="['grid grid-cols-12 gap-2', editable ? 'block' : 'hidden']">
              <!--- @blurでPOSTする -->
              <input
                @blur="postEditMenu(category, menu)"
                type="text"
                v-model="menu.content"
                :class="[
                  'border-2 col-span-11',
                  dataMenu.indexOf(menu.id) > -1
                    ? 'bg-green-400 hover:bg-green-300'
                    : 'hover:bg-gray-200',
                ]"
              />
              <button class="mr-1" @click="deleteMenu(menu.id, category.id)">
                <i class="fa-solid fa-trash mt-2" :class="hoverRed"></i>
              </button>
            </div>
            <div
              class="hidden"
              ref="deleteFunc"
              :menu_id="menu.id"
              :category_id="category.id"
            >
              <div class="absolute top-0 bg-gray-400 w-full h-full opacity-80"></div>
              <div
                class="absolute grid top-0 grid-cols-12 justify-center align-center w-full h-full"
              >
                <div class="md:col-span-1 xl:col-span-3"></div>
                <p
                  class="text-sm xl:text-base col-start-1 col-span-6 md:col-span-5 mt-1 md:mt-1.5 xl:mt-1 text-white font-bold"
                >
                  種目を削除しますか？
                </p>
                <button
                  @click="deleteMenuContent(category, menu)"
                  class="text-sm xl:text-base col-span-3 md:col-span-3 xl:col-span-2 rounded-2xl font-bold bg-white mt-0.5 md:mt-1.5 xl:mt-1 ml-1 mr-1 sm:mr-2 h-5 xl:h-6 text-red-600"
                >
                  削除する
                </button>
                <button
                  @click="cancelClick(category, menu)"
                  class="text-sm xl:text-base col-span-3 md:col-span-3 xl:col-span-2 ml-1 sm:ml-2 sm:mr-2 rounded-2xl font-bold bg-white mt-0.5 md:mt-1.5 xl:mt-1 h-5 xl:h-6 text-black"
                >
                  キャンセル
                </button>
              </div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <template v-if="isOdd">
      <table
        class="border border-collapse table-fixed mx-auto mt-3 md:w-5/12 w-11/12"
      ></table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick, computed, ComputedRef } from "vue";
import draggable from "vuedraggable";
import { useRoute, useRouter } from "vue-router";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetRecordState from "../../composables/record/useGetRecordState";
import { DispRecords, Categories, Category, Menu } from "../../types/record";
import axios from "axios";
import userSessionStorage from "../../utils/userSessionStorage";
import session from "../../utils/sessionStorageUtil";

const props = defineProps<{
  editable: boolean;
  dispHeadText: string;
  records: DispRecords[];
  dataMenu: Array<number>;
}>();

const router = useRouter();
const route = useRoute();

//Propsの値はcomputedに入れないとreadOnlyとなり変更できない。
const editable: ComputedRef<boolean> = computed(() => props.editable);
const dispHeadText: ComputedRef<string> = computed(() => props.dispHeadText);
const dataMenu: ComputedRef<number[]> = computed(() => props.dataMenu);

const recorded_day: string = route.params.recordId as string;

const isOdd = ref<boolean>(false);

// DOM取得のため
const deleteFunc = ref(null);
const deleteCategory = ref(null);

const hoverRed = ref<string>("hover:text-red-600");

const categories = ref<Categories>([]);

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

const { getLatestRecordState, latestRecord } = useGetRecordState();

//トレーニング記録画面に遷移
const toRecordContents = (category: Category, menu: Menu): void => {
  if (!editable.value) {
    if (recorded_day) {
      router.push({
        name: "record",
        params: route.params,
        query: {
          categoryId: category.id,
          menuId: menu.id,
          recordId: latestRecord.value.id,
        },
      });
    } else {
      router.push({
        name: "record",
        params: route.params,
        query: {
          categoryId: category.id,
          menuId: menu.id,
          recordId: latestRecord.value.id,
        },
      });
    }
  } else {
    return;
  }
};

// 記録したレコードがあれば

//メニュー内容を取得
const getMenus = async () => {
  const cache = session.get<any>("trainingMenus");
  if (cache) {
    categories.value = cache.categorylist;
    isOdd.value = categories.value.length % 2 === 1;
    return;
  }
  await axios
    .get("/api/menus", {
      // get時にパラメータを渡す際はparamsで指定が必要
      params: {
        user_id: loginUser.value.id,
      },
    })
    .then((res) => {
      categories.value = res.data.categorylist;
      session.set("trainingMenus", res.data);
      if (categories.value.length % 2 === 1) {
        isOdd.value = true;
      } else {
        isOdd.value = false;
      }
    })
    .catch((err) => {});
};

//「本当に削除しますか？」ダイアログを表示/非表示
const deleteMenu = (menuId: number, categoryId: number): void => {
  const menu_id: number = menuId;
  const category_id: number = categoryId;
  for (const menu of deleteFunc.value) {
    if (
      menu_id == menu.attributes.menu_id.value &&
      category_id == menu.attributes.category_id.value
    ) {
      menu.className = "block";
    } else {
      menu.className = "hidden";
    }
  }
};

//「本当に削除しますか？」ダイアログを表示/非表示
const deleteCategoryMenu = (categoryId: number): void => {
  const category_id: number = categoryId;
  for (const category of deleteCategory.value) {
    if (category_id == category.attributes.category_id.value) {
      category.className = "block";
    } else {
      category.className = "hidden";
    }
  }
};

// メニュー内容を削除
const deleteMenuContent = async (category: Category, menu: Menu) => {
  if (category !== undefined && menu !== undefined) {
    await axios
      .post("/api/menus/delete", {
        user_id: loginUser.value.id,
        category_id: category.id,
        menu_id: menu.id,
        content: menu.content,
      })
      .then((res) => {
        for (const menu of deleteFunc.value) {
          if (menu.className == "block") {
            menu.className = "hidden";
          }
        }
        session.remove("trainingMenus");
        getMenus();
      })
      .catch((err) => {});
  }
};

// 削除するのをキャンセルする
const cancelClick = (category: Category, menu: Menu): void => {
  if (category !== undefined && menu !== undefined) {
    for (const menu of deleteFunc.value) {
      if (menu.className == "block") {
        menu.className = "hidden";
      }
    }
  }
};

//メニュー内容を編集する
const postEditMenu = async (category: Category, menu: Menu) => {
  if (category !== undefined && menu !== undefined) {
    await axios
      .post("/api/menus/update", {
        user_id: loginUser.value.id,
        category_id: category.id,
        menu_id: menu.id,
        content: menu.content,
      })
      .then((res) => {
        session.remove("trainingMenus");
      })
      .catch((err) => {});
  }
};

//カテゴリー内容を編集する
const postEditCategory = async (category: Category) => {
  if (category !== undefined) {
    await axios
      .post("/api/category/update", {
        user_id: loginUser.value.id,
        category_id: category.id,
        content: category.content,
      })
      .then((res) => {})
      .catch((err) => {});
  }
};

// メニュー内容を削除
const deleteCategoryContent = async (category: Category) => {
  if (category !== undefined) {
    await axios
      .post("/api/category/delete", {
        user_id: loginUser.value.id,
        category_id: category.id,
      })
      .then((res) => {
        for (const category of deleteCategory.value) {
          if (category.className == "block") {
            category.className = "hidden";
          }
        }
        getMenus();
      })
      .catch((err) => {});
  }
};

// 削除するのをキャンセルする
const cancelEditCategoryClick = (category: Category): void => {
  if (category !== undefined) {
    for (const category of deleteCategory.value) {
      if (category.className == "block") {
        category.className = "hidden";
      }
    }
  }
};

onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  // DOM取得のため
  const deleteFuncDom = deleteFunc.value;
  const deleteCategoryDom = deleteCategory.value;
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  await getLatestRecordState();

  getMenus();
});
</script>

<style></style>

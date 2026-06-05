<template>
  <div class="bg-gray-500 h-16 md:h-16">
    <header class="container text-white relative z-10">
      <div
        class="flex justify-between items-center fixed w-full bg-gray-500 px-2 h-16 md:h-16"
      >
        <template v-if="route.name === 'selectMenu' && compGetData">
          <h3 class="md:mr-auto md:border-none">
            <router-link
              :to="
                recorded_day
                  ? {
                      name: 'home',
                      query: { day: recorded_day },
                    }
                  : { name: 'home' }
              "
              class="md:text-xl font-semibold text-lg"
              ><i class="fa-solid fa-arrow-left mr-2"></i>日付選択へ戻る</router-link
            >
          </h3>
        </template>
        <template v-else-if="route.name === 'record' && compGetData">
          <h3 class="md:mr-auto md:border-none">
            <router-link
              :to="{
                name: 'selectMenu',
                params: { recordId: recorded_day },
              }"
              class="md:text-xl font-semibold text-lg"
              ><i class="fa-solid fa-arrow-left mr-2"></i>メニュー選択へ戻る</router-link
            >
          </h3>
        </template>
        <template v-else-if="route.name === 'addMenu'">
          <h3 class="md:mr-auto md:border-none">
            <router-link
              :to="{
                name: 'selectMenu',
                params: { recordId: recorded_day },
              }"
              class="md:text-xl font-semibold text-lg"
              ><i class="fa-solid fa-arrow-left mr-2"></i>メニュー選択へ戻る</router-link
            >
          </h3>
        </template>
        <template v-else-if="isloaded">
          <h3 class="md:mr-auto md:border-none">
            <template
              v-if="
                (route.name != 'selectMenu' &&
                  route.name != 'record' &&
                  route.name != 'addMenu' &&
                  isloaded) ||
                isOpen
              "
            >
              <router-link
                :to="
                  recorded_day
                    ? {
                        name: 'home',
                        query: { day: recorded_day },
                      }
                    : { name: 'home' }
                "
                class="text-xl font-semibold md:text-4xl"
              >
                トレメモ</router-link
              ></template
            >
          </h3>
        </template>
        <div>
          <button @click="isOpen = !isOpen" class="focus:outline-none md:hidden">
            <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
              <path
                v-show="!isOpen"
                d="M24 6h-24v-4h24v4zm0 4h-24v4h24v-4zm0 8h-24v4h24v-4z"
              />
              <path
                v-show="isOpen"
                d="M24 20.188l-8.315-8.209 8.2-8.282-3.697-3.697-8.212 8.318-8.31-8.203-3.666 3.666 8.321 8.24-8.206 8.313 3.666 3.666 8.237-8.318 8.285 8.203z"
              />
            </svg>
          </button>
        </div>
        <!-- ハンバーガーメニューの開閉(app.css) -->
        <div :class="isOpen ? 'show-toggleMenu' : 'hidden-toggleMenu'">
          <ul
            class="md:flex md:justify-between md:items-center md:mr-1 md: m-auto lg:mr-0"
            @click="closeHumbuger"
          >
            <template v-if="route.name === 'selectMenu' && !isOpen && compGetData">
              <li class="border-b border-t top- md:mr-auto md:border-none">
                <router-link
                  :to="
                    recorded_day
                      ? {
                          name: 'home',
                          query: { day: recorded_day },
                        }
                      : { name: 'home' }
                  "
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                  ><i class="fa-solid fa-arrow-left mr-2"></i>日付選択へ戻る</router-link
                >
              </li>
            </template>
            <template v-else-if="route.name === 'record' && !isOpen && compGetData">
              <li class="border-b border-t top- md:mr-auto md:border-none">
                <router-link
                  :to="{
                    name: 'selectMenu',
                    params: { recordId: recorded_day },
                  }"
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                  ><i class="fa-solid fa-arrow-left mr-2"></i
                  >メニュー選択へ戻る</router-link
                >
              </li>
            </template>
            <template v-else-if="route.name === 'addMenu' && !isOpen">
              <li class="border-b border-t top- md:mr-auto md:border-none">
                <router-link
                  :to="{
                    name: 'selectMenu',
                    params: { recordId: recorded_day },
                  }"
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                  ><i class="fa-solid fa-arrow-left mr-2"></i
                  >メニュー選択へ戻る</router-link
                >
              </li>
            </template>
            <template v-else-if="isloaded">
              <li class="border-b border-t top- md:mr-auto md:border-none">
                <template
                  v-if="
                    (route.name != 'selectMenu' &&
                      route.name != 'record' &&
                      route.name != 'addMenu' &&
                      isloaded) ||
                    isOpen
                  "
                >
                  <router-link
                    :to="
                      recorded_day
                        ? {
                            name: 'home',
                            query: {
                              day: recorded_day,
                            },
                          }
                        : { name: 'home' }
                    "
                    class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                    >トレメモ</router-link
                  ></template
                >
              </li>
            </template>
            <!--<li class="border-b md:border-none">
              <router-link
                class="block px-8 py-2 my-4 hover:bg-gray-600 rounded cursor-pointer"
                to="/ranking"
                >ランキング</router-link
              >
            </li> -->
            <template v-if="isLogined === true && isloaded">
              <li class="border-b md:border-none">
                <router-link
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded cursor-pointer"
                  to="/recordRanking"
                  >メニュー別最高記録</router-link
                >
              </li>
              <template v-if="loginUser.is_admin == true">
                <li class="border-b md:border-none">
                  <a
                    class="block px-8 py-2 my-4 hover:bg-gray-600 rounded cursor-pointer"
                    href="/admin"
                    >管理画面</a
                  >
                </li>
              </template>
              <li class="border-b md:border-none">
                <a
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded cursor-pointer"
                  @click.native="logout"
                  >ログアウト</a
                >
              </li>
            </template>
            <template v-else-if="isLogined === false && isloaded">
              <li class="border-b md:border-none">
                <router-link
                  to="/login"
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                  >ログイン</router-link
                >
              </li>
              <li class="border-b md:border-none">
                <router-link
                  to="/register"
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded"
                  >新規登録</router-link
                >
              </li>
            </template>
            <template v-if="isloaded">
              <li>
                <div class="my-8 text-center md:my-4 md:mr-3">
                  <router-link
                    to="/inquiry"
                    class="px-6 py-2 bg-orange-500 hover:bg-orange-400 rounded-full"
                    >お問い合わせ</router-link
                  >
                </div>
              </li>
            </template>
          </ul>
        </div>
      </div>
    </header>
    <Modal
      v-model="dispAlertModal"
      title="ログアウト"
      wrapper-class="modal-wrapper"
      class="flex align-center"
      @closing="toHome()"
    >
      <p>{{ dispAlertMessage }}</p>
    </Modal>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted, computed, watchEffect, ComputedRef } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import useHoldLoginState from "../../composables/certification/useHoldLoginState";
import { LoginUser } from "../../types/loginUser";
import axios from "axios";

const router = useRouter();
const route = useRoute();
const store = useStore();

// データ取得できたか
const isloaded = ref<boolean>(false);

const recorded_day = ref<string>("");
const recordedAt = ref<string>("");
const recorded_at: ComputedRef<string> = computed(() => store.getters.getRecordedAt);
const compGetData: ComputedRef<boolean> = computed(() => store.getters.compGetData);
const loginUser: ComputedRef<LoginUser> = computed(() => store.getters.loginUser);

const paramName = ref<string>("");

const isOpen = ref<boolean>(false);

const dispAlertModal = ref<boolean>(false);
const dispAlertMessage = ref<string>("");

// ログイン状態をVuexより取得
const isLogined: ComputedRef<boolean> = computed(() => store.state.isLogined);

//ちらつき防止のためログイン状態取得
const { holdLoginState } = useHoldLoginState();

watchEffect(() => {
  paramName.value = route.name as string;
  recorded_day.value = route.params.recordId as string;
  if (recorded_at) {
    recordedAt.value = recorded_at.value;
  }
});

// ホーム画面へ遷移
const toHome = (): string => (window.location.href = "/");

const closeHumbuger = (): void => {
  if (isOpen) {
    isOpen.value = false;
  }
};

onMounted(async () => {
  //ちらつき防止のためログイン状態取得
  await holdLoginState();
  if (isLogined.value === false || isLogined.value === true) {
    isloaded.value = true;
  }
});

// ログアウト処理
const logout = async () => {
  await axios
    .post("/api/logout", {})
    .then((res) => {
      if ((res.data.status_code = 200)) {
        sessionStorage.clear();
        dispAlertModal.value = true;
        if (route.name === "home") {
          dispAlertMessage.value = "ログアウトしました。";
        } else {
          dispAlertMessage.value = "ログアウトしました。ホーム画面へ遷移します。";
        }
        // ログイン状態を変更するためVuexより呼び出し
        store.commit("LogoutState");
        //ページ再読み込み
        // alert("ログアウトしました。");
        holdLoginState();
      }
    })
    .catch((err) => {});
};
</script>

<style></style>

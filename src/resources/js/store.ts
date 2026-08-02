import axios from "axios";
import { createStore } from "vuex";
import useNotLoginedRedirect from "./composables/certification/useNotLoginedRedirect";
import userSessionStorage from "./utils/userSessionStorage";

// 同一データを取得する複数コンポーネントが同時にdispatchした際、
// 進行中のリクエストがあれば新規にHTTPリクエストを発行せず、その完了を待つだけにする。
// (例: SelectMenu.vueとEditableMenuTable.vueが同じonMountedタイミングで
//  同じactionを呼ぶことによる二重リクエストを防ぐ)
let loginUserRequest: Promise<void> | null = null;
let latestRecordStateRequest: Promise<void> | null = null;
let loginStateRequest: Promise<void> | null = null;
// SelectMenu.vueの体重入力は@blurで発火し、直後のメニュー選択クリックで即座に画面遷移するため、
// この保存リクエストの完了を待たずにgetLatestRecordStateが呼ばれるレースが起こり得る。
// 進行中であればgetLatestRecordStateがこれを待つことで、無効化(invalidate)前の古いキャッシュを
// 遷移先が読んでしまう(体重が反映されない)のを防ぐ。
let weightUpdateRequest: Promise<void> | null = null;

export default createStore({
    state: {
        user: [],
        isLogined: false,
        day: "",
        latestRecordState: "",
        latestRecordMenus: "",
        // 直近取得済みかどうか。trueの間はgetLatestRecordStateが再フェッチをスキップする。
        // record/create成功時にfalseへ戻すことで、最新レコードが変わったタイミングでのみ再取得する。
        latestRecordStateFetched: false,
        recorded_at: "",
        compGetData: false,
        dispAlertModal: false,
        // ログイン状態の確認(/api/users)が一度でも完了したか。
        // Header.vue等が独自に/api/usersを呼び直さず、この完了を待つだけで済むようにする
        authChecked: false,
    },
    getters: {
        isLogined: (state) => state.isLogined,
        loginUser: (state) => state.user,
        selectedDay: (state) => state.day,
        latestRecord: (state) => state.latestRecordState,
        latestMenus: (state) => state.latestRecordMenus,
        getRecordedAt: (state) => state.recorded_at,
        compGetData: (state) => state.compGetData,
        dispAlertModal: (state) => state.dispAlertModal,
        authChecked: (state) => state.authChecked,
    },
    mutations: {
        LoginState(state) {
            // ログイン状態
            state.isLogined = true;
        },
        setIsLogined(state, value) {
            state.isLogined = value;
        },
        setAuthChecked(state, value) {
            state.authChecked = value;
        },
        LogoutState(state) {
            // ログアウト状態
            state.isLogined = false;
        },
        selectedDay(state, day) {
            state.day = day;
        },
        loginUser(state, user) {
            state.user = user;
        },
        latestRecordState(state, latestRecordState) {
            state.latestRecordState = latestRecordState;
        },
        // record/create成功時に呼び、次回のgetLatestRecordStateで再フェッチさせる
        invalidateLatestRecordState(state) {
            state.latestRecordStateFetched = false;
        },
        setRecordedAt(state, recordedAt) {
            state.recorded_at = recordedAt;
        },
        compGetData(state, status) {
            state.compGetData = status;
        },
        dispAlertModal(state, status) {
            state.dispAlertModal = status;
        },
    },
    actions: {
        async loginState({ state }) {
            // App.vueとHeader.vueが同時にmountして二重発行するのを防ぐため、
            // 進行中のリクエストがあればそれを待つだけにする(getLoginUserと同様のパターン)
            if (loginStateRequest) {
                await loginStateRequest;
                return;
            }
            const { setSessionLoginUser } = userSessionStorage();
            loginStateRequest = axios
                .get("/api/users")
                .then((res) => {
                    // ログイン状態取得
                    state.isLogined = true;
                    state.dispAlertModal = false;
                    // ログインしているユーザー情報取得
                    state.user = res.data;
                    setSessionLoginUser(res.data);
                })
                .catch((err) => {
                    sessionStorage.clear();
                    // ログイン状態取得
                    state.isLogined = false;
                    const { dispAlert } = useNotLoginedRedirect(err);
                    if (dispAlert.value) {
                        state.dispAlertModal = true;
                    }
                })
                .finally(() => {
                    loginStateRequest = null;
                    state.authChecked = true;
                });
            await loginStateRequest;
        },

        async getLoginUser({ state }) {
            if (loginUserRequest) {
                await loginUserRequest;
                return;
            }
            const { setSessionLoginUser } = userSessionStorage();
            loginUserRequest = axios
                .get("/api/users")
                .then((res) => {
                    state.dispAlertModal = false;
                    // ログインしているユーザー情報取得
                    setSessionLoginUser(res.data);
                    state.user = res.data;
                })
                .catch((err) => {
                    sessionStorage.clear();
                    // ログインしていない状態だとホーム画面へリダイレクト
                    const { dispAlert } = useNotLoginedRedirect(err);
                    if (dispAlert.value) {
                        state.dispAlertModal = true;
                    }
                })
                .finally(() => {
                    loginUserRequest = null;
                });
            await loginUserRequest;
        },

        async getLatestRecordState({ state }) {
            // 進行中の体重更新(updateWeight)があれば、そのinvalidate反映を待ってから
            // 再フェッチ要否を判定する。待たずに判定すると、体重保存の完了(invalidate)前に
            // 古いキャッシュのままここを通過してしまい、直後に遷移した画面へ更新前の体重が
            // 表示され続けるレースが起きる。
            if (weightUpdateRequest) {
                await weightUpdateRequest;
            }
            // 既に取得済み(かつrecord/create成功以降まだ無効化されていない)なら再フェッチしない。
            // SelectMenu.vueとEditableMenuTable.vueなど同一画面の複数コンポーネントが
            // それぞれ呼び出しても、実際のHTTPリクエストは画面遷移につき1回で済む。
            if (state.latestRecordStateFetched) {
                return;
            }
            if (latestRecordStateRequest) {
                await latestRecordStateRequest;
                return;
            }
            latestRecordStateRequest = axios
                .get("/api/record")
                .then((res) => {
                    state.dispAlertModal = false;
                    state.latestRecordState = res.data.latestRecord;
                    state.latestRecordMenus = res.data.latestRecord;
                    state.latestRecordStateFetched = true;
                })
                .catch((err) => {
                    // ログインしていない状態だとホーム画面へリダイレクト
                    const { dispAlert } = useNotLoginedRedirect(err);
                    if (dispAlert.value) {
                        state.dispAlertModal = true;
                    }
                })
                .finally(() => {
                    latestRecordStateRequest = null;
                });
            await latestRecordStateRequest;
        },

        // SelectMenu.vueの体重入力欄(@blur)から呼ばれる。呼び出し側は結果を待たずに
        // 画面遷移することがあるため、進行中リクエストをモジュール変数weightUpdateRequestに
        // 保持し、getLatestRecordStateがそれを待てるようにしている。
        async updateWeight({ state }, payload: { user_id: number; recording_day: string; weight: string }) {
            weightUpdateRequest = axios
                .post("/api/record/edit", payload)
                .then(() => {
                    // bodyWeight/updated_atが変わり、キャッシュされたlatestRecordが古くなるため無効化する
                    state.latestRecordStateFetched = false;
                })
                .catch(() => {})
                .finally(() => {
                    weightUpdateRequest = null;
                });
            await weightUpdateRequest;
        },
    },
});

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
    },
    mutations: {
        LoginState(state) {
            // ログイン状態
            state.isLogined = true;
        },
        setIsLogined(state, value) {
            state.isLogined = value;
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
            const {
                getSessionLoginUser,
                setSessionLoginUser,
                removeSessionLoginUser,
            } = userSessionStorage();
            await axios
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
                    if ((dispAlert.value = true)) {
                        state.dispAlertModal = true;
                    }
                });
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
                    if ((dispAlert.value = true)) {
                        state.dispAlertModal = true;
                    }
                })
                .finally(() => {
                    loginUserRequest = null;
                });
            await loginUserRequest;
        },

        async getLatestRecordState({ state }) {
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
                    if ((dispAlert.value = true)) {
                        state.dispAlertModal = true;
                    }
                })
                .finally(() => {
                    latestRecordStateRequest = null;
                });
            await latestRecordStateRequest;
        },
    },
});

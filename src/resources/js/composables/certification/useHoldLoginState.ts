import { ref, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import userSessionStorage from "../../utils/userSessionStorage";

export default function useHoldLoginState() {
    const router = useRouter();
    const store = useStore();
    const isLogined = ref<boolean>(false);
    const { getSessionLoginUser } = userSessionStorage();

    //ログイン状態保持するため
    const holdLoginState = async () => {
        const sessionLoginUser = getSessionLoginUser();
        if (sessionLoginUser) {
            isLogined.value = true;
            store.commit("setIsLogined", true);
            store.commit("setAuthChecked", true);
            return;
        } else {
            // 非同期処理呼び出しのため async await
            // loginStateアクション自体が/api/usersを取得しisLoginedを更新するため、
            // ここで別途同じAPIを呼び直す必要はない
            await store.dispatch("loginState");
            // nextTickは非同期処理完了後に呼び出されるのでisLoginedを取得できる
            nextTick(() => {
                isLogined.value = store.getters.isLogined;
            });
        }
    };

    return { holdLoginState, isLogined };
}

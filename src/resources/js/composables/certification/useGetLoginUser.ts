import { ref, nextTick, reactive } from "vue";
import { useStore } from "vuex";

type LoginUser = {
    created_at: string;
    email: string;
    email_verified_at: null;
    id: number;
    name: string;
    updated_at: string;
    is_admin: boolean;
};

export default function useGetLoginUser() {
    const store = useStore();
    //loginUserのオブジェクトのそれぞれのキーのデフォルト値を設定
    let loginUser = ref<LoginUser>({
        created_at: "",
        email: "",
        email_verified_at: null,
        id: 0,
        name: "",
        updated_at: "",
        is_admin: false,
    });

    // async await を使わないとDOM生成後のonMountedのタイミングでも早すぎてユーザ情報を取得できない
    const getLoginUser = async () => {
        await store.dispatch("getLoginUser");
        // ログインしているユーザ情報取得
        // nextTickは非同期処理完了後に呼び出されるのでloginUserを取得できる
        nextTick(() => {
            loginUser.value = store.getters.loginUser;
        });
    };

    return { loginUser, getLoginUser };
}

import { ref } from "vue";
import router from "../../router/index";

export default function useNotLoginedRedirect(err) {
    const dispAlert = ref<boolean>(false);
    sessionStorage.clear();
    if (
        err.response.data.message === "Unauthenticated." &&
        router.currentRoute.value.meta.requiresAuth === true
    ) {
        // router.push("/");
        // alert("画面表示するにはログインしてください。");
        dispAlert.value = true;
    }

    return { dispAlert };
}

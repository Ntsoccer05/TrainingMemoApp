import { LoginUser } from "../types/loginUser";

export default function userSessionStorage() {
    const getSessionLoginUser = () =>
        JSON.parse(sessionStorage.getItem("trainingMemo_user"));
    const setSessionLoginUser = (user: LoginUser) =>
        sessionStorage.setItem("trainingMemo_user", JSON.stringify(user));
    const removeSessionLoginUser = () =>
        sessionStorage.removeItem("trainingMemo_user");

    return { getSessionLoginUser, setSessionLoginUser, removeSessionLoginUser };
}

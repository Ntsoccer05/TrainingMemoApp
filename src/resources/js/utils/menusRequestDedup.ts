import axios, { AxiosResponse } from "axios";

// SelectMenu.vueとEditableMenuTable.vue(親子)が同時にonMountedで
// GET /api/menus を呼ぶと、sessionStorageのキャッシュがまだ書き込まれる前に
// 両方が同時にリクエストを発行してしまう。
// 進行中のリクエストがあれば新規に発行せず、同じPromiseを共有することで
// 二重リクエストを防ぐ(呼び出し元ごとの.then()でのデータ加工はそれぞれ独立して行える)。
let inFlightRequest: Promise<AxiosResponse<any>> | null = null;

export const fetchMenusOnce = (userId: number): Promise<AxiosResponse<any>> => {
    if (inFlightRequest) {
        return inFlightRequest;
    }
    inFlightRequest = axios
        .get("/api/menus", {
            params: {
                user_id: userId,
            },
        })
        .finally(() => {
            inFlightRequest = null;
        });
    return inFlightRequest;
};

export default fetchMenusOnce;

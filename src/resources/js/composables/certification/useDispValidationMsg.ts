// 合成関数では必ず変数名の頭にに「use」を付ける

import { ComputedRef, computed } from "vue";// エラーメッセージのレイアウト
import { DispErrorMsg, Errors } from "../../types/certification";

// エラーメッセージのレイアウト指定（エラーメッセージがある場合のみ表示）
// hasMsg：エラーメッセージが存在するかどうか
export default function useDispValidationMsg(hasMsg:DispErrorMsg){
    // ユーザ名のバリデーションエラー
    const dispNameErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.name ? "text-red-500 text-sm italic mt-1 mb-2" : "hidden";
    });
    
    // メールアドレスのバリデーションエラー
    const dispEmailErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.email ? "text-red-500 text-sm italic mt-1 mb-2" : "hidden";
    });

    // パスワードのバリデーションエラー
    const dispPassErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.password ? "text-red-500 text-sm italic mt-1 mb-2" : "hidden";
    });

    return {dispNameErrMsg, dispEmailErrMsg, dispPassErrMsg};
}
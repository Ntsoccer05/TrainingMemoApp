// 合成関数では必ず変数名の頭にに「use」を付ける

import { ComputedRef, computed } from "vue";// エラーメッセージのレイアウト
import { DispErrorMsg, Errors } from "../../types/inquiry";

// エラーメッセージのレイアウト指定（エラーメッセージがある場合のみ表示）
// hasMsg：エラーメッセージが存在するかどうか
export default function useDispValidationMsg(hasMsg:DispErrorMsg){
    // メールアドレスのバリデーションエラー
    const dispEmailErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.email ? "absolute text-red-500 text-sm italic mt-1 mb-2" : "hidden";
    });

    // お問い合わせ内容のバリデーションエラー
    const dispContentErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.content ? "absolute text-red-500 text-sm italic mt-1 mb-2" : "hidden";
    });

    return {dispEmailErrMsg, dispContentErrMsg};
}
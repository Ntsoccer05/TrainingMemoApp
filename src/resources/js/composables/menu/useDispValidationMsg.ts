import { ComputedRef, computed } from "vue";
import { DispErrorMsg } from "../../types/trainingMenu";

// エラーメッセージのレイアウト指定（エラーメッセージがある場合のみ表示）
// hasMsg：エラーメッセージが存在するかどうか
export default function useDispValidationMsg(hasMsg: DispErrorMsg){
    // 部位のバリデーションエラー
    const dispCategoryErrMsg:ComputedRef<string> = computed(() => {
        return hasMsg.category_content ? "col-start-2 justify-self-start text-red-500 font-bold text-sm italic mt-1" : "hidden";
    });
    
    // 種目のバリデーションエラー
    const dispMenuErrMsg: ComputedRef<string> = computed(() => {
        return hasMsg.menu ? "col-start-2 justify-self-start text-red-500 font-bold text-sm italic mt-1" : "hidden";
    });

    return {dispCategoryErrMsg, dispMenuErrMsg};
}
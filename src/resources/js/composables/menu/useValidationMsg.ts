// 合成関数では必ず変数名の頭にに「use」を付ける

import { DispErrorMsg, Errors } from "../../types/trainingMenu";
// エラーメッセージがあれば表示させる判定・エラーメッセージをdataに格納
// user_id：バリデーションメッセージ一覧
// category_content：エラーメッセージの格納場所
// menu：エラーメッセージを表示させるかどうか
export default function useValidationMsg(msgs:Errors, data:Errors, hasMsg:DispErrorMsg){
    // 部位のバリデーションエラー
    if (msgs.hasOwnProperty('menu')) {
        data.menu = msgs.menu
        hasMsg.menu = true;
    } else {
        data.menu = undefined;
        hasMsg.menu = false;
    }
    // 種目のバリデーションエラー
    if (msgs.hasOwnProperty('category_content')) {
        data.category_content = msgs.category_content;
        hasMsg.category_content = true;
    } else {
        data.category_content = undefined;
        hasMsg.category_content = false;
    }
}
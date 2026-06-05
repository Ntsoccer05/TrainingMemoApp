// 合成関数では必ず変数名の頭にに「use」を付ける

// エラーメッセージがあれば表示させる判定・エラーメッセージをdataに格納
// msgs：バリデーションメッセージ一覧
// data：エラーメッセージの格納場所
// hasMsg：エラーメッセージを表示させるかどうか
import { DispErrorMsg, Errors } from "../../types/certification";

export default function useValidationMsg(msgs:Errors, data:Errors, hasMsg:DispErrorMsg){
    // ユーザ名のバリデーションエラー
    if(msgs){
        if (msgs.hasOwnProperty('name')) {
            data.name = msgs.name
            hasMsg.name = true;
        } else {
            data.name = [];
            hasMsg.name = false;
        }
        // メールアドレスのバリデーションエラー
        if (msgs.hasOwnProperty('email')) {
            data.email = msgs.email;
            hasMsg.email = true;
        } else {
            data.email = [];
            hasMsg.email = false;
        }
        // パスワードのバリデーションエラー
        if (msgs.hasOwnProperty('password')) {
            data.password = msgs.password;
            hasMsg.password = true;
        } else {
            data.password = [];
            hasMsg.password = false;
        }
    }
}
import { ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { TgtRecordContent } from "../../types/record";

export default function useGetTgtRecordContents(){
    const tgtRecord = ref<TgtRecordContent[]>([])
    // 既にデータが存在するか
    const hasTgtRecord = ref<boolean>(false)

    //既にデータが存在していれば取得
    const getTgtRecords = async(user_id:number, category_id:string, menu_id:string, record_state_id:string)=>{
        await axios.get("/api/recordContent", {
            // get時にパラメータを渡す際はparamsで指定が必要
            params:{
                // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                user_id,
                category_id,
                menu_id,
                record_state_id,
            }
        }).then((res) =>{
            if(res.data.tgtRecords){
                tgtRecord.value = res.data.tgtRecords
                hasTgtRecord.value = true
            }else{
                hasTgtRecord.value = false
            }
            // hasTgtRecord.value = true
        }).catch((err)=>{
            useNotLoginedRedirect(err);
        })
    }

    return{tgtRecord, hasTgtRecord, getTgtRecords}
}
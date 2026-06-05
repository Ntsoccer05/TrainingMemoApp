import { ref } from "vue";
import axios from "axios";
import { HistoryMenu, HistoryRecord } from "../../types/record";

export default function useGetHistoryRecordContent(){
    const historyRecords = ref<HistoryRecord[][]>([])
    const historyMenus = ref<HistoryMenu[]>([])
    // 既にデータが存在するか
    const hasHistoryRecord = ref<boolean>(false)

    //既にデータが存在していれば取得
    const getHistoryRecords = async(user_id:number, category_id:string, menu_id:string, record_state_id:string, recorded_at:string)=>{
        await axios.get("/api/recordContent/show", {
            // get時にパラメータを渡す際はparamsで指定が必要
            params:{
                // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                user_id,
                category_id,
                menu_id,
                record_state_id,
                recorded_at,
            }
        }).then((res) =>{
            if(res.data.historyTgtMenus){
                historyRecords.value = res.data.historyTgtRecords
                historyMenus.value = res.data.historyTgtMenus
                hasHistoryRecord.value = true
            }else{
                hasHistoryRecord.value = false
            }
        }).catch((err)=>{
        })
    }

    return{historyRecords, historyMenus, hasHistoryRecord, getHistoryRecords}
}
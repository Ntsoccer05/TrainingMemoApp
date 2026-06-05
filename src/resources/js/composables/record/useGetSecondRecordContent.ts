import { ref } from "vue";
import axios from "axios";
import { LatestRecord, HistoryRecord } from "../../types/record";

export default function useGetSecondRecordContent(fillBeforeRecordSession) {
    const secondRecord = ref<HistoryRecord[]>(
        fillBeforeRecordSession?.record || []
    );
    const secondRecordState = ref<LatestRecord>(undefined);
    // ２番目に新しい記録が存在するか
    const hasSecondRecord = ref<boolean>(!!fillBeforeRecordSession);

    //２番目に新しい記録を取得する
    const getSecondRecord = async (
        user_id: number,
        category_id: string,
        menu_id: string,
        record_state_id: string,
        recorded_at: string,
        thisTotalSet: string
    ) => {
        await axios
            .get("/api/recordMenu", {
                // get時にパラメータを渡す際はparamsで指定が必要
                params: {
                    // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                    user_id,
                    category_id,
                    menu_id,
                    record_state_id,
                    recorded_at,
                    thisTotalSet,
                },
            })
            .then((res) => {
                if (res.data.secondRecords) {
                    secondRecord.value = res.data.secondRecords;
                    secondRecordState.value =
                        res.data.secondRecordState.record_state;
                    hasSecondRecord.value = true;
                } else {
                    hasSecondRecord.value = false;
                }
            });
    };

    return {
        secondRecord,
        secondRecordState,
        hasSecondRecord,
        getSecondRecord,
    };
}

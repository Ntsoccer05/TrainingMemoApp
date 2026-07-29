import { ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { TgtRecordContent, HistoryRecord, LatestRecord } from "../../types/record";

export default function useGetRecordContent() {
    const tgtRecords = ref<TgtRecordContent[]>([]);
    const hasTgtRecord = ref<boolean>(false);
    const previousRecords = ref<HistoryRecord[]>([]);
    const previousRecordState = ref<LatestRecord>(undefined);
    const hasPreviousRecord = ref<boolean>(false);

    const getRecordContent = async (
        user_id: number,
        category_id: string,
        menu_id: string,
        record_state_id: string,
        recorded_at: string
    ): Promise<boolean> => {
        return await axios
            .get("/api/recordContent", {
                params: {
                    user_id,
                    category_id,
                    menu_id,
                    record_state_id,
                    recorded_at,
                },
            })
            .then((res) => {
                if (res.data.tgtRecords) {
                    tgtRecords.value = res.data.tgtRecords;
                    hasTgtRecord.value = true;
                } else {
                    hasTgtRecord.value = false;
                }
                if (res.data.previousRecords) {
                    previousRecords.value = res.data.previousRecords;
                    previousRecordState.value = res.data.previousRecordState;
                    hasPreviousRecord.value = true;
                } else {
                    hasPreviousRecord.value = false;
                }
                return true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
                return false;
            });
    };

    return {
        tgtRecords,
        hasTgtRecord,
        previousRecords,
        previousRecordState,
        hasPreviousRecord,
        getRecordContent,
    };
}

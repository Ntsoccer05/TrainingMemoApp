import { ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { CategorySummary, dispRecordContents } from "../../types/recordRanking";
import { extractCategorySummaries } from "../../utils/recordRanking";

export default function useGetRecords() {
    const rankingContents = ref<dispRecordContents>([]);
    const compGetData = ref<boolean>(false);
    const categoryContents = ref<CategorySummary[]>([]);

    const getRecords = async (user_id: number) => {
        await axios
            .get("/api/recordRanking/user", {
                // get時にパラメータを渡す際はparamsで指定が必要
                params: {
                    // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                    user_id,
                },
            })
            .then((res) => {
                rankingContents.value = res.data.dispContents;
                categoryContents.value = extractCategorySummaries(rankingContents.value);
                compGetData.value = true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
            });
    };

    return { rankingContents, compGetData, categoryContents, getRecords };
}

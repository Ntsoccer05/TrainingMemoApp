import { reactive, ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { dispRecordContents } from "../../types/recordRanking";

export default function useGetRecords() {
    const rankingContents = ref<dispRecordContents>([]);
    const compGetData = ref<boolean>(false);
    const categoryContents = ref<string[]>([]);

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
                for (let i = 0; i < rankingContents.value.length; i++) {
                    if (i > 0) {
                        if (
                            rankingContents.value[i - 1].category.id !==
                            rankingContents.value[i].category.id
                        ) {
                            categoryContents.value.push(
                                rankingContents.value[i].category.content
                            );
                        }
                    } else {
                        categoryContents.value.push(
                            rankingContents.value[i].category.content
                        );
                    }
                }
                compGetData.value = true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
            });
    };

    return { rankingContents, compGetData, categoryContents, getRecords };
}

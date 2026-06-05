import { ref } from "vue";
import axios, { AxiosResponse } from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { DispRecords } from "../../types/record";

type Data = {
    records: Records[];
};

type Category = {
    category_id: number;
    category_content: string;
};
type Menu = {
    menu_id: number;
    menu_content: string;
};
type Record = {
    record_id: number;
    recorded_at: string;
};

// ?はオプション、ない場合を考慮
type Records = {
    menu?: Array<Menu>;
    category?: Array<Category>;
    recorded_at: Record;
};

export default function useGetRecords() {
    // 配列はArray<>もしくは、変数[]
    const records = ref<DispRecords[]>([]);
    const compGetData = ref<Boolean>(false);
    const isLoaded = ref<boolean>(false);

    const getRecords = async (user_id: Number, recorded_at: String = "") => {
        await axios
            .get("/api/recordContent", {
                // get時にパラメータを渡す際はparamsで指定が必要
                params: {
                    // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                    user_id,
                    recorded_at,
                },
            })
            .then((res: AxiosResponse<Data>) => {
                records.value = res.data.records;
                compGetData.value = true;
                isLoaded.value = true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
                isLoaded.value = true;
            });
    };

    return { records, compGetData, isLoaded, getRecords };
}

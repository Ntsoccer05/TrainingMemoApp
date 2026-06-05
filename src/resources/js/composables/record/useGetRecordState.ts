import { ref,nextTick } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import { LatestRecord } from "../../types/record";

export default function useGetRecordState(){
    const router = useRouter();
    const store = useStore();
    const latestRecord = ref<LatestRecord>(undefined);
    const compGetData = ref<boolean>(false);

    // async await を使わないとDOM生成後のonMountedのタイミングでも早すぎてユーザ情報を取得できない
    const getLatestRecordState = async ()=>{
        await store.dispatch("getLatestRecordState");
        // 選択したレコードの日付、ID情報取得
        // nextTickは非同期処理完了後に呼び出されるのでlatestRecordを取得できる
        nextTick(()=>{
            compGetData.value = true
            latestRecord.value = store.getters.latestRecord;
        })
    }

    return {latestRecord, compGetData, getLatestRecordState};
}
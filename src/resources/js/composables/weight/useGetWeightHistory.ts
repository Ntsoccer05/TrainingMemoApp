import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord } from "../../types/weight";

export default function useGetWeightHistory() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);

  const getWeightHistory = async (from?: string, to?: string): Promise<void> => {
    await axios
      .get("/api/weight", {
        params: from && to ? { from, to } : {},
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
      });
  };

  return { weightRecords, targetWeight, getWeightHistory };
}

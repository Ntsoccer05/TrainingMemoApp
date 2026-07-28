import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord } from "../../types/weight";

export default function useGetWeightHistory() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);
  const targetWeightDate: Ref<string | null> = ref(null);

  const getWeightHistory = async (from?: string, to?: string): Promise<void> => {
    await axios
      .get("/api/weight", {
        params: from && to ? { from, to } : {},
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
        targetWeightDate.value = res.data.target_weight_date;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
        targetWeightDate.value = null;
      });
  };

  return { weightRecords, targetWeight, targetWeightDate, getWeightHistory };
}

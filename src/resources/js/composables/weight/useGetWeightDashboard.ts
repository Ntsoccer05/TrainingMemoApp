import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord, WeightTag, TagStatistic } from "../../types/weight";

export default function useGetWeightDashboard() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);
  const targetWeightDate: Ref<string | null> = ref(null);
  const weightTags: Ref<WeightTag[]> = ref([]);
  const tagStats: Ref<TagStatistic[]> = ref([]);
  const selectedDateRecord: Ref<WeightRecord | null> = ref(null);

  const getWeightDashboard = async (
    from: string,
    to: string,
    selectedDate: string
  ): Promise<void> => {
    await axios
      .get("/api/weight/dashboard", {
        params: { from, to, selected_date: selectedDate },
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
        targetWeightDate.value = res.data.target_weight_date;
        weightTags.value = res.data.tags;
        tagStats.value = res.data.tag_stats;
        selectedDateRecord.value = res.data.selected_date_record;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
        targetWeightDate.value = null;
        weightTags.value = [];
        tagStats.value = [];
        selectedDateRecord.value = null;
      });
  };

  return {
    weightRecords,
    targetWeight,
    targetWeightDate,
    weightTags,
    tagStats,
    selectedDateRecord,
    getWeightDashboard,
  };
}

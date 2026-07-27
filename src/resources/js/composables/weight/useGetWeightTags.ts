import { ref, Ref } from "vue";
import axios from "axios";
import { WeightTag } from "../../types/weight";

export default function useGetWeightTags() {
  const weightTags: Ref<WeightTag[]> = ref([]);

  const getWeightTags = async (): Promise<void> => {
    await axios
      .get("/api/weight/tags")
      .then((res) => {
        weightTags.value = res.data.tags;
      })
      .catch(() => {
        weightTags.value = [];
      });
  };

  return { weightTags, getWeightTags };
}

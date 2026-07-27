import { ref, Ref } from "vue";
import axios from "axios";
import { WeightTag } from "../../types/weight";

export default function usePostWeightTag() {
  const isSaving: Ref<boolean> = ref(false);

  const postWeightTag = async (content: string): Promise<WeightTag | null> => {
    isSaving.value = true;
    return await axios
      .post("/api/weight/tags", { content })
      .then((res) => res.data.tag as WeightTag)
      .catch(() => null)
      .finally(() => {
        isSaving.value = false;
      });
  };

  return { isSaving, postWeightTag };
}

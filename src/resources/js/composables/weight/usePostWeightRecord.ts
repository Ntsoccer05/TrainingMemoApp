import { ref, Ref } from "vue";
import axios from "axios";

export default function usePostWeightRecord() {
  const isSaving: Ref<boolean> = ref(false);
  const hasError: Ref<boolean> = ref(false);

  const postWeightRecord = async (
    recordedAt: string,
    bodyWeight: number | null,
    memo: string | null,
    tagIds: number[]
  ): Promise<void> => {
    isSaving.value = true;
    hasError.value = false;
    await axios
      .post("/api/weight", {
        recorded_at: recordedAt,
        body_weight: bodyWeight,
        memo: memo,
        tag_ids: tagIds,
      })
      .catch(() => {
        hasError.value = true;
      })
      .finally(() => {
        isSaving.value = false;
      });
  };

  return { isSaving, hasError, postWeightRecord };
}

import { ref, Ref } from "vue";
import axios from "axios";

export default function useDeleteWeightTag() {
  const isDeleting: Ref<boolean> = ref(false);

  const deleteWeightTag = async (tagId: number): Promise<void> => {
    isDeleting.value = true;
    await axios.delete(`/api/weight/tags/${tagId}`).catch(() => {});
    isDeleting.value = false;
  };

  return { isDeleting, deleteWeightTag };
}

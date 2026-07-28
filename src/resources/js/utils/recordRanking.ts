import { CategorySummary, dispRecordContents } from "../types/recordRanking";

export const extractCategorySummaries = (
  contents: dispRecordContents
): CategorySummary[] => {
  const seenCategoryIds = new Set<number>();
  const summaries: CategorySummary[] = [];
  for (const content of contents) {
    const categoryId = content.category.id;
    if (!seenCategoryIds.has(categoryId)) {
      seenCategoryIds.add(categoryId);
      summaries.push({ id: categoryId, content: content.category.content });
    }
  }
  return summaries;
};

export const groupContentsByCategory = (
  contents: dispRecordContents
): Map<number, dispRecordContents> => {
  const map = new Map<number, dispRecordContents>();
  for (const content of contents) {
    const categoryId = content.category.id;
    const list = map.get(categoryId) ?? [];
    list.push(content);
    map.set(categoryId, list);
  }
  return map;
};

export const parseStoredClosedCategoryIds = (stored: string | null): number[] => {
  if (!stored) {
    return [];
  }
  const parsed = JSON.parse(stored);
  return Array.isArray(parsed) ? parsed : [];
};

export const toggleCategoryInClosedIds = (
  closedIds: number[],
  categoryId: number
): number[] => {
  if (closedIds.includes(categoryId)) {
    return closedIds.filter((id) => id !== categoryId);
  }
  return [...closedIds, categoryId];
};

export const toggleAllClosedCategoryIds = (
  closedIds: number[],
  allCategoryIds: number[]
): number[] => {
  return closedIds.length === 0 ? [...allCategoryIds] : [];
};

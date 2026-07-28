import { describe, it, expect } from "vitest";
import { dispRecordContents } from "../types/recordRanking";
import {
  extractCategorySummaries,
  groupContentsByCategory,
  parseStoredClosedCategoryIds,
  toggleCategoryInClosedIds,
  toggleAllClosedCategoryIds,
} from "./recordRanking";

const chestCategory = {
  id: 1,
  content: "胸",
  created_at: "2026-01-01",
  updated_at: "2026-01-01",
  user_id: 1,
};

const backCategory = {
  id: 2,
  content: "背中",
  created_at: "2026-01-01",
  updated_at: "2026-01-01",
  user_id: 1,
};

const benchPressMenu = {
  id: 10,
  content: "ベンチプレス",
  category: chestCategory,
  category_id: 1,
  oneSide: 0,
};

const inclinePressMenu = {
  id: 11,
  content: "インクラインプレス",
  category: chestCategory,
  category_id: 1,
  oneSide: 0,
};

const dumbbellRowMenu = {
  id: 20,
  content: "ダンベルロウ",
  category: backCategory,
  category_id: 2,
  oneSide: 1,
};

const contents: dispRecordContents = [
  { category: chestCategory, menu: benchPressMenu, emptyData: 0, bestWeight: 80 },
  { category: backCategory, menu: dumbbellRowMenu, emptyData: 0 },
  { category: chestCategory, menu: inclinePressMenu, emptyData: 1 },
];

describe("extractCategorySummaries", () => {
  it("最初に出現した順でカテゴリを重複なく抽出する", () => {
    expect(extractCategorySummaries(contents)).toEqual([
      { id: 1, content: "胸" },
      { id: 2, content: "背中" },
    ]);
  });

  it("同一カテゴリが隣接していなくても重複しない", () => {
    const nonAdjacent: dispRecordContents = [
      { category: chestCategory, menu: benchPressMenu, emptyData: 0 },
      { category: backCategory, menu: dumbbellRowMenu, emptyData: 0 },
      { category: chestCategory, menu: inclinePressMenu, emptyData: 1 },
    ];
    expect(extractCategorySummaries(nonAdjacent)).toEqual([
      { id: 1, content: "胸" },
      { id: 2, content: "背中" },
    ]);
  });
});

describe("groupContentsByCategory", () => {
  it("カテゴリIDごとに種目をグルーピングする", () => {
    const grouped = groupContentsByCategory(contents);
    expect(grouped.get(1)).toHaveLength(2);
    expect(grouped.get(2)).toHaveLength(1);
    expect(grouped.get(1)?.map((c) => c.menu.content)).toEqual([
      "ベンチプレス",
      "インクラインプレス",
    ]);
  });

  it("存在しないカテゴリIDにはundefinedを返す", () => {
    const grouped = groupContentsByCategory(contents);
    expect(grouped.get(999)).toBeUndefined();
  });
});

describe("parseStoredClosedCategoryIds", () => {
  it("nullの場合は空配列を返す", () => {
    expect(parseStoredClosedCategoryIds(null)).toEqual([]);
  });

  it("JSON配列文字列をパースして返す", () => {
    expect(parseStoredClosedCategoryIds("[1,2,3]")).toEqual([1, 2, 3]);
  });

  it("配列でないJSONの場合は空配列を返す", () => {
    expect(parseStoredClosedCategoryIds('{"a":1}')).toEqual([]);
  });
});

describe("toggleCategoryInClosedIds", () => {
  it("含まれていないIDを追加する", () => {
    expect(toggleCategoryInClosedIds([1, 2], 3)).toEqual([1, 2, 3]);
  });

  it("含まれているIDを除去する", () => {
    expect(toggleCategoryInClosedIds([1, 2, 3], 2)).toEqual([1, 3]);
  });
});

describe("toggleAllClosedCategoryIds", () => {
  it("閉じているカテゴリが1つもない場合は全カテゴリIDを返す(すべて閉じる)", () => {
    expect(toggleAllClosedCategoryIds([], [1, 2, 3])).toEqual([1, 2, 3]);
  });

  it("閉じているカテゴリが1つでもある場合は空配列を返す(すべて開く)", () => {
    expect(toggleAllClosedCategoryIds([2], [1, 2, 3])).toEqual([]);
  });
});

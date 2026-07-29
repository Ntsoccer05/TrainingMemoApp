import { describe, it, expect } from "vitest";
import resolveMemoAdjustIndexes from "./resolveMemoAdjustIndexes";

describe("resolveMemoAdjustIndexes", () => {
  it("メモが入っているセットのインデックスのみを返す", () => {
    const contents = [
      { set: 1, memo: "長いメモ" },
      { set: 2, memo: "" },
      { set: 3, memo: null },
      { set: 4, memo: "別のメモ" },
    ];

    const result = resolveMemoAdjustIndexes(contents);

    expect(result).toEqual([0, 3]);
  });

  it("どのセットにもメモが無い場合、空配列を返す", () => {
    const contents = [
      { set: 1, memo: "" },
      { set: 2, memo: null },
      { set: 3 },
    ];

    const result = resolveMemoAdjustIndexes(contents);

    expect(result).toEqual([]);
  });

  it("空配列を渡された場合、空配列を返す", () => {
    const result = resolveMemoAdjustIndexes([]);

    expect(result).toEqual([]);
  });
});

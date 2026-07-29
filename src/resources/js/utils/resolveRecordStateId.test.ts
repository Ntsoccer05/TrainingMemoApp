import { describe, it, expect } from "vitest";
import resolveRecordStateId from "./resolveRecordStateId";

describe("resolveRecordStateId", () => {
  it("選択した日付のRecordStateが存在する場合、そのIDを返す(最新のRecordStateのIDは無視する)", () => {
    const records = [
      { recorded_at: { record_id: 42, recorded_at: "2026-06-08" } },
    ];

    const result = resolveRecordStateId(records, 999);

    expect(result).toBe(42);
  });

  it("選択した日付にまだRecordStateが存在しない場合(record_idがnull)、フォールバックのIDを返す", () => {
    const records = [
      { recorded_at: { record_id: null, recorded_at: "2026-06-08" } },
    ];

    const result = resolveRecordStateId(records, 999);

    expect(result).toBe(999);
  });

  it("recordsが空配列の場合、フォールバックのIDを返す", () => {
    const result = resolveRecordStateId([], 999);

    expect(result).toBe(999);
  });
});

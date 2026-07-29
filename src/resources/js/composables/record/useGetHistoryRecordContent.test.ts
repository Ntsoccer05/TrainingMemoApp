import { describe, it, expect, vi, afterEach } from "vitest";
import axios from "axios";
import useGetHistoryRecordContent from "./useGetHistoryRecordContent";

vi.mock("axios");

afterEach(() => {
  vi.clearAllMocks();
});

describe("useGetHistoryRecordContent", () => {
  it("historyTgtMenusが空配列の場合はhasHistoryRecordがfalseになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: { historyTgtMenus: [], historyTgtRecords: null },
    });

    const { hasHistoryRecord, getHistoryRecords } = useGetHistoryRecordContent();
    await getHistoryRecords(1, "1", "1", "1", "2026-07-27");

    expect(hasHistoryRecord.value).toBe(false);
  });

  it("historyTgtMenusが1件以上ある場合はhasHistoryRecordがtrueになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: {
        historyTgtMenus: [
          {
            id: 1,
            user_id: 1,
            category_id: 1,
            menu_id: 1,
            record_state_id: 1,
            recorded_at: "2026-07-20",
            created_at: "2026-07-20",
            updated_at: "2026-07-20",
            record_state: { id: 1, bodyWeight: 70 },
          },
        ],
        historyTgtRecords: [[]],
      },
    });

    const { hasHistoryRecord, getHistoryRecords } = useGetHistoryRecordContent();
    await getHistoryRecords(1, "1", "1", "1", "2026-07-27");

    expect(hasHistoryRecord.value).toBe(true);
  });
});

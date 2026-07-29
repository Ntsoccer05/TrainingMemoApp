import { describe, it, expect, vi, afterEach } from "vitest";
import axios from "axios";
import useGetRecordContent from "./useGetRecordContent";

vi.mock("axios");
vi.mock("../certification/useNotLoginedRedirect", () => ({
  default: vi.fn(),
}));

afterEach(() => {
  vi.clearAllMocks();
});

describe("useGetRecordContent", () => {
  it("tgtRecordsとpreviousRecordsが両方存在する場合、それぞれhasフラグがtrueになりデータが反映される", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: {
        tgtRecords: [{ set: 1, weight: 60, rep: 10 }],
        previousRecordState: { id: 1, bodyWeight: 70 },
        previousRecords: [{ set: 1, weight: 55, rep: 10 }],
      },
    });

    const {
      hasTgtRecord,
      hasPreviousRecord,
      tgtRecords,
      previousRecords,
      previousRecordState,
      getRecordContent,
    } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(true);
    expect(hasPreviousRecord.value).toBe(true);
    expect(tgtRecords.value).toHaveLength(1);
    expect(previousRecords.value).toHaveLength(1);
    expect(previousRecordState.value).toEqual({ id: 1, bodyWeight: 70 });
  });

  it("tgtRecordsもpreviousRecordsも存在しない場合、両方のhasフラグがfalseになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: { tgtRecords: null, previousRecordState: null, previousRecords: null },
    });

    const { hasTgtRecord, hasPreviousRecord, getRecordContent } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(false);
    expect(hasPreviousRecord.value).toBe(false);
  });

  it("tgtRecordsのみ存在しpreviousRecordsが存在しない場合、hasTgtRecordのみtrueになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: {
        tgtRecords: [{ set: 1, weight: 60, rep: 10 }],
        previousRecordState: null,
        previousRecords: null,
      },
    });

    const { hasTgtRecord, hasPreviousRecord, getRecordContent } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(true);
    expect(hasPreviousRecord.value).toBe(false);
  });

  it("axios.getが成功した場合、trueを返す", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: { tgtRecords: null, previousRecordState: null, previousRecords: null },
    });

    const { getRecordContent } = useGetRecordContent();
    const result = await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(result).toBe(true);
  });

  it("axios.getが失敗した場合、falseを返し例外をthrowしない", async () => {
    vi.mocked(axios.get).mockRejectedValue(new Error("network error"));

    const { getRecordContent } = useGetRecordContent();
    const result = await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(result).toBe(false);
  });
});

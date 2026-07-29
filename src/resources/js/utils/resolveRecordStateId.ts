import { DispRecords } from "../types/record";

// 選択した日付のRecordStateのIDを解決する。
// 直接URLアクセス等でその日のRecordStateがまだ存在しない場合(record_idがnull)のみ、
// フォールバックのIDを使う。
export default function resolveRecordStateId(
    records: DispRecords[],
    fallbackId: number
): number {
    const recordId = records[0]?.recorded_at?.record_id;
    return recordId ?? fallbackId;
}

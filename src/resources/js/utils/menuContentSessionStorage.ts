import { LoginUser } from "../types/loginUser";

export default function userSessionStorage(
    categoryId,
    menuId,
    recordStateId = null
) {
    const menuContentKey = `menuContent_${categoryId}_${menuId}`;
    const fillBeforeRecordKey = `secondRecord_${categoryId}_${menuId}_${recordStateId}`;
    const historyRecordsKey = `historyRecords_${categoryId}_${menuId}_${recordStateId}`;
    // 日付(recordStateId)を問わず、部位+種目単位で保持する
    const complementContentsKey = `complementContents_${categoryId}_${menuId}`;

    const setMenuContentSession = (menuContent) =>
        sessionStorage.setItem(menuContentKey, JSON.stringify(menuContent));
    const getMenuContentSession = () =>
        JSON.parse(sessionStorage.getItem(menuContentKey));
    const removeMenuContentSession = () =>
        sessionStorage.removeItem(menuContentKey);

    const getFillBeforeRecordSession = () =>
        JSON.parse(sessionStorage.getItem(fillBeforeRecordKey));
    const setFillBeforeRecordSession = (bodyWeight, record) =>
        sessionStorage.setItem(
            fillBeforeRecordKey,
            JSON.stringify({ bodyWeight, record })
        );
    const removeFillBeforeRecordSession = () =>
        sessionStorage.removeItem(fillBeforeRecordKey);

    const getHistoryRecordSession = () =>
        JSON.parse(sessionStorage.getItem(historyRecordsKey));
    const setHistoryRecordSession = (
        historyRecords,
        historyMenus,
        hasHistoryRecord
    ) =>
        sessionStorage.setItem(
            historyRecordsKey,
            JSON.stringify({ historyRecords, historyMenus, hasHistoryRecord })
        );
    const removeHistoryRecordSession = () =>
        sessionStorage.removeItem(historyRecordsKey);

    // 「重量・回数を補完する」チェックボックスの状態を部位+種目単位で保持する
    const getComplementContentsSession = (): boolean =>
        sessionStorage.getItem(complementContentsKey) === "true";
    const setComplementContentsSession = (value: boolean) =>
        sessionStorage.setItem(complementContentsKey, String(value));

    return {
        setMenuContentSession,
        getMenuContentSession,
        removeMenuContentSession,
        getFillBeforeRecordSession,
        setFillBeforeRecordSession,
        removeFillBeforeRecordSession,
        getHistoryRecordSession,
        setHistoryRecordSession,
        removeHistoryRecordSession,
        getComplementContentsSession,
        setComplementContentsSession,
    };
}

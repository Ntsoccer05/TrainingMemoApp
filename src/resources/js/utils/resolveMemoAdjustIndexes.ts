// メモが入っているセットのインデックス一覧を返す。
// 高さ調整(adjustHeight)はDOM操作を伴い重いため、対象を事前に絞り込む。
export default function resolveMemoAdjustIndexes(
    contents: Array<{ memo?: string | null }>
): number[] {
    return contents
        .map((content, index) => (content.memo ? index : -1))
        .filter((index) => index !== -1);
}

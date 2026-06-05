export declare type dispRecordContents ={
    category?: Category,
    emptyData?: number,
    menu?: Menu,
    menuBestVolume?: RecordContents,
    bestWeight?: number,
    menuBestRightWeight?: number,
    menuBestRightVolume?: RecordContents,
    menuBestLeftWeight?: number,
    menuBestLeftVolume?: RecordContents,
}[]

type Category = {
    content: string,
    created_at: string,
    id: number,
    updated_at: string,
    user_id: number
};

type Menu = {
    category: Category,
    category_id: number,
    content: string,
    id: number,
    oneSide: number,
    record_contents?: RecordContents[]
};

type RecordContents ={
    category_id: number,
    created_at: string,
    id: number,
    left_rep?: number|null,
    left_volume?: number|null,
    left_weight?: number|null,
    memo?: string|null,
    menu_id: number,
    record_menu_id: number,
    record_state_id: number,
    rep?: number|null,
    right_rep?: number|null,
    right_volume?: number|null,
    right_weight?: number|null,
    set: number,
    updated_at: string,
    user_id: number,
    volume?: number|null,
    weight?: number|null,
}
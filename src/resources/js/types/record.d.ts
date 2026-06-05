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

export declare type Records = {
    category?: Category,
    menu?: Menu,
    recorded_at: RecordedAt
}

export declare type Categories = {
    content: string,
    menus?: Menu[],
    created_at: string,
    id: number,
    updated_at: string,
    user_id: number
}[]

export declare type Category = {
    content: string,
    created_at: string,
    id: number,
    updated_at: string,
    user_id: number
};

export declare type Menu = {
    category: Category,
    category_id: number,
    content: string,
    id: number,
    oneSide: number,
    record_contents?: RecordContents[]
};

export declare type HistoryMenu = {
    category_id: number,
    record_state_id: number,
    record_state: RecordState
    recorded_at: string,
    created_at: string,
    updated_at: string,
    id: number,
    user_id: number,
};

export declare type HistoryRecord = {
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
};

export declare type TgtRecordContent = {
    category_id: number,
    created_at: string,
    id: number,
    left_rep?: string|null,
    left_volume?: string|null,
    left_weight?: string|null,
    memo?: string|null,
    menu_id: string,
    record_menu_id: string,
    record_state_id: string,
    rep?: string|null,
    right_rep?: string|null,
    right_volume?: string|null,
    right_weight?: string|null,
    set: number,
    updated_at: string,
    user_id: number,
    volume?: string|null,
    weight?: string|null,
};

export declare type LatestRecord = {
    id: number,
    user_id: number,
    bodyWeight: number,
    recorded_at: string,
    created_at: string,
    updated_at: string,
};

type RecordState={
    id: number,
    bodyWeight: number
}

type RecordedAt = {
    record_id: number,
    recorded_at : string
}

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

type DispCategory = {
    category_id: number;
    category_content: string;
}
type DispMenu = {
    menu_id: number;
    menu_content: string;
}

// ?はオプション、ない場合を考慮
export declare type DispRecords = {
    menu?: Array<DispMenu>;
    category?: Array<DispCategory>;
    recorded_at: RecordedAt;
};
export declare type WeightTag = {
    id: number,
    content: string,
};

export declare type WeightRecord = {
    id: number,
    recorded_at: string,
    bodyWeight: number | null,
    weight_memo: string | null,
    weight_tags: WeightTag[],
};

export declare type TagStatistic = {
    tag: string,
    average_diff: number,
    sample_count: number,
};

export declare type WeightDashboardResponse = {
    status_code: number,
    records: WeightRecord[],
    target_weight: number | null,
    target_weight_date: string | null,
    tags: WeightTag[],
    tag_stats: TagStatistic[],
    selected_date_record: WeightRecord | null,
};

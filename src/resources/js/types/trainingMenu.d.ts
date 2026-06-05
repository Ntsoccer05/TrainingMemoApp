export declare type DispErrorMsg ={
    category_content: boolean;
    menu: boolean;
}

export declare type Errors = {
    category_content: Array<string>;
    menu: Array<string>;
};

export declare type Post = {
    user_id: number,
    category_content: boolean | string,
    category_id: string,
    menu: string,
    oneSide: boolean
};

export declare type Category = {
    content?: string,
    created_at?: string,
    id?: number,
    menus?: Menu
    updated_at?: string,
    user_id?: number
}[];

type Menu = {
    category_id?: number,
    content?: string,
    id?: number,
    oneSide?: number,
    updated_at?: string,
    user_id?: number
}[];

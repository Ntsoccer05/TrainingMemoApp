export declare type DispErrorMsg = {
    name?: boolean;
    email: boolean;
    password?: boolean;
};

export declare type Errors = {
    name?: Array<string>;
    email: Array<string>;
    password?: Array<string>;
};

export declare type Form = {
    email: string;
    token?: string;
    password?: string;
    password_confirmation?: string;
};

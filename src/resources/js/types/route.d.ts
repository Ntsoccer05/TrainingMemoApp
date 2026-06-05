export declare type routes= {
    path: string;
    name: string;
    component: () => Promise<typeof import("../views/home.vue")>;
    meta: {
        requiresAuth: boolean;
    };
    redirect?: string;
}[]
import { RouteRecordRaw, createRouter, createWebHistory } from "vue-router";
//遅延ローディング
const Login = () => import("../views/certification/loginPage.vue");
const Register = () => import("../views/certification/registerPage.vue");
const RedirectAuthGoogle = () =>
    import("../components/certification/RedirectAuthGoogle.vue");
const googleRegister = () =>
    import("../views/certification/googleRegister.vue");
const Home = () => import("../views/home.vue");
const SelectMenu = () => import("../views/record/selectMenu.vue");
const Record = () => import("../views/record/recordContents.vue");
const AddMenu = () => import("../views/menu/addMenu.vue");
const PasswordForget = () =>
    import("../views/certification/passwordForget.vue");
const ResetPassword = () => import("../views/certification/resetPassword.vue");
const Inquiry = () => import("../views/inquiry/inquiry.vue");
const UserRecordRanking = () =>
    import("../views/ranking/userRecordRanking.vue");

// import Login from '../views/certification/loginPage.vue';
// import Register from '../views/certification/registerPage.vue';
// import RedirectAuthGoogle from '../components/certification/RedirectAuthGoogle.vue'
// import googleRegister from '../views/certification/googleRegister.vue'
// import Home from '../views/home.vue';
// import SelectMenu from '../views/record/selectMenu.vue';
// import Record from '../views/record/recordContents.vue';
// import trainingMenuList from '../views/trainingMenuList.vue';
// import AddMenu from '../views/menu/addMenu.vue';
// import PasswordForget from '../views/certification/passwordForget.vue';
// import ResetPassword from '../views/certification/resetPassword.vue';
// import Inquiry from '../views/inquiry/inquiry.vue';
// import UserRecordRanking from '../views/ranking/userRecordRanking.vue';

const routes = [
    {
        path: "/",
        name: "home",
        component: Home,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/login",
        name: "login",
        component: Login,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/login/google/callback",
        name: "RedirectAuthGoogle",
        component: RedirectAuthGoogle,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/register",
        name: "register",
        component: Register,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/api/register/:provider",
        name: "googleRegister",
        component: googleRegister,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/password/forget",
        name: "PasswordForget",
        component: PasswordForget,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/api/password/reset",
        name: "ResetPassword",
        component: ResetPassword,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        path: "/inquiry",
        name: "Inquiry",
        component: Inquiry,
        // ログインの必要なし
        meta: { requiresAuth: false },
    },
    {
        // ?：NULL許容
        path: "/selectMenu/:recordId?",
        name: "selectMenu",
        component: SelectMenu,
        // ログインの必要あり
        meta: { requiresAuth: true },
    },
    {
        // ?：NULL許容
        path: "/record/:recordId",
        name: "record",
        component: Record,
        // ログインの必要あり
        meta: { requiresAuth: true },
    },
    {
        path: "/addMenu/:recordId?",
        name: "addMenu",
        component: AddMenu,
        // ログインの必要あり
        meta: { requiresAuth: true },
    },
    {
        path: "/recordRanking",
        name: "userRecordRanking",
        component: UserRecordRanking,
        // ログインの必要あり
        meta: { requiresAuth: true },
    },
    {
        path: "/tr-sitemap",
        name: "trSitemap",
        meta: { requiresAuth: false },
    },
    //指定のないURLの場合ホームにリダイレクト
    {
        path: "/:paths(.*)*",
        name: "nothing",
        redirect: "/",
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes: routes as RouteRecordRaw[],
    scrollBehavior(to, from, savedPosition) {
        if (
            savedPosition &&
            to.path === "/selectMenu" &&
            from.path === "/record"
        ) {
            return savedPosition;
        } else {
            return { top: 0 };
        }
    },
});

export default router;

const session = {
    get<T>(key: string): T | null {
        const item = sessionStorage.getItem(key);
        return item ? JSON.parse(item) : null;
    },
    set(key: string, value: any): void {
        sessionStorage.setItem(key, JSON.stringify(value));
    },
    remove(key: string): void {
        sessionStorage.removeItem(key);
    },
};

export default session;

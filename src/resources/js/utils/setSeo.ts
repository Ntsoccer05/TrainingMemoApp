import { SEO } from "@/config/seo";
import { useHead } from "@unhead/vue";

export function setSeo(page?: string): void {
    if (page !== undefined && page !== null && SEO[page] !== undefined) {
        useHead({
            title: () => SEO[page].title,
            meta: [
                {
                    name: "description",
                    content: SEO[page].description,
                },
                {
                    name: "keywords",
                    content: SEO[page].keywords,
                },
                {
                    name: "robots",
                    content: SEO[page].robots,
                },
            ],
        });
    } else {
        useHead({
            title: () => SEO.DEFAULT.title,
            meta: [
                {
                    name: "description",
                    content: SEO.DEFAULT.description,
                },
                {
                    name: "keywords",
                    content: SEO.DEFAULT.keywords,
                },
                {
                    name: "robots",
                    content: SEO.DEFAULT.robots,
                },
            ],
        });
    }
}

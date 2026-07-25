import { useRoute } from "vue-router";
import { useHead } from "@unhead/vue";
import { SEO, SITE_URL, OG_IMAGE } from "../config/seo";
import { buildCanonicalUrl } from "./seoUrl";
import { buildWebApplicationJsonLd } from "./seoJsonLd";

export function setSeo(page?: string): void {
    const route = useRoute();
    const config =
        page !== undefined && page !== null && SEO[page] !== undefined
            ? SEO[page]
            : SEO.DEFAULT;
    const canonicalUrl = buildCanonicalUrl(SITE_URL, route.fullPath);
    const ogTitle = config.ogTitle ?? config.title;
    const ogDescription = config.ogDescription ?? config.description;

    useHead({
        title: () => config.title,
        meta: [
            { name: "description", content: config.description },
            { name: "keywords", content: config.keywords },
            { name: "robots", content: config.robots },
            { property: "og:type", content: "website" },
            { property: "og:title", content: ogTitle },
            { property: "og:description", content: ogDescription },
            { property: "og:image", content: OG_IMAGE },
            { property: "og:url", content: canonicalUrl },
            { name: "twitter:card", content: "summary_large_image" },
            { name: "twitter:title", content: ogTitle },
            { name: "twitter:description", content: ogDescription },
            { name: "twitter:image", content: OG_IMAGE },
        ],
        link: [{ rel: "canonical", href: canonicalUrl }],
        script:
            page === "home"
                ? [
                      {
                          type: "application/ld+json",
                          innerHTML: JSON.stringify(
                              buildWebApplicationJsonLd(SITE_URL)
                          ),
                      },
                  ]
                : [],
    });
}

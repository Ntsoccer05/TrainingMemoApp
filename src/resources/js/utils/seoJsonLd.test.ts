import { describe, it, expect } from "vitest";
import { buildWebApplicationJsonLd } from "./seoJsonLd";

describe("buildWebApplicationJsonLd", () => {
    it("schema.orgのWebApplication型のオブジェクトを返す", () => {
        const result = buildWebApplicationJsonLd("https://training-memo.com");
        expect(result["@context"]).toBe("https://schema.org");
        expect(result["@type"]).toBe("WebApplication");
    });

    it("渡したsiteUrlをurlに設定する", () => {
        const result = buildWebApplicationJsonLd("https://training-memo.com");
        expect(result.url).toBe("https://training-memo.com");
    });

    it("無料アプリであることを示すofferを含む", () => {
        const result = buildWebApplicationJsonLd("https://training-memo.com");
        expect(result.offers).toEqual({
            "@type": "Offer",
            price: "0",
            priceCurrency: "JPY",
        });
    });

    it("applicationCategoryにHealthApplicationを設定する", () => {
        const result = buildWebApplicationJsonLd("https://training-memo.com");
        expect(result.applicationCategory).toBe("HealthApplication");
    });
});

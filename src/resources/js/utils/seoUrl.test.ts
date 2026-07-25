import { describe, it, expect } from "vitest";
import { buildCanonicalUrl } from "./seoUrl";

describe("buildCanonicalUrl", () => {
    it("ルートパスの場合はsiteUrlの末尾にスラッシュを付けて返す", () => {
        expect(buildCanonicalUrl("https://training-memo.com", "/")).toBe(
            "https://training-memo.com/"
        );
    });

    it("通常のパスをsiteUrlに結合して返す", () => {
        expect(
            buildCanonicalUrl("https://training-memo.com", "/selectMenu/123")
        ).toBe("https://training-memo.com/selectMenu/123");
    });

    it("クエリパラメータを除去する", () => {
        expect(
            buildCanonicalUrl("https://training-memo.com", "/record/5?foo=bar")
        ).toBe("https://training-memo.com/record/5");
    });

    it("ハッシュを除去する", () => {
        expect(
            buildCanonicalUrl("https://training-memo.com", "/login#section")
        ).toBe("https://training-memo.com/login");
    });

    it("siteUrlの末尾にスラッシュがあっても重複しない", () => {
        expect(buildCanonicalUrl("https://training-memo.com/", "/login")).toBe(
            "https://training-memo.com/login"
        );
    });
});

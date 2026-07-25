# SEO技術対策 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OGP/canonical URL/構造化データ(JSON-LD)/robots.txtのSitemap記載/ログイン必須ページのrobots修正を実装し、トレーニング関連ワードでのGoogle検索評価とSNSシェア時の見え方を改善する。

**Architecture:** 既存の `config/seo.ts`(ページ別SEO設定) + `utils/setSeo.ts`(`@unhead/vue`経由でheadに反映)の仕組みに、OGP/canonical/JSON-LDを追加する。canonical URL組み立てとJSON-LD生成はVueに依存しない純粋関数として `utils/seoUrl.ts` / `utils/seoJsonLd.ts` に切り出し、vitestでユニットテストする。`setSeo.ts` 自体(useHead/useRouteを使うVue composable部分)は手動確認で検証する。

**Tech Stack:** Vue 3 + TypeScript, Vite 3.2.3, `@unhead/vue` 2.0.8, vue-router 4, vitest 0.34.6(Vite 3系と互換性のある最終系列)

---

## ファイル構造

- `src/package.json` (修正) — devDependenciesに`vitest`追加、`test`スクリプト追加
- `src/vitest.config.ts` (新規) — vitestの実行設定(`@`エイリアス、node環境)
- `src/resources/js/types/seo.d.ts` (新規) — `SeoPageConfig`型定義
- `src/resources/js/utils/seoUrl.ts` (新規) — `buildCanonicalUrl`純粋関数
- `src/resources/js/utils/seoUrl.test.ts` (新規) — 上記のテスト
- `src/resources/js/utils/seoJsonLd.ts` (新規) — `buildWebApplicationJsonLd`純粋関数
- `src/resources/js/utils/seoJsonLd.test.ts` (新規) — 上記のテスト
- `src/resources/js/config/seo.ts` (修正) — `SITE_URL`/`OG_IMAGE`定数追加、型注釈、ログイン必須ページのrobots修正
- `src/resources/js/utils/setSeo.ts` (修正) — OGP/canonical/JSON-LDをuseHeadに反映
- `src/public/robots.txt` (修正) — `Sitemap:`行を追記

---

### Task 1: vitest導入

**Files:**
- Modify: `src/package.json`
- Create: `src/vitest.config.ts`

- [ ] **Step 1: package.jsonにvitestとtestスクリプトを追加する**

`src/package.json` の `devDependencies` に `"vitest": "^0.34.6"` を追加し、`scripts` に `"test": "vitest run"` を追加する。

```json
{
    "private": true,
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "test": "vitest run"
    },
    "devDependencies": {
        "@babel/types": "^7.23.9",
        "@types/bootstrap": "^5.2.10",
        "@types/node": "^20.11.16",
        "@vitejs/plugin-vue": "^3.2.0",
        "@vue/tsconfig": "^0.5.1",
        "autoprefixer": "^10.4.13",
        "axios": "^1.1.2",
        "laravel-vite-plugin": "^0.6.0",
        "lodash": "^4.17.19",
        "postcss": "^8.1.14",
        "tailwindcss": "^3.2.4",
        "typescript": "^5.3.3",
        "vite": "^3.0.0",
        "vite-plugin-checker": "^0.6.2",
        "vitest": "^0.34.6",
        "vue-tsc": "^1.8.27"
    },
    "dependencies": {
        "@kouts/vue-modal": "^5.0.0",
        "@unhead/vue": "^2.0.8",
        "alpinejs": "^3.13.7",
        "eslint": "^8.56.0",
        "tw-elements": "^1.1.0",
        "v-calendar": "^3.0.0-alpha.8",
        "vue": "^3.2.45",
        "vue-router": "^4.1.6",
        "vuedraggable": "^4.1.0",
        "vuex": "^4.0.2"
    }
}
```

- [ ] **Step 2: vitest.config.tsを作成する**

```typescript
import { defineConfig } from "vitest/config";

export default defineConfig({
  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },
  test: {
    environment: "node",
  },
});
```

- [ ] **Step 3: 依存関係をインストールする**

Run: `cd src && npm install`
Expected: `vitest@0.34.6` が追加され、既存の `vite@3.2.3` のバージョンが変わらないこと(`npm ls vite` で確認)。

Run: `cd src && npm ls vite`
Expected: `vite@3.2.3` のまま(warning等でvite5要求のツリーが出ないこと)。

---

### Task 2: SeoPageConfig型定義を作成する

**Files:**
- Create: `src/resources/js/types/seo.d.ts`

- [ ] **Step 1: 型定義ファイルを作成する**

既存の `types/inquiry.d.ts` 等と同じ `export declare type` 形式に合わせる。

```typescript
export declare type SeoPageConfig = {
    title: string;
    description: string;
    keywords: string;
    robots: string;
    ogTitle?: string;
    ogDescription?: string;
};
```

---

### Task 3: canonical URL組み立てロジック(TDD)

**Files:**
- Create: `src/resources/js/utils/seoUrl.ts`
- Test: `src/resources/js/utils/seoUrl.test.ts`

- [ ] **Step 1: 失敗するテストを書く**

```typescript
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
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `cd src && npx vitest run resources/js/utils/seoUrl.test.ts`
Expected: FAIL(`./seoUrl` が見つからない、または `buildCanonicalUrl is not a function`)

- [ ] **Step 3: 最小実装を書く**

```typescript
export function buildCanonicalUrl(siteUrl: string, path: string): string {
    const normalizedSiteUrl = siteUrl.replace(/\/$/, "");
    const pathWithoutHash = path.split("#")[0];
    const pathWithoutQuery = pathWithoutHash.split("?")[0];

    if (pathWithoutQuery === "/") {
        return `${normalizedSiteUrl}/`;
    }
    return `${normalizedSiteUrl}${pathWithoutQuery}`;
}
```

- [ ] **Step 4: テストがパスすることを確認する**

Run: `cd src && npx vitest run resources/js/utils/seoUrl.test.ts`
Expected: PASS(5 tests)

---

### Task 4: 構造化データ(JSON-LD)組み立てロジック(TDD)

**Files:**
- Create: `src/resources/js/utils/seoJsonLd.ts`
- Test: `src/resources/js/utils/seoJsonLd.test.ts`

- [ ] **Step 1: 失敗するテストを書く**

```typescript
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
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `cd src && npx vitest run resources/js/utils/seoJsonLd.test.ts`
Expected: FAIL(`./seoJsonLd` が見つからない、または `buildWebApplicationJsonLd is not a function`)

- [ ] **Step 3: 最小実装を書く**

```typescript
export function buildWebApplicationJsonLd(
    siteUrl: string
): Record<string, unknown> {
    return {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        name: "トレメモ",
        description:
            "トレメモは、あなたの日々のトレーニングを簡単に記録・管理できる無料サービスです。運動習慣をサポート！",
        url: siteUrl,
        applicationCategory: "HealthApplication",
        operatingSystem: "Web",
        offers: {
            "@type": "Offer",
            price: "0",
            priceCurrency: "JPY",
        },
    };
}
```

- [ ] **Step 4: テストがパスすることを確認する**

Run: `cd src && npx vitest run resources/js/utils/seoJsonLd.test.ts`
Expected: PASS(4 tests)

---

### Task 5: config/seo.tsを更新する(SITE_URL/OG_IMAGE定数・型注釈・robots修正)

**Files:**
- Modify: `src/resources/js/config/seo.ts`

- [ ] **Step 1: SITE_URL/OG_IMAGE定数と型注釈を追加し、ログイン必須ページのrobotsを修正する**

ファイル全体を以下の内容に置き換える(`selectMenu`/`record`/`addMenu`/`userRecordRanking`の`robots`を`"index, follow"`から`"noindex, follow"`に変更、冒頭に`SITE_URL`/`OG_IMAGE`定数を追加、`SEO`に`Record<string, SeoPageConfig>`の型注釈を追加)。

```typescript
import { SeoPageConfig } from "../types/seo";

const COMMON_KEYWORDS = "筋トレ, ジムトレ, トレーニング, 全てのトレーニング";

export const SITE_URL = "https://training-memo.com";
export const OG_IMAGE =
    "https://training-memo.s3.ap-northeast-1.amazonaws.com/icon.ico";

function mergeKeywords(base: string) {
    return `${base}, ${COMMON_KEYWORDS}`;
}

export const SEO: Record<string, SeoPageConfig> = {
    DEFAULT: {
        title: "トレーニング記録 | 毎日の運動を簡単に管理・メモできるサービス",
        description:
            "トレーニング記録サービス「トレメモ」では、日々の運動・トレーニング内容を手軽にメモ・管理できます。日付ごとの記録でモチベーション維持に最適！スマホ・PC対応、無料で使えます。",
        keywords: mergeKeywords(
            "トレーニング記録, 運動メモ, 筋トレ管理, ワークアウトログ, トレーニング日記, フィットネス管理, ジム"
        ),
        robots: "index, follow",
    },
    home: {
        title: "トレメモ | トレーニング記録サービス",
        description:
            "トレメモは、あなたの日々のトレーニングを簡単に記録・管理できる無料サービスです。運動習慣をサポート！",
        keywords: mergeKeywords(
            "トレメモ, トレーニング記録, 運動管理, フィットネスメモ"
        ),
        robots: "index, follow",
    },
    login: {
        title: "ログイン | トレメモ",
        description:
            "トレメモにログインして、あなたのトレーニング記録を管理しましょう。簡単登録・無料で使えます。",
        keywords: mergeKeywords("トレメモ, ログイン, トレーニング記録"),
        robots: "noindex, nofollow",
    },
    RedirectAuthGoogle: {
        title: "Google認証リダイレクト | トレメモ",
        description:
            "Googleアカウントを使ってトレメモに簡単ログイン・登録できます。",
        keywords: mergeKeywords("トレメモ, Googleログイン, 簡単登録"),
        robots: "noindex, nofollow",
    },
    register: {
        title: "新規登録 | トレメモ",
        description:
            "トレメモに無料登録して、毎日のトレーニング記録をスタートしましょう！",
        keywords: mergeKeywords(
            "トレメモ, 新規登録, 無料会員登録, トレーニング管理"
        ),
        robots: "noindex, nofollow",
    },
    googleRegister: {
        title: "Google連携登録 | トレメモ",
        description:
            "Googleアカウントでトレメモに登録できます。わずか数秒で簡単スタート！",
        keywords: mergeKeywords("トレメモ, Google連携, 簡単登録"),
        robots: "noindex, nofollow",
    },
    PasswordForget: {
        title: "パスワードをお忘れですか？ | トレメモ",
        description: "パスワードをお忘れの場合はこちらから再設定できます。",
        keywords: mergeKeywords(
            "トレメモ, パスワードリセット, パスワード忘れた"
        ),
        robots: "noindex, nofollow",
    },
    ResetPassword: {
        title: "パスワード再設定 | トレメモ",
        description:
            "新しいパスワードを設定して、トレメモを再びご利用いただけます。",
        keywords: mergeKeywords("トレメモ, パスワード再設定"),
        robots: "noindex, nofollow",
    },
    Inquiry: {
        title: "お問い合わせ | トレメモ",
        description:
            "トレメモに関するご質問やご相談はこちらからお問い合わせください。",
        keywords: mergeKeywords("トレメモ, お問い合わせ, サポート"),
        robots: "index, follow",
    },
    selectMenu: {
        title: "メニュー選択 | トレメモ",
        description:
            "トレーニング種目を選択して、あなたの運動記録を充実させましょう。",
        keywords: mergeKeywords(
            "トレメモ, メニュー選択, 種目追加, トレーニング種目"
        ),
        robots: "noindex, follow",
    },
    record: {
        title: "トレーニング記録詳細 | トレメモ",
        description:
            "トレーニング記録の詳細ページです。日付ごとの運動内容を確認・編集できます。",
        keywords: mergeKeywords(
            "トレメモ, 記録詳細, トレーニングログ, 運動履歴"
        ),
        robots: "noindex, follow",
    },
    addMenu: {
        title: "メニュー追加 | トレメモ",
        description:
            "新しいトレーニング種目を追加して、記録をさらに充実させましょう。",
        keywords: mergeKeywords("トレメモ, メニュー追加, トレーニング種目追加"),
        robots: "noindex, follow",
    },
    userRecordRanking: {
        title: "ユーザー記録ランキング | トレメモ",
        description:
            "トレーニング記録ランキングをチェックして、他のユーザーと成果を比較してみましょう。",
        keywords: mergeKeywords(
            "トレメモ, 記録ランキング, トレーニングランキング"
        ),
        robots: "noindex, follow",
    },
    nothing: {
        title: "ページが見つかりません | トレメモ",
        description:
            "指定されたページが見つかりませんでした。トップページに戻るか、メニューから選んでください。",
        keywords: mergeKeywords("トレメモ, ページが見つかりません, 404エラー"),
        robots: "noindex, nofollow",
    },
};
```

- [ ] **Step 2: 型チェックを実行して整合性を確認する**

Run: `cd src && npx vue-tsc --noEmit`
Expected: `config/seo.ts` / `types/seo.d.ts` に起因するエラーが出ないこと(既存の無関係なエラーが元々ある場合はこのタスクでは無視してよいが、新規エラーが増えていないことを確認する)。

---

### Task 6: setSeo.tsを更新する(OGP/canonical/JSON-LD反映)

**Files:**
- Modify: `src/resources/js/utils/setSeo.ts`

- [ ] **Step 1: OGP/canonical/JSON-LDをuseHeadに反映するよう書き換える**

```typescript
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
                          children: JSON.stringify(
                              buildWebApplicationJsonLd(SITE_URL)
                          ),
                      },
                  ]
                : [],
    });
}
```

- [ ] **Step 2: 型チェックを実行する**

Run: `cd src && npx vue-tsc --noEmit`
Expected: `utils/setSeo.ts` に起因する型エラーが出ないこと。

---

### Task 7: robots.txtにSitemapを追記する

**Files:**
- Modify: `src/public/robots.txt`

- [ ] **Step 1: Sitemap行を追記する**

```
User-agent: *
Disallow:
Sitemap: https://training-memo.com/tr-sitemap
```

---

### Task 8: 手動動作確認

**Files:** なし(確認のみ)

- [ ] **Step 1: 全ユニットテストを実行する**

Run: `cd src && npm run test`
Expected: `seoUrl.test.ts`(5 tests)・`seoJsonLd.test.ts`(4 tests)が全てPASS

- [ ] **Step 2: ビルドが型エラーなく通ることを確認する**

Run: `cd src && npm run build`
Expected: ビルドが正常終了する(exit code 0)。`dist/robots.txt` に `Sitemap: https://training-memo.com/tr-sitemap` が含まれることを確認する。

Run: `cd src && cat dist/robots.txt`
Expected:
```
User-agent: *
Disallow:
Sitemap: https://training-memo.com/tr-sitemap
```

- [ ] **Step 3: 開発サーバーでheadの内容を目視確認する**

Run: `cd src && npm run dev`

ブラウザで `http://localhost:5173/` を開き、DevTools > Elements > `<head>` を確認し、以下がすべて存在することを確認する:
- `<title>トレメモ | トレーニング記録サービス</title>`
- `<meta name="description" content="トレメモは、あなたの日々のトレーニングを簡単に記録・管理できる無料サービスです。運動習慣をサポート！">`
- `<meta property="og:title" content="トレメモ | トレーニング記録サービス">`
- `<meta property="og:image" content="https://training-memo.s3.ap-northeast-1.amazonaws.com/icon.ico">`
- `<meta name="twitter:card" content="summary_large_image">`
- `<link rel="canonical" href="https://training-memo.com/">`
- `<script type="application/ld+json">` に `"@type":"WebApplication"` を含むJSON

`http://localhost:5173/login` に遷移し、以下を確認する:
- `<meta name="robots" content="noindex, nofollow">`(変更していないため従来通り)
- `<link rel="canonical" href="https://training-memo.com/login">`

ログインした状態で `http://localhost:5173/selectMenu` に遷移し、以下を確認する:
- `<meta name="robots" content="noindex, follow">`(Task 5で `index, follow` から変更した結果)

---

## 最終コミット

全タスク完了後、`src/` を含む単一リポジトリでまとめてコミットする。

```bash
git add src/package.json src/package-lock.json src/vitest.config.ts \
  src/resources/js/types/seo.d.ts \
  src/resources/js/utils/seoUrl.ts src/resources/js/utils/seoUrl.test.ts \
  src/resources/js/utils/seoJsonLd.ts src/resources/js/utils/seoJsonLd.test.ts \
  src/resources/js/config/seo.ts src/resources/js/utils/setSeo.ts \
  src/public/robots.txt
git commit -m "$(cat <<'EOF'
feat: SEO技術対策(OGP/canonical/JSON-LD/robots)を追加

「筋トレ」「ジム」「家トレ」等のワードでの検索評価向上とSNSシェア対応のため、
OGP/Twitter Card・canonical URL・トップページの構造化データ(JSON-LD)を追加し、
robots.txtにサイトマップを記載。ログイン必須ページはnoindexに修正した。

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

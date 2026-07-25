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

export function buildCanonicalUrl(siteUrl: string, path: string): string {
    const normalizedSiteUrl = siteUrl.replace(/\/$/, "");
    const pathWithoutHash = path.split("#")[0];
    const pathWithoutQuery = pathWithoutHash.split("?")[0];

    if (pathWithoutQuery === "/") {
        return `${normalizedSiteUrl}/`;
    }
    return `${normalizedSiteUrl}${pathWithoutQuery}`;
}

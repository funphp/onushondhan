// Locale-dependent formatting lives in src/i18n/format.ts (makeFormat / useI18n().f).

/** Only allow http(s) links to be rendered as clickable (defence against javascript: URLs). */
export function safeHref(url: string | null | undefined): string | null {
  if (!url) return null;
  try {
    const u = new URL(url);
    return u.protocol === "http:" || u.protocol === "https:" ? u.toString() : null;
  } catch {
    return null;
  }
}

export function investigationPath(id: string, slug?: string | null): string {
  return `/investigation/${id}${slug ? `/${slug}` : ""}`;
}

/** Route params for non-ASCII (Bangla) slugs may arrive percent-encoded; decode once, tolerating bad input. */
export function decodeParam(value: string): string {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

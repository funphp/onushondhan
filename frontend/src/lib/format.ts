export function formatDate(iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { year: "numeric", month: "short", day: "numeric" }): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return new Intl.DateTimeFormat("en-GB", { timeZone: "UTC", ...opts }).format(d);
}

export function formatDateTime(iso: string | null | undefined): string {
  return formatDate(iso, { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

export function formatNumber(n: number | null | undefined): string {
  return new Intl.NumberFormat("en", { notation: (n ?? 0) >= 10000 ? "compact" : "standard" }).format(n ?? 0);
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  const units = ["KB", "MB", "GB"];
  let v = bytes / 1024;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v.toFixed(v < 10 ? 1 : 0)} ${units[i]}`;
}

export function humanize(value: string | null | undefined): string {
  if (!value) return "";
  const s = value.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}

export function pluralize(n: number, one: string, many = one + "s"): string {
  return `${formatNumber(n)} ${n === 1 ? one : many}`;
}

const COUNTRY = typeof Intl !== "undefined" && "DisplayNames" in Intl ? new Intl.DisplayNames(["en"], { type: "region" }) : null;
export function countryName(code: string | null | undefined): string {
  if (!code) return "";
  try {
    return COUNTRY?.of(code.toUpperCase()) ?? code;
  } catch {
    return code;
  }
}

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

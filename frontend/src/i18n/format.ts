import type { Locale } from "./config";

/**
 * Locale-aware formatting. Bangla uses Bengali numerals and month names; times are
 * shown in Bangladesh time (Asia/Dhaka), the platform's primary audience.
 */
export function makeFormat(locale: Locale) {
  const tag = locale === "bn" ? "bn-BD" : "en-GB";
  const timeZone = "Asia/Dhaka";
  const regions = typeof Intl !== "undefined" && "DisplayNames" in Intl ? new Intl.DisplayNames([locale], { type: "region" }) : null;

  const date = (iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { year: "numeric", month: "short", day: "numeric" }): string => {
    if (!iso) return "";
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? "" : new Intl.DateTimeFormat(tag, { timeZone, ...opts }).format(d);
  };

  const number = (n: number | null | undefined): string =>
    new Intl.NumberFormat(tag, { notation: (n ?? 0) >= 10000 ? "compact" : "standard" }).format(n ?? 0);

  return {
    locale,
    date,
    dateTime: (iso: string | null | undefined) => date(iso, { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }),
    monthYear: (iso: string | null | undefined) => date(iso, { year: "numeric", month: "long" }),
    number,
    /** Plain digits (no grouping/compaction) — for ranks, versions, counters inside sentences. */
    digits: (n: number | string) => new Intl.NumberFormat(tag, { useGrouping: false }).format(Number(n)),
    bytes: (bytes: number): string => {
      const units = ["B", "KB", "MB", "GB"];
      let v = bytes;
      let i = 0;
      while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
      }
      const shown = new Intl.NumberFormat(tag, { maximumFractionDigits: i === 0 || v >= 10 ? 0 : 1, minimumFractionDigits: i === 0 || v >= 10 ? 0 : 1 }).format(v);
      return `${shown} ${units[i]}`;
    },
    country: (code: string | null | undefined): string => {
      if (!code) return "";
      try {
        return regions?.of(code.toUpperCase()) ?? code;
      } catch {
        return code;
      }
    },
  };
}

export type Format = ReturnType<typeof makeFormat>;

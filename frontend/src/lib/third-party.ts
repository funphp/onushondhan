/**
 * Google Analytics 4 and Google AdSense configuration.
 *
 * Both are opt-in by environment: with the variables unset nothing third-party is rendered,
 * loaded or allowed by the CSP. NEXT_PUBLIC_* values are baked in at build time, so a change
 * needs a rebuild (the CSP in next.config.ts is derived from the same values).
 */

const GA_PATTERN = /^G-[A-Z0-9]{4,20}$/;
const ADSENSE_CLIENT_PATTERN = /^ca-pub-\d{10,20}$/;
const ADSENSE_SLOT_PATTERN = /^\d{5,20}$/;

function pick(value: string | undefined, pattern: RegExp): string {
  const v = (value ?? "").trim();
  return pattern.test(v) ? v : "";
}

export const GA_MEASUREMENT_ID = pick(process.env.NEXT_PUBLIC_GA_MEASUREMENT_ID, GA_PATTERN);
export const ADSENSE_CLIENT = pick(process.env.NEXT_PUBLIC_ADSENSE_CLIENT, ADSENSE_CLIENT_PATTERN);

const displaySlot = pick(process.env.NEXT_PUBLIC_ADSENSE_SLOT_DISPLAY, ADSENSE_SLOT_PATTERN);
export const ADSENSE_SLOTS = {
  /** Responsive display unit (home page, report sidebar). */
  display: displaySlot,
  /** Unit shown under a report's body; falls back to the display unit. */
  article: pick(process.env.NEXT_PUBLIC_ADSENSE_SLOT_ARTICLE, ADSENSE_SLOT_PATTERN) || displaySlot,
} as const;
export type AdPlacement = keyof typeof ADSENSE_SLOTS;

export const ANALYTICS_ENABLED = GA_MEASUREMENT_ID !== "";
export const ADS_ENABLED = ADSENSE_CLIENT !== "";
export const THIRD_PARTY_ENABLED = ANALYTICS_ENABLED || ADS_ENABLED;

/** Publisher id as used in ads.txt ("pub-…" without the "ca-" prefix). */
export const ADSENSE_PUBLISHER_ID = ADSENSE_CLIENT.replace(/^ca-/, "");

/**
 * Routes where no page view is sent and no ad is shown: account areas, sign-in flows and
 * — most importantly — the tip pages, where a source contacting an investigator must not
 * be observed by third parties.
 */
const EXCLUDED_PREFIXES = [
  "/dashboard",
  "/account",
  "/tips",
  "/login",
  "/register",
  "/forgot-password",
  "/reset-password",
  "/verify-email",
  "/agreements",
  "/report",
];

export function isThirdPartyExcluded(pathname: string): boolean {
  const path = pathname.split(/[?#]/)[0] || "/";
  return EXCLUDED_PREFIXES.some((p) => path === p || path.startsWith(`${p}/`));
}

/** The page location sent to Analytics: origin + path only, never query strings or fragments. */
export function analyticsLocation(origin: string, pathname: string): string {
  return `${origin.replace(/\/$/, "")}${pathname.split(/[?#]/)[0] || "/"}`;
}

export type ConsentChoice = "granted" | "denied";
export const CONSENT_COOKIE = "ons_consent";

export function readConsentCookie(cookieString: string): ConsentChoice | null {
  const match = cookieString.split(/;\s*/).find((c) => c.startsWith(`${CONSENT_COOKIE}=`));
  const value = match?.slice(CONSENT_COOKIE.length + 1);
  return value === "granted" || value === "denied" ? value : null;
}

/** Content-Security-Policy additions for the enabled services. */
export function thirdPartyCsp(): { script: string[]; connect: string[]; frame: string[]; img: string[] } {
  const script: string[] = [];
  const connect: string[] = [];
  const frame: string[] = [];
  const img: string[] = [];
  if (ANALYTICS_ENABLED) {
    script.push("https://www.googletagmanager.com");
    connect.push("https://*.google-analytics.com", "https://*.analytics.google.com", "https://www.googletagmanager.com");
    img.push("https://*.google-analytics.com", "https://www.googletagmanager.com");
  }
  if (ADS_ENABLED) {
    script.push("https://pagead2.googlesyndication.com", "https://*.googlesyndication.com", "https://*.adtrafficquality.google", "https://www.google.com", "https://*.gstatic.com", "https://*.doubleclick.net");
    connect.push("https://pagead2.googlesyndication.com", "https://*.googlesyndication.com", "https://*.adtrafficquality.google", "https://*.doubleclick.net", "https://*.google.com");
    frame.push("https://googleads.g.doubleclick.net", "https://*.googlesyndication.com", "https://*.adtrafficquality.google", "https://www.google.com", "https://*.doubleclick.net");
    // Ad creatives are served from many advertiser CDNs.
    img.push("https:");
  }
  return { script, connect, frame, img };
}

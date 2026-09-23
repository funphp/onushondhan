export const SITE_NAME = "Onushondhan";
export const SITE_TAGLINE = "A home for independent investigations";
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");
export const ADMIN_URL = process.env.NEXT_PUBLIC_ADMIN_URL ?? "/admin";

export const PLATFORM_DISCLAIMER =
  "Onushondhan is a publishing platform for independent investigators. Publication does not mean Onushondhan has verified or endorsed a report — investigators are responsible for their own work.";

export const SUPPORT_NOTE = "Support counts reflect community engagement, not accuracy or quality.";

export function absoluteUrl(path: string): string {
  return `${SITE_URL}${path.startsWith("/") ? path : `/${path}`}`;
}

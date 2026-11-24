import { ADS_ENABLED, ADSENSE_PUBLISHER_ID } from "@/lib/third-party";

// Built once: the publisher id is a build-time NEXT_PUBLIC_ value.
export const dynamic = "force-static";

/** Authorised Digital Sellers file for AdSense (https://<domain>/ads.txt). */
export function GET(): Response {
  if (!ADS_ENABLED) return new Response("Not found\n", { status: 404, headers: { "Content-Type": "text/plain; charset=utf-8" } });
  return new Response(`google.com, ${ADSENSE_PUBLISHER_ID}, DIRECT, f08c47fec0942fa0\n`, {
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=86400" },
  });
}

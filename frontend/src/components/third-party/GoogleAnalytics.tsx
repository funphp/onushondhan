"use client";

import Script from "next/script";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { analyticsLocation, ANALYTICS_ENABLED, GA_MEASUREMENT_ID, isThirdPartyExcluded } from "@/lib/third-party";
import { useConsent } from "./Consent";

/**
 * Loads GA4 only after the visitor accepts, and sends one page view per route change with
 * the path only (no query strings), skipping account, sign-in and tip pages entirely.
 * Signals/ad-personalisation from Analytics are switched off.
 */
export function GoogleAnalytics() {
  const { choice } = useConsent();
  const pathname = usePathname();
  const [ready, setReady] = useState(false);
  const allowed = ANALYTICS_ENABLED && choice === "granted";

  useEffect(() => {
    if (!allowed || !ready || !window.gtag || isThirdPartyExcluded(pathname)) return;
    window.gtag("event", "page_view", {
      page_location: analyticsLocation(window.location.origin, pathname),
      page_path: pathname,
      page_title: document.title,
    });
  }, [allowed, ready, pathname]);

  if (!allowed) return null;
  return (
    <Script
      id="ga4"
      src={`https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`}
      strategy="afterInteractive"
      onReady={() => {
        window.gtag?.("js", new Date());
        window.gtag?.("config", GA_MEASUREMENT_ID, {
          send_page_view: false,
          allow_google_signals: false,
          allow_ad_personalization_signals: false,
        });
        setReady(true);
      }}
    />
  );
}

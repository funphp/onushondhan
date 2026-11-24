"use client";

import Script from "next/script";
import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { useI18n } from "@/i18n/client";
import { ADSENSE_CLIENT, ADSENSE_SLOTS, isThirdPartyExcluded, type AdPlacement } from "@/lib/third-party";
import { cn } from "@/components/ui/cn";

/**
 * A manually placed AdSense unit, clearly labelled as an advertisement. The AdSense script
 * is only requested on pages that actually show a unit (never on account or tip pages), so
 * keep "Auto ads" switched off in the AdSense dashboard.
 */
export function AdSlot({ placement, className }: { placement: AdPlacement; className?: string }) {
  const { t } = useI18n();
  const pathname = usePathname();
  const slot = ADSENSE_SLOTS[placement];
  const enabled = ADSENSE_CLIENT !== "" && slot !== "" && !isThirdPartyExcluded(pathname);

  if (!enabled) return null;
  return (
    <aside aria-label={t.ads.label} className={cn("not-prose border-y border-line py-3", className)}>
      <p className="file-stamp mb-2 text-center text-[11px] text-muted">{t.ads.label}</p>
      {/* Re-mount per route so each navigation gets a fresh unit. */}
      <AdUnit key={pathname} slot={slot} />
      <Script
        id="adsbygoogle"
        src={`https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${ADSENSE_CLIENT}`}
        strategy="afterInteractive"
        crossOrigin="anonymous"
      />
    </aside>
  );
}

function AdUnit({ slot }: { slot: string }) {
  const ref = useRef<HTMLModElement>(null);
  useEffect(() => {
    const el = ref.current;
    // Guard against double pushes (React strict mode, re-renders): AdSense throws on them.
    if (!el || el.dataset.pushed) return;
    el.dataset.pushed = "1";
    try {
      (window.adsbygoogle = window.adsbygoogle || []).push({});
    } catch {
      // Blocked by an ad blocker or not yet approved — the slot simply stays empty.
    }
  }, []);
  return (
    <ins
      ref={ref}
      className="adsbygoogle block min-h-[100px]"
      style={{ display: "block" }}
      data-ad-client={ADSENSE_CLIENT}
      data-ad-slot={slot}
      data-ad-format="auto"
      data-full-width-responsive="true"
    />
  );
}

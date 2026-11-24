"use client";

import Link from "next/link";
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { Button } from "@/components/ui/Button";
import { useI18n } from "@/i18n/client";
import { ADS_ENABLED, ANALYTICS_ENABLED, CONSENT_COOKIE, readConsentCookie, THIRD_PARTY_ENABLED, type ConsentChoice } from "@/lib/third-party";

type Gtag = (...args: unknown[]) => void;
declare global {
  interface Window {
    gtag?: Gtag;
    dataLayer?: unknown[];
    adsbygoogle?: unknown[];
  }
}

interface ConsentState {
  /** null until the visitor has chosen (or before hydration). */
  choice: ConsentChoice | null;
  setChoice: (choice: ConsentChoice) => void;
  openSettings: () => void;
}

const ConsentContext = createContext<ConsentState>({ choice: null, setChoice: () => {}, openSettings: () => {} });

export function useConsent(): ConsentState {
  return useContext(ConsentContext);
}

function applyConsent(choice: ConsentChoice): void {
  const value = choice === "granted" ? "granted" : "denied";
  window.gtag?.("consent", "update", { ad_storage: value, ad_user_data: value, ad_personalization: value, analytics_storage: value });
}

export function ConsentProvider({ children }: { children: ReactNode }) {
  const [choice, setChoiceState] = useState<ConsentChoice | null>(null);
  const [bannerOpen, setBannerOpen] = useState(false);

  useEffect(() => {
    if (!THIRD_PARTY_ENABLED) return;
    const stored = readConsentCookie(document.cookie);
    // Reading the cookie must wait for hydration; the server never knows the choice.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setChoiceState(stored);
    setBannerOpen(stored === null);
  }, []);

  const setChoice = useCallback((next: ConsentChoice) => {
    const secure = window.location.protocol === "https:" ? "; secure" : "";
    document.cookie = `${CONSENT_COOKIE}=${next}; path=/; max-age=15552000; samesite=lax${secure}`;
    applyConsent(next);
    setChoiceState(next);
    setBannerOpen(false);
  }, []);

  const openSettings = useCallback(() => setBannerOpen(true), []);
  const value = useMemo(() => ({ choice, setChoice, openSettings }), [choice, setChoice, openSettings]);

  return (
    <ConsentContext.Provider value={value}>
      {children}
      {THIRD_PARTY_ENABLED && bannerOpen && <ConsentBanner current={choice} onChoose={setChoice} />}
    </ConsentContext.Provider>
  );
}

function ConsentBanner({ current, onChoose }: { current: ConsentChoice | null; onChoose: (c: ConsentChoice) => void }) {
  const { t } = useI18n();
  return (
    <section
      role="region"
      aria-labelledby="consent-title"
      className="fixed inset-x-0 bottom-0 z-50 border-t-4 border-accent bg-surface text-ink shadow-[0_-4px_24px_rgba(0,0,0,0.15)]"
    >
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="text-sm">
          <h2 id="consent-title" className="font-semibold">{t.consent.title}</h2>
          <p className="mt-1 text-muted">
            {t.consent.body(ANALYTICS_ENABLED, ADS_ENABLED)}{" "}
            <Link href="/pages/privacy-policy" className="underline">{t.consent.policy}</Link>
          </p>
          {current && <p className="mt-1 text-xs text-muted">{current === "granted" ? t.consent.currentGranted : t.consent.currentDenied}</p>}
        </div>
        <div className="flex shrink-0 gap-2">
          <Button variant="secondary" onClick={() => onChoose("denied")}>{t.consent.decline}</Button>
          <Button onClick={() => onChoose("granted")}>{t.consent.accept}</Button>
        </div>
      </div>
    </section>
  );
}

/** Footer link that reopens the consent banner. Renders nothing when no service is configured. */
export function CookieSettingsButton({ className }: { className?: string }) {
  const { openSettings } = useConsent();
  const { t } = useI18n();
  if (!THIRD_PARTY_ENABLED) return null;
  return (
    <button type="button" onClick={openSettings} className={className}>
      {t.consent.settings}
    </button>
  );
}

"use client";

import { createContext, useContext, useMemo, type ReactNode } from "react";
import { LOCALE_COOKIE, type Locale } from "./config";
import { makeFormat } from "./format";
import { getMessages } from "./index";

const LocaleContext = createContext<Locale>("bn");

export function I18nProvider({ locale, children }: { locale: Locale; children: ReactNode }) {
  return <LocaleContext.Provider value={locale}>{children}</LocaleContext.Provider>;
}

export function useLocale(): Locale {
  return useContext(LocaleContext);
}

/** Messages and formatters for client components. */
export function useI18n() {
  const locale = useLocale();
  return useMemo(() => ({ locale, t: getMessages(locale), f: makeFormat(locale) }), [locale]);
}

/** Persist the interface language for a year (read by server components on the next render). */
export function writeLocaleCookie(locale: Locale): void {
  document.cookie = `${LOCALE_COOKIE}=${locale}; path=/; max-age=31536000; samesite=lax`;
}

import "server-only";
import { cookies } from "next/headers";
import { DEFAULT_LOCALE, LOCALE_COOKIE, isLocale, type Locale } from "./config";
import { makeFormat } from "./format";
import { getMessages } from "./index";

/** Interface language for this request (cookie, default Bangla). */
export async function getLocale(): Promise<Locale> {
  const value = (await cookies()).get(LOCALE_COOKIE)?.value;
  return isLocale(value) ? value : DEFAULT_LOCALE;
}

/** Messages + formatters for server components. */
export async function getI18n() {
  const locale = await getLocale();
  return { locale, t: getMessages(locale), f: makeFormat(locale) };
}

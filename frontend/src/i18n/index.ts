import { bn } from "./messages/bn";
import { en, type Messages } from "./messages/en";
import type { Locale } from "./config";

export type { Messages } from "./messages/en";
export * from "./config";

const DICTIONARIES: Record<Locale, Messages> = { bn, en };

export function getMessages(locale: Locale): Messages {
  return DICTIONARIES[locale];
}

/** Label for an API enum value (status, type…), falling back to the raw value. */
export function labelFor(map: Record<string, string>, key: string | null | undefined): string {
  if (!key) return "";
  return map[key] ?? key.replace(/_/g, " ");
}

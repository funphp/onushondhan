"use client";

import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { LOCALES, type Locale } from "@/i18n/config";
import { useI18n, writeLocaleCookie } from "@/i18n/client";
import { api } from "@/lib/api";

/**
 * Switches the interface language (menus, labels, messages). Investigators' content is
 * never translated. Signed-in users also get the choice saved for notifications/emails.
 */
export function LanguageSwitcher() {
  const { locale, t } = useI18n();
  const { user, setUser } = useAuth();
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  const choose = (next: Locale) => {
    if (next === locale) return;
    writeLocaleCookie(next);
    if (user) {
      void api<{ data: typeof user }>("/me/profile", { method: "PATCH", body: { locale: next } }).then((r) => setUser(r.data)).catch(() => undefined);
    }
    startTransition(() => router.refresh());
  };

  return (
    <div role="group" aria-label={t.language.label} className="flex overflow-hidden rounded-md border border-line text-sm" aria-busy={pending || undefined}>
      {LOCALES.map((l) => (
        <button
          key={l}
          type="button"
          lang={l}
          aria-pressed={locale === l}
          onClick={() => choose(l)}
          className={`px-2.5 py-1 ${locale === l ? "bg-accent text-white dark:text-paper" : "hover:bg-accent-soft"}`}
        >
          {t.language[l]}
        </button>
      ))}
    </div>
  );
}

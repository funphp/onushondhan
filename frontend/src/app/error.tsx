"use client";

import { useI18n } from "@/i18n/client";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const { t } = useI18n();
  return (
    <div role="alert" className="mx-auto max-w-xl py-16 text-center">
      <h1 className="font-serif text-3xl font-bold">{t.errors.errorTitle}</h1>
      <p className="mt-3 text-muted">{t.errors.errorBody}</p>
      <button type="button" onClick={reset} className="mt-6 rounded-md bg-accent px-4 py-2 text-white dark:text-paper">{t.errors.tryAgain}</button>
    </div>
  );
}

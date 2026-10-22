"use client";

import { useState } from "react";
import { useI18n } from "@/i18n/client";

const PLATFORMS = (url: string, title: string, emailLabel: string) => {
  const u = encodeURIComponent(url);
  const t = encodeURIComponent(title);
  return [
    { key: "facebook", label: "Facebook", href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
    { key: "x", label: "X", href: `https://x.com/intent/post?url=${u}&text=${t}` },
    { key: "linkedin", label: "LinkedIn", href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}` },
    { key: "whatsapp", label: "WhatsApp", href: `https://wa.me/?text=${t}%20${u}` },
    { key: "telegram", label: "Telegram", href: `https://t.me/share/url?url=${u}&text=${t}` },
    { key: "messenger", label: "Messenger", href: `fb-messenger://share/?link=${u}` },
    { key: "email", label: emailLabel, href: `mailto:?subject=${t}&body=${u}` },
  ];
};

function track(id: string, platform: string) {
  void fetch(`/api/v1/investigations/${encodeURIComponent(id)}/share`, {
    method: "POST",
    credentials: "same-origin",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ platform }),
  }).catch(() => undefined);
}

export function ShareButtons({ id, url, title }: { id: string; url: string; title: string }) {
  const { t } = useI18n();
  const [copied, setCopied] = useState(false);
  return (
    <div>
      <h2 className="mb-2 text-sm font-semibold">{t.share.title}</h2>
      <ul className="flex flex-wrap gap-2">
        {PLATFORMS(url, title, t.share.email).map((p) => (
          <li key={p.key}>
            <a href={p.href} target="_blank" rel="noopener noreferrer" onClick={() => track(id, p.key)} className="inline-block rounded-md border border-line px-2.5 py-1 text-xs hover:bg-accent-soft">
              {p.label}
            </a>
          </li>
        ))}
        <li>
          <button
            type="button"
            className="rounded-md border border-line px-2.5 py-1 text-xs hover:bg-accent-soft"
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(url);
                setCopied(true);
                track(id, "copy_link");
                setTimeout(() => setCopied(false), 2000);
              } catch {
                setCopied(false);
              }
            }}
          >
            {copied ? t.share.copied : t.share.copy}
          </button>
          <span className="sr-only" aria-live="polite">{copied ? t.share.copiedAnnounce : ""}</span>
        </li>
      </ul>
    </div>
  );
}

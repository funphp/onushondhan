"use client";

import { useId, useRef, type KeyboardEvent } from "react";
import { cn } from "./cn";

/** WAI-ARIA tabs with arrow-key navigation. Panels are rendered by the caller. */
export function Tabs({ tabs, active, onChange, label }: { tabs: { key: string; label: string; badge?: string | number }[]; active: string; onChange: (key: string) => void; label: string }) {
  const id = useId();
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  const onKey = (e: KeyboardEvent, i: number) => {
    const dir = e.key === "ArrowRight" ? 1 : e.key === "ArrowLeft" ? -1 : 0;
    if (!dir) return;
    e.preventDefault();
    const next = (i + dir + tabs.length) % tabs.length;
    refs.current[next]?.focus();
    onChange(tabs[next].key);
  };

  return (
    <div role="tablist" aria-label={label} className="flex gap-1 overflow-x-auto border-b border-line">
      {tabs.map((t, i) => (
        <button
          key={t.key}
          ref={(el) => {
            refs.current[i] = el;
          }}
          role="tab"
          id={`${id}-${t.key}`}
          aria-selected={active === t.key}
          tabIndex={active === t.key ? 0 : -1}
          onKeyDown={(e) => onKey(e, i)}
          onClick={() => onChange(t.key)}
          className={cn("-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium", active === t.key ? "border-accent text-accent" : "border-transparent text-muted hover:text-ink")}
        >
          {t.label}
          {t.badge !== undefined && <span className="ml-2 rounded-full bg-accent-soft px-2 text-xs">{t.badge}</span>}
        </button>
      ))}
    </div>
  );
}

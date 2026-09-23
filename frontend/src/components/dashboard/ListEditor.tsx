"use client";

import type { ReactNode } from "react";
import { Button } from "@/components/ui/Button";

/** Ordered list editor: add, remove, reorder. Rendering of each row is delegated. */
export function ListEditor<T>({ items, onChange, empty, render, addLabel, itemLabel, max = 100 }: {
  items: T[];
  onChange: (items: T[]) => void;
  empty: () => T;
  render: (item: T, update: (patch: Partial<T>) => void, index: number) => ReactNode;
  addLabel: string;
  itemLabel: (item: T, index: number) => string;
  max?: number;
}) {
  const move = (i: number, d: number) => {
    const j = i + d;
    if (j < 0 || j >= items.length) return;
    const copy = [...items];
    [copy[i], copy[j]] = [copy[j], copy[i]];
    onChange(copy);
  };
  return (
    <div className="space-y-3">
      <ol className="space-y-3">
        {items.map((item, i) => (
          <li key={i} className="rounded-lg border border-line bg-paper p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
              <span className="text-sm font-medium">{itemLabel(item, i)}</span>
              <span className="flex gap-1">
                <Button type="button" size="sm" variant="ghost" onClick={() => move(i, -1)} disabled={i === 0} aria-label={`Move ${itemLabel(item, i)} up`}>↑</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => move(i, 1)} disabled={i === items.length - 1} aria-label={`Move ${itemLabel(item, i)} down`}>↓</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => onChange(items.filter((_, k) => k !== i))} aria-label={`Remove ${itemLabel(item, i)}`}>Remove</Button>
              </span>
            </div>
            {render(item, (patch) => onChange(items.map((x, k) => (k === i ? { ...x, ...patch } : x))), i)}
          </li>
        ))}
      </ol>
      {items.length < max && <Button type="button" variant="secondary" size="sm" onClick={() => onChange([...items, empty()])}>{addLabel}</Button>}
    </div>
  );
}

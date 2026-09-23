"use client";

import { useEffect, useRef, type ReactNode } from "react";

/** Accessible modal built on <dialog> (focus trap, Esc to close, inert background). */
export function Modal({ open, onClose, title, children, footer }: { open: boolean; onClose: () => void; title: string; children: ReactNode; footer?: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);

  return (
    <dialog
      ref={ref}
      onClose={onClose}
      aria-labelledby="modal-title"
      className="m-auto w-[min(640px,calc(100vw-2rem))] rounded-lg border border-line bg-surface p-0 text-ink backdrop:bg-black/50"
    >
      <div className="flex items-center justify-between border-b border-line px-5 py-3">
        <h2 id="modal-title" className="text-lg font-semibold">{title}</h2>
        <button type="button" onClick={onClose} className="rounded p-1 hover:bg-accent-soft" aria-label="Close dialog">✕</button>
      </div>
      <div className="max-h-[70vh] overflow-y-auto px-5 py-4">{children}</div>
      {footer && <div className="flex justify-end gap-2 border-t border-line px-5 py-3">{footer}</div>}
    </dialog>
  );
}

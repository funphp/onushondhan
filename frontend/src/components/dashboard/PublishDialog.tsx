"use client";

import { useEffect, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, TextArea } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { OwnerInvestigation } from "@/lib/types";

/** Explicit responsibility confirmation before every publication (first or update). */
export function PublishDialog({ open, onClose, investigation, beforePublish, onPublished }: {
  open: boolean;
  onClose: () => void;
  investigation: OwnerInvestigation;
  beforePublish: () => Promise<boolean>;
  onPublished: (inv: OwnerInvestigation) => void;
}) {
  const [statements, setStatements] = useState<{ key: string; text: string }[]>([]);
  const [checked, setChecked] = useState<Record<string, boolean>>({});
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const isUpdate = investigation.current_version > 0;

  useEffect(() => {
    if (!open) return;
    api<{ data: { key: string; text: string }[] }>("/me/investigations/confirmation-statements").then((r) => setStatements(r.data)).catch((e) => setError(errorMessage(e)));
  }, [open]);

  const close = () => {
    setChecked({});
    setError(null);
    setReason("");
    onClose();
  };

  const allChecked = statements.length > 0 && statements.every((s) => checked[s.key]);

  return (
    <Modal open={open} onClose={close} title={isUpdate ? "Publish update" : "Confirm responsibility and publish"} footer={
      <>
        <Button variant="ghost" onClick={close}>Cancel</Button>
        <Button loading={busy} disabled={!allChecked || (isUpdate && reason.trim().length < 3)} onClick={async () => {
          setBusy(true);
          setError(null);
          try {
            if (!(await beforePublish())) {
              setError("Your latest edits could not be saved. Resolve the problem and try again.");
              return;
            }
            const r = await api<{ data: OwnerInvestigation }>(`/me/investigations/${investigation.id}/publish`, { method: "POST", body: { confirmations: checked, change_reason: isUpdate ? reason : undefined } });
            onPublished(r.data);
          } catch (e) {
            setError(e instanceof ApiError ? e : errorMessage(e));
          } finally {
            setBusy(false);
          }
        }}>{isUpdate ? "Publish update" : "Publish"}</Button>
      </>
    }>
      <div className="space-y-4">
        <p className="text-sm">Before {isUpdate ? "this update is" : "your investigation is"} published, confirm each statement. Your confirmation is recorded with a timestamp and the agreement version in force.</p>
        <fieldset className="space-y-3">
          <legend className="sr-only">Responsibility statements</legend>
          {statements.map((s) => <Checkbox key={s.key} label={s.text} checked={!!checked[s.key]} onChange={(e) => setChecked({ ...checked, [s.key]: e.target.checked })} />)}
        </fieldset>
        {isUpdate && <TextArea label="What changed in this update?" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={1000} hint="Shown publicly in the version history." />}
        {error && (
          <Alert tone="danger" title={typeof error === "string" ? error : error.message}>
            {error instanceof ApiError && Object.keys(error.errors).length > 0 && (
              <ul className="list-disc pl-5">{Object.entries(error.errors).map(([k, v]) => <li key={k}>{v[0]}</li>)}</ul>
            )}
          </Alert>
        )}
      </div>
    </Modal>
  );
}

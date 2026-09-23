import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";
import { cn } from "./cn";

const control = "w-full rounded-md border border-line bg-surface px-3 py-2 text-ink placeholder:text-muted aria-[invalid=true]:border-danger";

interface FieldProps {
  label: ReactNode;
  hint?: ReactNode;
  error?: string;
  required?: boolean;
  children: (props: { id: string; "aria-describedby"?: string; "aria-invalid"?: boolean; required?: boolean }) => ReactNode;
  className?: string;
}

/** Label + control + hint + error, wired together for screen readers. */
export function Field({ label, hint, error, required, children, className }: FieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errId = error ? `${id}-err` : undefined;
  const describedBy = [hintId, errId].filter(Boolean).join(" ") || undefined;

  return (
    <div className={cn("flex flex-col gap-1", className)}>
      <label htmlFor={id} className="text-sm font-medium">
        {label}
        {required && <span className="text-danger" aria-hidden> *</span>}
      </label>
      {children({ id, "aria-describedby": describedBy, "aria-invalid": error ? true : undefined, required })}
      {hint && <p id={hintId} className="text-xs text-muted">{hint}</p>}
      {error && <p id={errId} role="alert" className="text-sm text-danger">{error}</p>}
    </div>
  );
}

export function TextInput({ label, hint, error, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; hint?: ReactNode; error?: string }) {
  return (
    <Field label={label} hint={hint} error={error} required={rest.required} className={className}>
      {(p) => <input {...rest} {...p} className={control} />}
    </Field>
  );
}

export function TextArea({ label, hint, error, className, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement> & { label: ReactNode; hint?: ReactNode; error?: string }) {
  return (
    <Field label={label} hint={hint} error={error} required={rest.required} className={className}>
      {(p) => <textarea {...rest} {...p} className={cn(control, "min-h-24")} />}
    </Field>
  );
}

export function SelectInput({ label, hint, error, className, options, placeholder, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { label: ReactNode; hint?: ReactNode; error?: string; options: { value: string; label: string }[]; placeholder?: string }) {
  return (
    <Field label={label} hint={hint} error={error} required={rest.required} className={className}>
      {(p) => (
        <select {...rest} {...p} className={control}>
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
      )}
    </Field>
  );
}

export function Checkbox({ label, error, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; error?: string }) {
  const id = useId();
  return (
    <div className={cn("flex flex-col gap-1", className)}>
      <div className="flex items-start gap-2">
        <input type="checkbox" id={id} {...rest} aria-invalid={error ? true : undefined} aria-describedby={error ? `${id}-err` : undefined} className="mt-1 h-4 w-4 accent-[var(--accent)]" />
        <label htmlFor={id} className="text-sm leading-6">{label}</label>
      </div>
      {error && <p id={`${id}-err`} role="alert" className="text-sm text-danger">{error}</p>}
    </div>
  );
}

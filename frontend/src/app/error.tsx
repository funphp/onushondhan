"use client";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div role="alert" className="mx-auto max-w-xl py-16 text-center">
      <h1 className="font-serif text-3xl font-bold">Something went wrong</h1>
      <p className="mt-3 text-muted">Please try again. If the problem continues, contact support.</p>
      <button type="button" onClick={reset} className="mt-6 rounded-md bg-accent px-4 py-2 text-white dark:text-paper">Try again</button>
    </div>
  );
}

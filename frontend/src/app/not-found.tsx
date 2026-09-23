import Link from "next/link";

export default function NotFound() {
  return (
    <div className="mx-auto max-w-xl py-16 text-center">
      <h1 className="font-serif text-3xl font-bold">Page not found</h1>
      <p className="mt-3 text-muted">This page does not exist or is not publicly available.</p>
      <p className="mt-6"><Link href="/" className="text-accent underline">Go to the homepage</Link> or <Link href="/search" className="text-accent underline">search investigations</Link>.</p>
    </div>
  );
}

import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { safeHref } from "@/lib/format";

/**
 * Renders user-authored Markdown safely: raw HTML is never rendered (no rehype-raw),
 * links are limited to http(s) and open with rel="noopener noreferrer ugc nofollow",
 * and images are not embedded from arbitrary hosts.
 */
export function Markdown({ children, className = "prose-report" }: { children: string | null | undefined; className?: string }) {
  if (!children) return null;
  return (
    <div className={className}>
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        skipHtml
        components={{
          a: ({ href, children: c }) => {
            const safe = safeHref(href);
            return safe ? <a href={safe} rel="noopener noreferrer ugc nofollow" target="_blank">{c}</a> : <span>{c}</span>;
          },
          img: ({ alt }) => <span className="text-muted">[image: {alt ?? "external image not displayed"}]</span>,
          h1: ({ children: c }) => <h2>{c}</h2>,
        }}
      >
        {children}
      </ReactMarkdown>
    </div>
  );
}

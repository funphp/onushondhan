import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import type { Element } from "hast";
import { safeHref } from "@/lib/format";
import { parseYouTube } from "@/lib/youtube";
import { YouTubeEmbed } from "./YouTubeEmbed";

/** A paragraph that is nothing but a YouTube link becomes an embedded (click-to-play) video. */
function videoIn(node: Element | undefined) {
  const kids = (node?.children ?? []).filter((c) => !(c.type === "text" && !c.value.trim()));
  const only = kids.length === 1 ? kids[0] : null;
  return only && only.type === "element" && only.tagName === "a" ? parseYouTube(String(only.properties?.href ?? "")) : null;
}

/**
 * Renders user-authored Markdown safely: raw HTML is never rendered (no rehype-raw),
 * links are limited to http(s) and open with rel="noopener noreferrer ugc nofollow",
 * images are not embedded from arbitrary hosts, and a YouTube link on its own line becomes a
 * click-to-play video.
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
          img: ({ alt }) => <span className="text-muted">[🖼 {alt ?? ""}]</span>,
          h1: ({ children: c }) => <h2>{c}</h2>,
          p: ({ node, children: c }) => {
            const video = videoIn(node);
            return video ? <YouTubeEmbed video={video} /> : <p>{c}</p>;
          },
        }}
      >
        {children}
      </ReactMarkdown>
    </div>
  );
}

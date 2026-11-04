/**
 * YouTube links in report bodies. A video is stored in the Markdown as a YouTube URL on a line
 * of its own; the public page turns it into a click-to-play player (nothing is loaded from
 * YouTube except the thumbnail until the reader presses play).
 */
export interface YouTubeRef {
  id: string;
  start: number;
}

const ID = /^[A-Za-z0-9_-]{11}$/;
const HOSTS = new Set(["youtube.com", "www.youtube.com", "m.youtube.com", "music.youtube.com", "youtu.be", "www.youtube-nocookie.com", "youtube-nocookie.com"]);

/** "90", "90s", "1m30s", "1h2m3s" → seconds. */
function parseStart(value: string | null): number {
  if (!value) return 0;
  if (/^\d+$/.test(value)) return Number(value);
  const m = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(value);
  return m ? Number(m[1] ?? 0) * 3600 + Number(m[2] ?? 0) * 60 + Number(m[3] ?? 0) : 0;
}

export function parseYouTube(input: string | null | undefined): YouTubeRef | null {
  if (!input) return null;
  let url: URL;
  try {
    url = new URL(input.trim());
  } catch {
    return null;
  }
  if (url.protocol !== "https:" && url.protocol !== "http:") return null;
  const host = url.hostname.toLowerCase();
  if (!HOSTS.has(host)) return null;

  let id: string | null = null;
  if (host === "youtu.be") {
    id = url.pathname.slice(1).split("/")[0] ?? null;
  } else if (url.pathname === "/watch") {
    id = url.searchParams.get("v");
  } else {
    const m = /^\/(?:embed|shorts|live|v)\/([^/?#]+)/.exec(url.pathname);
    id = m?.[1] ?? null;
  }
  if (!id || !ID.test(id)) return null;
  return { id, start: parseStart(url.searchParams.get("t") ?? url.searchParams.get("start")) };
}

/** Canonical link stored in the report body. */
export function youtubeUrl({ id, start }: YouTubeRef): string {
  return `https://www.youtube.com/watch?v=${id}${start > 0 ? `&t=${start}s` : ""}`;
}

export function youtubeThumbnail(id: string): string {
  return `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
}

export function youtubeEmbedUrl({ id, start }: YouTubeRef): string {
  return `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0${start > 0 ? `&start=${start}` : ""}`;
}

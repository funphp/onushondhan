"use client";

import { useState } from "react";
import { useI18n } from "@/i18n/client";
import { youtubeEmbedUrl, youtubeThumbnail, youtubeUrl, type YouTubeRef } from "@/lib/youtube";

/**
 * Click-to-play YouTube video. Until the reader presses play only the thumbnail image is
 * fetched; the player then loads from youtube-nocookie.com (YouTube's privacy-enhanced mode).
 */
export function YouTubeEmbed({ video }: { video: YouTubeRef }) {
  const { t } = useI18n();
  const [playing, setPlaying] = useState(false);

  return (
    <figure className="not-prose my-6">
      <div className="relative aspect-video overflow-hidden bg-black">
        {playing ? (
          <iframe
            src={youtubeEmbedUrl(video)}
            title={t.video.player}
            className="absolute inset-0 h-full w-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerPolicy="strict-origin-when-cross-origin"
            allowFullScreen
          />
        ) : (
          <button type="button" onClick={() => setPlaying(true)} className="group absolute inset-0 h-full w-full" aria-label={t.video.play}>
            {/* eslint-disable-next-line @next/next/no-img-element -- YouTube thumbnail; next/image is not used */}
            <img src={youtubeThumbnail(video.id)} alt="" loading="lazy" className="h-full w-full object-cover opacity-90 transition-opacity group-hover:opacity-100" />
            <span aria-hidden className="absolute left-1/2 top-1/2 flex h-16 w-24 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-2xl bg-[#e00] shadow-lg transition-transform group-hover:scale-110">
              <svg viewBox="0 0 24 24" width="30" height="30" fill="#fff"><path d="M8 5.5v13l11-6.5z" /></svg>
            </span>
          </button>
        )}
      </div>
      <figcaption className="mt-2 flex items-center justify-between gap-3 font-sans text-xs text-muted">
        <span>{t.video.caption}</span>
        <a href={youtubeUrl(video)} target="_blank" rel="noopener noreferrer nofollow" className="underline">{t.video.openOnYouTube}</a>
      </figcaption>
    </figure>
  );
}

import { mergeAttributes, Node } from "@tiptap/core";
import { Plugin, PluginKey } from "@tiptap/pm/state";
import { parseYouTube, youtubeThumbnail, youtubeUrl } from "@/lib/youtube";

declare module "@tiptap/core" {
  interface Commands<ReturnType> {
    youtubeVideo: {
      /** Insert a YouTube video block from any YouTube link; returns false for other links. */
      insertYouTube: (url: string) => ReturnType;
    };
  }
}

/** Matches a line holding only a YouTube link (optionally in <…>), at the start of `src`. */
const LINE = /^ {0,3}<?(https?:\/\/(?:www\.|m\.|music\.)?(?:youtube\.com|youtu\.be)\/[^\s>]+)>? *(?:\n+|$)/;

/**
 * A YouTube video inside the report body. Stored in Markdown as the video's URL on its own line
 * (so the public renderer and other tools understand it); shown in the editor as its thumbnail.
 */
export const YouTubeVideo = Node.create({
  name: "youtubeVideo",
  group: "block",
  atom: true,
  draggable: true,
  selectable: true,

  addAttributes() {
    return { src: { default: null } };
  },

  parseHTML() {
    return [{ tag: "figure[data-youtube]", getAttrs: (el) => ({ src: (el as HTMLElement).getAttribute("data-youtube") }) }];
  },

  renderHTML({ node, HTMLAttributes }) {
    const video = parseYouTube(node.attrs.src as string);
    return [
      "figure",
      mergeAttributes(HTMLAttributes, { "data-youtube": node.attrs.src, class: "yt-editor", contenteditable: "false" }),
      ["img", { src: video ? youtubeThumbnail(video.id) : "", alt: "", draggable: "false" }],
      ["span", { class: "yt-editor-play", "aria-hidden": "true" }],
      ["figcaption", {}, String(node.attrs.src ?? "")],
    ];
  },

  addCommands() {
    return {
      insertYouTube:
        (url) =>
        ({ commands }) => {
          const video = parseYouTube(url);
          return video ? commands.insertContent({ type: this.name, attrs: { src: youtubeUrl(video) } }) : false;
        },
    };
  },

  /** Pasting a YouTube link on its own embeds the video at the cursor. */
  addProseMirrorPlugins() {
    const editor = this.editor;
    return [
      new Plugin({
        key: new PluginKey("youtubePaste"),
        props: {
          handlePaste: (_view, event) => {
            const text = event.clipboardData?.getData("text/plain")?.trim() ?? "";
            if (!text || /\s/.test(text) || !parseYouTube(text)) return false;
            return editor.commands.insertYouTube(text);
          },
        },
      }),
    ];
  },

  markdownTokenName: "youtubeVideo",
  markdownTokenizer: {
    name: "youtubeVideo",
    level: "block",
    start: (src: string) => {
      const m = /(^|\n) {0,3}<?https?:\/\/(?:www\.|m\.|music\.)?(?:youtube\.com|youtu\.be)\//.exec(src);
      return m ? m.index + m[1].length : -1;
    },
    tokenize: (src: string) => {
      const m = LINE.exec(src);
      if (!m) return undefined;
      const video = parseYouTube(m[1]);
      return video ? { type: "youtubeVideo", raw: m[0], src: youtubeUrl(video) } : undefined;
    },
  },
  parseMarkdown: (token) => ({ type: "youtubeVideo", attrs: { src: token.src } }),
  renderMarkdown: (node) => String(node.attrs?.src ?? ""),
});

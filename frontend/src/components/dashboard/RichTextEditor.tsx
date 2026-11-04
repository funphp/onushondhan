"use client";

import { Markdown } from "@tiptap/markdown";
import { EditorContent, useEditor, useEditorState, type Editor } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import { Placeholder } from "@tiptap/extensions";
import { useId, type ReactNode } from "react";
import { cn } from "@/components/ui/cn";
import { useI18n } from "@/i18n/client";
import { safeHref } from "@/lib/format";
import { YouTubeVideo } from "./YouTubeNode";

/**
 * Word-processor style editor for the report body. Content is stored as Markdown (the same
 * format the public page renders without raw HTML), so only formatting that Markdown can
 * express is offered: headings, bold/italic, lists, quotes, links and dividers — plus YouTube
 * videos, stored as a YouTube link on its own line (paste a YouTube link anywhere to embed it).
 */
export function RichTextEditor({ label, value, onChange, hint, error, disabled, placeholder }: {
  label: ReactNode; value: string; onChange: (markdown: string) => void; hint?: ReactNode; error?: string; disabled?: boolean; placeholder?: string;
}) {
  const id = useId();
  const editor = useEditor({
    immediatelyRender: false,
    editable: !disabled,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        underline: false, // not expressible in Markdown
        link: { openOnClick: false, autolink: true, protocols: ["http", "https"], defaultProtocol: "https", isAllowedUri: (url) => safeHref(url) !== null },
      }),
      Markdown,
      YouTubeVideo,
      Placeholder.configure({ placeholder: placeholder ?? "" }),
    ],
    content: value,
    contentType: "markdown",
    editorProps: {
      attributes: {
        id,
        role: "textbox",
        "aria-multiline": "true",
        "aria-labelledby": `${id}-label`,
        ...(hint ? { "aria-describedby": `${id}-hint` } : {}),
        ...(error ? { "aria-invalid": "true" } : {}),
        class: "prose-report min-h-[28rem] px-4 py-3 focus:outline-none",
      },
    },
    onUpdate: ({ editor: e }) => onChange(e.getMarkdown()),
  });

  return (
    <div className="flex flex-col gap-1">
      <span id={`${id}-label`} className="text-sm font-medium">{label}</span>
      <div className={cn("rounded-md border bg-surface focus-within:ring-2 focus-within:ring-accent/40", error ? "border-danger" : "border-line", disabled && "opacity-70")}>
        {editor && !disabled && <Toolbar editor={editor} />}
        <EditorContent editor={editor} />
        {!editor && <div className="min-h-[28rem]" aria-hidden />}
      </div>
      {hint && <p id={`${id}-hint`} className="text-xs text-muted">{hint}</p>}
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
    </div>
  );
}

function Toolbar({ editor }: { editor: Editor }) {
  const { t } = useI18n();
  const tb = t.editor.toolbar;
  const s = useEditorState({
    editor,
    selector: ({ editor: e }) => ({
      h2: e.isActive("heading", { level: 2 }), h3: e.isActive("heading", { level: 3 }),
      bold: e.isActive("bold"), italic: e.isActive("italic"), strike: e.isActive("strike"),
      bullet: e.isActive("bulletList"), ordered: e.isActive("orderedList"), quote: e.isActive("blockquote"), link: e.isActive("link"),
      canUndo: e.can().undo(), canRedo: e.can().redo(),
    }),
  });

  const setLink = () => {
    if (s.link) {
      editor.chain().focus().extendMarkRange("link").unsetLink().run();
      return;
    }
    const input = window.prompt(tb.linkPrompt, "https://");
    if (input === null) return;
    const href = safeHref(input.trim());
    if (!href) {
      window.alert(tb.linkInvalid);
      return;
    }
    const chain = editor.chain().focus().extendMarkRange("link");
    if (editor.state.selection.empty) chain.insertContent({ type: "text", text: href, marks: [{ type: "link", attrs: { href } }] }).run();
    else chain.setLink({ href }).run();
  };

  const addVideo = () => {
    const input = window.prompt(tb.youtubePrompt, "https://www.youtube.com/watch?v=");
    if (input === null) return;
    if (!editor.chain().focus().insertYouTube(input.trim()).run()) window.alert(tb.youtubeInvalid);
  };

  const c = () => editor.chain().focus();
  const buttons: ({ label: string; icon: ReactNode; on?: boolean; run: () => void; disabled?: boolean } | "sep")[] = [
    { label: tb.heading, icon: <span className="font-bold">H2</span>, on: s.h2, run: () => c().toggleHeading({ level: 2 }).run() },
    { label: tb.subheading, icon: <span className="font-bold">H3</span>, on: s.h3, run: () => c().toggleHeading({ level: 3 }).run() },
    "sep",
    { label: tb.bold, icon: <span className="font-bold">B</span>, on: s.bold, run: () => c().toggleBold().run() },
    { label: tb.italic, icon: <span className="font-serif italic">I</span>, on: s.italic, run: () => c().toggleItalic().run() },
    { label: tb.strike, icon: <span className="line-through">S</span>, on: s.strike, run: () => c().toggleStrike().run() },
    { label: s.link ? tb.unlink : tb.link, icon: <Icon d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" />, on: s.link, run: setLink },
    "sep",
    { label: tb.bulletList, icon: <Icon d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01" />, on: s.bullet, run: () => c().toggleBulletList().run() },
    { label: tb.orderedList, icon: <Icon d="M10 6h10M10 12h10M10 18h10M4 4.5h1v4M4 8.5h2M4 14.5a1 1 0 0 1 2 0c0 1-2 1.5-2 3h2" />, on: s.ordered, run: () => c().toggleOrderedList().run() },
    { label: tb.quote, icon: <Icon d="M7 7h4v6H5v-2a4 4 0 0 1 2-4m8 0h4v6h-6v-2a4 4 0 0 1 2-4" />, on: s.quote, run: () => c().toggleBlockquote().run() },
    { label: tb.divider, icon: <Icon d="M4 12h16" />, run: () => c().setHorizontalRule().run() },
    { label: tb.youtube, icon: <Icon d="M3 8.5a3 3 0 0 1 2.6-3C7.6 5.2 9.8 5 12 5s4.4.2 6.4.5A3 3 0 0 1 21 8.5v7a3 3 0 0 1-2.6 3c-2 .3-4.2.5-6.4.5s-4.4-.2-6.4-.5A3 3 0 0 1 3 15.5zM10 9.5v5l4.5-2.5z" />, run: addVideo },
    "sep",
    { label: tb.undo, icon: <Icon d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3" />, run: () => c().undo().run(), disabled: !s.canUndo },
    { label: tb.redo, icon: <Icon d="m15 14 5-5-5-5M20 9H9a5 5 0 0 0 0 10h3" />, run: () => c().redo().run(), disabled: !s.canRedo },
  ];

  return (
    <div role="toolbar" aria-label={tb.label} className="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 rounded-t-md border-b border-line bg-surface p-1.5">
      {buttons.map((b, i) =>
        b === "sep" ? <span key={i} aria-hidden className="mx-1 h-5 w-px bg-line" /> : (
          <button key={b.label} type="button" title={b.label} aria-label={b.label} aria-pressed={b.on === undefined ? undefined : b.on} disabled={b.disabled}
            onMouseDown={(e) => e.preventDefault()} onClick={b.run}
            className={cn("inline-flex h-8 min-w-8 items-center justify-center rounded px-1.5 text-sm text-ink hover:bg-accent-soft disabled:opacity-40", b.on && "bg-accent-soft text-accent")}>
            {b.icon}
          </button>
        ),
      )}
    </div>
  );
}

function Icon({ d }: { d: string }) {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      <path d={d} />
    </svg>
  );
}

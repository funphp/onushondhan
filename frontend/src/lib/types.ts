// Types mirroring the Laravel API resources (/api/v1). Keep in sync with backend/app/Http/Resources.

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number; per_page?: number; [k: string]: unknown };
  links?: Record<string, string | null>;
}

export interface ApiErrorBody {
  message: string;
  code: string;
  errors: Record<string, string[]>;
  request_id?: string;
}

export interface InvestigatorSummary {
  id: string;
  public_name: string;
  slug: string;
  identity_mode: "real_name" | "protected_public_identity";
  avatar_url: string | null;
  supporters_count: number;
  followers_count: number;
  published_investigations_count: number;
}

export interface InvestigatorProfile extends InvestigatorSummary {
  bio: string | null;
  website_url: string | null;
  total_views: number;
  joined_at: string;
  canonical_url: string;
  viewer: { following: boolean; supporting: boolean; is_self: boolean } | null;
}

export interface TaxonomyRef {
  name: string;
  slug: string;
}

export interface Counts {
  views: number;
  shares: number;
  comments: number;
  bookmarks: number;
  reactions: number;
  tips?: number;
}

export interface InvestigationCard {
  id: string;
  slug: string;
  title: string;
  summary: string;
  status: string;
  category: TaxonomyRef | null;
  tags: TaxonomyRef[];
  location_name: string | null;
  location_country: string | null;
  investigator: InvestigatorSummary | null;
  published_at: string | null;
  updated_at: string | null;
  version: number;
  counts: Counts;
  url: string;
}

export interface Evidence {
  id: string;
  kind: "image" | "video" | "audio" | "pdf" | "document";
  mime_type: string;
  extension: string;
  size_bytes: number;
  sha256: string;
  caption: string | null;
  display_name: string;
  uploaded_at: string;
  version: number;
  metadata: { width?: number; height?: number; pages?: number };
  has_preview: boolean;
  preview_url: string | null;
  download_url: string;
  // Owner view only:
  original_name?: string;
  visibility?: "public" | "restricted" | "private";
  scan_status?: "pending" | "clean" | "infected" | "failed" | "not_scanned";
  scan_message?: string | null;
  moderation_status?: string;
  private_metadata?: Record<string, unknown>;
}

export interface Finding {
  title: string;
  body: string | null;
}

export interface TimelineItem {
  event_date: string | null;
  title: string;
  description: string | null;
}

export interface SourceItem {
  title: string;
  source_type: string | null;
  publisher: string | null;
  url: string | null;
  citation_text: string | null;
  accessed_at: string | null;
  is_public?: boolean;
}

export interface Entity {
  name: string;
  type: string;
  role: string | null;
}

export interface PublicInvestigation {
  id: string;
  slug: string;
  url: string;
  canonical_url: string;
  status: "published" | "archived" | "withdrawn" | "restricted" | "removed" | "draft";
  visibility: "public" | "unlisted" | "private";
  is_preview: boolean;
  robots: { index: boolean; follow: boolean };
  title: string;
  summary: string | null;
  body: string | null;
  findings: Finding[];
  recommendations: string[];
  timeline: TimelineItem[];
  sources: SourceItem[];
  entities: Entity[];
  category: TaxonomyRef | null;
  subcategory: TaxonomyRef | null;
  tags: TaxonomyRef[];
  locations: { country: string | null; region: string | null; district: string | null; city: string | null; area: string | null }[];
  location_name: string | null;
  location_country: string | null;
  period_start: string | null;
  period_end: string | null;
  investigator: InvestigatorSummary;
  published_at: string | null;
  updated_at: string | null;
  version: number;
  change_reason: string | null;
  counts: Counts;
  comments_locked: boolean;
  evidence: Evidence[];
  notice: { type: string; message: string; detail: string | null; date: string | null } | null;
  disclaimer: string;
  viewer: { bookmarked: boolean; reaction: string | null; following: boolean; supporting: boolean; is_owner: boolean } | null;
}

export interface OwnerInvestigation {
  id: string;
  slug: string | null;
  title: string;
  status: PublicInvestigation["status"];
  visibility: PublicInvestigation["visibility"];
  revision: number;
  current_version: number;
  has_unpublished_changes: boolean;
  published_at: string | null;
  last_published_at: string | null;
  withdrawn_at: string | null;
  archived_at: string | null;
  public_status_note: string | null;
  comments_locked: boolean;
  updated_at: string;
  created_at: string;
  counts: Counts;
  public_url: string | null;
  summary?: string | null;
  body?: string | null;
  category?: string | null;
  subcategory?: string | null;
  tags?: string[];
  location_name?: string | null;
  location_country?: string | null;
  location_lat?: number | null;
  location_lng?: number | null;
  period_start?: string | null;
  period_end?: string | null;
  recommendations?: string[];
  entities?: Entity[];
  findings?: Finding[];
  timeline?: TimelineItem[];
  sources?: SourceItem[];
  locations?: { country: string | null; region: string | null; district: string | null; city: string | null; area: string | null }[];
  evidence?: Evidence[];
}

export interface Category extends TaxonomyRef {
  description: string | null;
  parent?: string | null;
  children?: Category[];
  investigations_count?: number;
}

export interface Me {
  id: string;
  name: string;
  username: string;
  email: string;
  email_verified: boolean;
  phone: string | null;
  phone_verified: boolean;
  status: string;
  roles: string[];
  permissions: string[];
  is_staff: boolean;
  investigator: { id: string; public_name: string; slug: string; identity_mode: string; status: string } | null;
  pending_agreements: { id: number; agreement_type: string; version: string; title: string }[];
  notification_preferences: { email?: Record<string, boolean> };
  unread_notifications: number;
  created_at: string;
}

export interface Comment {
  id: string;
  body: string | null;
  placeholder: string | null;
  status: string;
  author: { name: string; role: "member" | "investigator"; slug: string | null } | null;
  is_pinned: boolean;
  score: number;
  replies_count: number;
  parent_id: string | null;
  created_at: string;
  edited_at: string | null;
  viewer: { vote: number; is_author: boolean } | null;
  replies: Comment[];
}

export interface CmsPage {
  slug: string;
  title: string;
  excerpt: string | null;
  body: string | null;
  page_type: string;
  requires_legal_review: boolean;
  seo_title: string | null;
  seo_description: string | null;
  canonical_url: string | null;
  published_at: string | null;
  updated_at: string;
}

export interface Agreement {
  id: number;
  agreement_type: string;
  version: string;
  title: string;
  content: string | null;
  effective_at: string | null;
  published_at: string | null;
}

export interface Tip {
  id: string;
  subject: string;
  status: "new" | "read" | "important" | "archived" | "closed";
  investigation: { id: string; title: string } | null;
  investigator: { public_name: string; slug: string };
  created_at: string;
  last_message_at: string | null;
  unread?: boolean | null;
  sender?: { anonymous: boolean; type: "guest" | "member"; username: string | null };
  contact_preference?: string;
  contact_value?: string | null;
  anonymous_to_investigator?: boolean;
  message?: string;
  attachments?: { id: string; name: string; mime_type: string; size_bytes: number; sha256: string; scan_status: string; download_url: string | null }[];
  messages?: { from: "tipster" | "investigator"; body: string; created_at: string; read_at: string | null }[];
}

export interface Notification {
  id: string;
  type: string;
  data: { kind: string; title: string; url: string | null; [k: string]: unknown };
  read_at: string | null;
  created_at: string | null;
}

export interface LeaderboardResponse {
  board: string;
  label: string;
  note: string;
  boards: { key: string; label: string }[];
  data: { rank: number; score: number; investigator: InvestigatorSummary }[];
}

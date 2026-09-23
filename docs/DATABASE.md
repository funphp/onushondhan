# Database Specification — MySQL 8+

## Principles
- InnoDB.
- UTF8MB4.
- UTC timestamps in storage.
- Foreign keys for integrity where practical.
- Unique constraints for idempotency.
- Index actual query paths.
- Prefer ULID/UUID public IDs in addition to numeric/internal keys where useful.

## Core tables

### users
Fields: id, public_id, name, email, email_verified_at, password, phone, phone_verified_at, status, last_login_at, created_at, updated_at, deleted_at.

Constraints: unique email; unique public_id.

### roles
id, name, slug, description, timestamps. Unique slug.

### permissions
id, name, slug, description, timestamps. Unique slug.

### role_permissions
role_id, permission_id. Composite primary/unique key.

### user_roles
user_id, role_id. Composite unique key.

### investigators
id, user_id, public_id, public_name, slug, bio, avatar_path, website_url, identity_mode, status, published_at, timestamps, deleted_at.

Identity mode: real_name | protected_public_identity.

Unique: user_id, public_id, slug.

### investigator_private_identity
id, investigator_id, legal_or_private_name, private_email, private_phone, notes, timestamps. This table is never exposed in public API resources.

### terms_versions
id, agreement_type, version, title, content, effective_at, published_at, status, created_by, timestamps. Unique agreement_type + version.

### terms_acceptances
id, user_id, agreement_type, terms_version_id, accepted_at, ip_address, user_agent, metadata_json, created_at. Index user_id + agreement_type + accepted_at.

### investigation_categories
id, parent_id, name, slug, description, sort_order, status, timestamps.

### investigations
id, public_id, investigator_id, category_id, title, slug, summary, body, location_name, location_country, location_lat, location_lng, period_start, period_end, identity_snapshot, status, visibility, published_at, withdrawn_at, archived_at, current_version, created_at, updated_at, deleted_at.

Statuses: draft | published | withdrawn | archived | restricted | removed.

Visibility: public | unlisted | private.

Unique public_id and slug among active records as appropriate.

### investigation_versions
id, investigation_id, version_no, title, summary, body, findings_json, recommendations_json, identity_snapshot, changed_by, change_reason, created_at. Unique investigation_id + version_no.

### investigation_tags
id, name, slug, timestamps. Unique slug.

### investigation_tag_map
investigation_id, tag_id. Composite unique key.

### investigation_locations
id, investigation_id, country, region, district, city, area, latitude, longitude, is_primary, timestamps.

### findings
id, investigation_id, title, body, sort_order, status, timestamps.

### investigation_timelines
id, investigation_id, event_date, title, description, sort_order, timestamps.

### sources
id, investigation_id, title, source_type, publisher, url, citation_text, accessed_at, is_public, sort_order, timestamps.

Do not automatically imply that a linked source endorses the report's conclusions.

### evidence
id, public_id, investigation_id, investigator_id, original_name, storage_disk, storage_path, mime_type, extension, size_bytes, sha256, scan_status, scan_message, metadata_json, visibility, caption, sort_order, uploaded_at, created_at, updated_at, deleted_at.

Visibility: public | restricted | private.
Scan status: pending | clean | infected | failed | not_scanned.

### evidence_versions
id, evidence_id, version_no, storage_path, size_bytes, sha256, mime_type, metadata_json, scan_status, created_at. Unique evidence_id + version_no.

### evidence_access_logs
id, evidence_id, user_id nullable, action, ip_address, user_agent, success, created_at. Index evidence_id + created_at.

Actions: preview | download | stream | metadata_view.

### comments
id, public_id, investigation_id, user_id, parent_id nullable, body, status, edited_at, deleted_at, created_at, updated_at.

Statuses: visible | hidden | locked | removed | pending.

### comment_votes
comment_id, user_id, value, created_at, updated_at. Unique comment_id + user_id.

### comment_reports
id, comment_id, reporter_user_id, reason, details, status, reviewed_by, reviewed_at, created_at, updated_at.

### investigator_supports
id, user_id, investigator_id, status, created_at, revoked_at. Unique active user_id + investigator_id strategy must be enforced in DB/application so one user cannot generate multiple active supports.

### investigator_followers
id, user_id, investigator_id, created_at. Unique user_id + investigator_id.

### bookmarks
id, user_id, investigation_id, created_at. Unique user_id + investigation_id.

### reactions
id, user_id, investigation_id, reaction_type, created_at, updated_at. Unique user_id + investigation_id; reaction types such as useful/important/interesting.

### tips
id, public_id, investigation_id nullable, investigator_id, sender_user_id nullable, anonymous_to_investigator, subject, message, contact_preference, status, last_message_at, created_at, updated_at.

Statuses: new | read | important | archived | closed.

### tip_attachments
id, tip_id, storage_path, original_name, mime_type, size_bytes, sha256, scan_status, created_at, deleted_at.

### tip_messages
id, tip_id, sender_user_id nullable, sender_type, body, created_at, read_at.

### notifications
id, user_id, type, data_json, read_at, created_at.

### complaints
id, public_id, investigation_id nullable, comment_id nullable, evidence_id nullable, reporter_user_id, complaint_type, description, status, reviewed_by, reviewed_at, resolution, created_at, updated_at.

Complaint types: factual_dispute | privacy | copyright | personal_information | prohibited_content | harassment | spam | other_terms_violation.

Statuses: submitted | under_review | action_requested | resolved | dismissed | escalated.

### moderation_actions
id, moderator_user_id, target_type, target_id, action, reason, details, created_at.

### shares
id, user_id nullable, investigation_id, platform, referrer, created_at.

Platforms: facebook | x | linkedin | whatsapp | telegram | messenger | email | copy_link | other.

### analytics_events
id, user_id nullable, session_id nullable, investigation_id nullable, investigator_id nullable, event_name, page_url, referrer, anonymous_fingerprint nullable, metadata_json, created_at.

Do not collect unnecessary identifiers. Define a retention policy.

### audit_logs
id, actor_user_id nullable, action, entity_type, entity_id, before_json nullable, after_json nullable, ip_address nullable, user_agent nullable, request_id nullable, created_at.

High-risk actions must be audited.

### login_logs
id, user_id nullable, email_attempted nullable, success, ip_address, user_agent, failure_reason nullable, created_at.

### security_events
id, user_id nullable, event_type, severity, ip_address nullable, user_agent nullable, metadata_json, created_at.

### cms_pages
id, slug, title, excerpt, body, status, seo_title, seo_description, canonical_url, published_at, created_by, updated_by, timestamps.

### system_settings
id, key, value_json, value_type, description, updated_by, timestamps. Never store secrets here.

## Query/index guidance

Create indexes for:
- public investigation discovery by status/visibility/published_at;
- investigator slug;
- category and published_at;
- location fields;
- comments by investigation/parent/status;
- tips by investigator/status;
- notifications by user/read_at;
- complaints by status/created_at;
- analytics events by event_name/created_at/investigation_id;
- audit logs by entity and actor/date;
- full-text investigation title/summary/body as supported by MySQL version.

Avoid arbitrary indexes on every column.

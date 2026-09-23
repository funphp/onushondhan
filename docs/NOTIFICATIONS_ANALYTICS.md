# Notifications & Analytics

## Notifications
Create in-app notifications for:
- new report from followed investigator;
- comment on user's investigation;
- reply to user's comment;
- comment vote;
- new tip;
- tip message;
- new follower;
- new supporter;
- investigation milestone;
- moderation/complaint status where applicable;
- terms requiring re-acceptance.

Email notifications should be configurable and must not leak private tip content in unsafe ways.

## Analytics events
Recommended events:
- page_view
- investigation_view
- investigation_share
- investigation_bookmark
- comment_created
- comment_voted
- support_created
- support_revoked
- investigator_followed
- tip_submitted
- tip_message_sent
- evidence_previewed
- evidence_downloaded
- search_performed
- investigation_published
- investigation_withdrawn

## Anti-abuse
Support/follow/share metrics can be manipulated. Use rate limiting, unique constraints, email verification, optional phone verification, anomaly flags, and audit logs. Do not present anti-abuse heuristics as proof of misconduct.

## Analytics privacy
Avoid storing raw IP addresses forever. Define retention and anonymization rules. Make event collection configurable and document any external analytics integration separately.

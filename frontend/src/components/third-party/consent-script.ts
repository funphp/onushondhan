import { CONSENT_COOKIE } from "@/lib/third-party";

/**
 * Inline bootstrap (runs before gtag.js / adsbygoogle.js): defines `gtag`, sets Google
 * Consent Mode v2 defaults to "denied", then applies a choice the visitor already made.
 * Without consent Analytics is never loaded and AdSense serves only non-personalised ads.
 */
export const CONSENT_BOOTSTRAP = `
window.dataLayer=window.dataLayer||[];
window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
gtag('set','ads_data_redaction',true);
try{var m=document.cookie.match(/(?:^|; )${CONSENT_COOKIE}=(granted|denied)/);if(m&&m[1]==='granted'){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});}}catch(e){}
`;

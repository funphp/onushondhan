"use client";

import { useApiList } from "@/components/account/useApiList";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { useI18n } from "@/i18n/client";
import type { InvestigatorSummary } from "@/lib/types";

export default function FollowingPage() {
  const { t } = useI18n();
  const following = useApiList<InvestigatorSummary>("/me/following");
  const supporting = useApiList<InvestigatorSummary>("/me/supporting");
  return (
    <div className="space-y-8">
      <h1 className="font-display text-3xl font-bold">{t.account.followingSupport}</h1>
      <section className="space-y-3">
        <h2 className="text-xl font-semibold">{t.account.following}</h2>
        {!following.items ? <Spinner /> : following.items.length === 0 ? <EmptyState title={t.account.notFollowing} /> : following.items.map((i) => <InvestigatorCard key={i.id} inv={i} />)}
      </section>
      <section className="space-y-3">
        <h2 className="text-xl font-semibold">{t.account.supporting}</h2>
        <p className="text-xs text-muted">{t.brand.supportNote} {t.account.supportOnce}</p>
        {!supporting.items ? <Spinner /> : supporting.items.length === 0 ? <EmptyState title={t.account.notSupporting} /> : supporting.items.map((i) => <InvestigatorCard key={i.id} inv={i} />)}
      </section>
    </div>
  );
}

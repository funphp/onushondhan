"use client";

import { useApiList } from "@/components/account/useApiList";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { SUPPORT_NOTE } from "@/lib/site";
import type { InvestigatorSummary } from "@/lib/types";

export default function FollowingPage() {
  const following = useApiList<InvestigatorSummary>("/me/following");
  const supporting = useApiList<InvestigatorSummary>("/me/supporting");
  return (
    <div className="space-y-8">
      <h1 className="font-serif text-3xl font-bold">Following &amp; support</h1>
      <section className="space-y-3">
        <h2 className="text-xl font-semibold">Following</h2>
        {!following.items ? <Spinner /> : following.items.length === 0 ? <EmptyState title="You are not following anyone yet." /> : following.items.map((i) => <InvestigatorCard key={i.id} inv={i} />)}
      </section>
      <section className="space-y-3">
        <h2 className="text-xl font-semibold">Supporting</h2>
        <p className="text-xs text-muted">{SUPPORT_NOTE} You can support each investigator once.</p>
        {!supporting.items ? <Spinner /> : supporting.items.length === 0 ? <EmptyState title="You are not supporting anyone yet." /> : supporting.items.map((i) => <InvestigatorCard key={i.id} inv={i} />)}
      </section>
    </div>
  );
}

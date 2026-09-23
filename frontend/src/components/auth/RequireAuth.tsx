"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { Spinner } from "@/components/ui/Card";
import { useAuth } from "./AuthProvider";

/**
 * Client-side guard for account areas. This is UX only — every API call is
 * authorised by the backend regardless.
 */
export function RequireAuth({ children, investigator }: { children: ReactNode; investigator?: boolean }) {
  const { user, loading } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (loading) return;
    if (!user) router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    else if (investigator && !user.investigator && pathname !== "/dashboard/onboarding") router.replace("/dashboard/onboarding");
  }, [user, loading, investigator, router, pathname]);

  if (loading || !user || (investigator && !user.investigator && pathname !== "/dashboard/onboarding")) {
    return <div className="py-16"><Spinner /></div>;
  }
  return <>{children}</>;
}

"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { api, ApiError } from "@/lib/api";
import type { Me } from "@/lib/types";

interface AuthState {
  user: Me | null;
  loading: boolean;
  refresh: () => Promise<Me | null>;
  setUser: (u: Me | null) => void;
  logout: () => Promise<void>;
  can: (permission: string) => boolean;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Me | null>(null);
  const [loading, setLoading] = useState(true);

  const inflight = useRef<Promise<unknown> | null>(null);

  const refresh = useCallback(async () => {
    try {
      const pending = api<{ data: Me }>("/auth/me");
      inflight.current = pending;
      const res = await pending;
      setUser(res.data);
      return res.data;
    } catch (e) {
      if (!(e instanceof ApiError) || e.status === 401 || e.status === 403) setUser(null);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    api<{ data: Me }>("/auth/me")
      .then((res) => setUser(res.data))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  const logout = useCallback(async () => {
    // Never race an in-flight /auth/me against logout: its response could re-set the old session cookie.
    await inflight.current?.catch(() => undefined);
    try {
      await api("/auth/logout", { method: "POST" });
    } finally {
      setUser(null);
    }
  }, []);

  const value = useMemo<AuthState>(
    () => ({
      user,
      loading,
      refresh,
      setUser,
      logout,
      can: (p) => !!user && (user.permissions.includes("*") || user.permissions.includes(p)),
    }),
    [user, loading, refresh, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}

"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useRouter } from "next/navigation";
import { api, getToken, setToken } from "./api";

interface JwtPayload {
  username: string;
  roles: string[];
  exp: number;
}

interface AuthState {
  email: string | null;
  roles: string[];
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthState | null>(null);

function decodeJwt(token: string): JwtPayload | null {
  try {
    const [, payload] = token.split(".");
    return JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/")));
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [email, setEmail] = useState<string | null>(null);
  const [roles, setRoles] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const token = getToken();
    const payload = token ? decodeJwt(token) : null;
    if (payload && payload.exp * 1000 > Date.now()) {
      // Reading a token stashed in localStorage at mount time and mirroring it
      // into state is synchronous by nature (no external async system to await).
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setEmail(payload.username);
      setRoles(payload.roles);
    } else {
      setToken(null);
    }
    setIsLoading(false);
  }, []);

  const login = useCallback(async (loginEmail: string, password: string) => {
    const { token } = await api.login(loginEmail, password);
    setToken(token);
    const payload = decodeJwt(token);
    setEmail(payload?.username ?? loginEmail);
    setRoles(payload?.roles ?? []);
  }, []);

  const logout = useCallback(() => {
    setToken(null);
    setEmail(null);
    setRoles([]);
    router.push("/login");
  }, [router]);

  const value = useMemo(
    () => ({ email, roles, isLoading, login, logout }),
    [email, roles, isLoading, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within an AuthProvider");
  return ctx;
}

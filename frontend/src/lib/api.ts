import type {
  Adherant,
  AdherantStatut,
  Admin,
  ImportResult,
  Paginated,
  VerifyResult,
} from "./types";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";
const TOKEN_KEY = "myassoc_token";

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public violations?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null): void {
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

async function request<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();
  const headers = new Headers(options.headers);
  if (!(options.body instanceof FormData) && options.body) {
    headers.set("Content-Type", "application/json");
  }
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const response = await fetch(`${API_URL}${path}`, { ...options, headers });

  if (response.status === 204) {
    return undefined as T;
  }

  const isJson = response.headers.get("content-type")?.includes("application/json");
  const payload = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const message =
      isJson && typeof payload === "object" && payload && "error" in payload
        ? (payload as { error: string }).error
        : `Erreur ${response.status}`;
    const violations =
      isJson && typeof payload === "object" && payload && "violations" in payload
        ? (payload as { violations: Record<string, string[]> }).violations
        : undefined;
    throw new ApiError(message, response.status, violations);
  }

  return payload as T;
}

export const api = {
  login: (email: string, password: string) =>
    request<{ token: string }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    }),

  listAdmins: () => request<Admin[]>("/admins"),
  createAdmin: (data: { email: string; password: string; nom: string; prenom: string }) =>
    request<Admin>("/admins", { method: "POST", body: JSON.stringify(data) }),
  deleteAdmin: (id: string) => request<void>(`/admins/${id}`, { method: "DELETE" }),

  listMembers: (params: { q?: string; statut?: AdherantStatut; page?: number; limit?: number }) => {
    const search = new URLSearchParams();
    if (params.q) search.set("q", params.q);
    if (params.statut) search.set("statut", params.statut);
    search.set("page", String(params.page ?? 1));
    search.set("limit", String(params.limit ?? 20));
    return request<Paginated<Adherant>>(`/members?${search.toString()}`);
  },

  getMember: (id: string) => request<Adherant>(`/members/${id}`),

  createMember: (data: Partial<Adherant> & { nom: string; prenom: string }) =>
    request<Adherant>("/members", { method: "POST", body: JSON.stringify(data) }),

  updateMember: (
    id: string,
    data: Partial<
      Pick<Adherant, "nom" | "prenom" | "email" | "telephone" | "adresse" | "dateNaissance" | "profession" | "commentaires">
    >,
  ) => request<Adherant>(`/members/${id}`, { method: "PATCH", body: JSON.stringify(data) }),

  renewMembership: (id: string, data: { dateDebut?: string; dateFin?: string } = {}) =>
    request<Adherant>(`/members/${id}/renew`, { method: "POST", body: JSON.stringify(data) }),

  cancelMembership: (id: string, motif?: string) =>
    request<Adherant>(`/members/${id}/cancel`, {
      method: "POST",
      body: JSON.stringify({ motif }),
    }),

  importMembers: (file: File) => {
    const formData = new FormData();
    formData.append("file", file);
    return request<ImportResult>("/members/import", { method: "POST", body: formData });
  },

  sendCardEmail: (id: string) =>
    request<{ sent: boolean }>(`/members/${id}/card/send-email`, { method: "POST" }),

  async downloadCard(id: string, filename: string): Promise<void> {
    const token = getToken();
    const response = await fetch(`${API_URL}/members/${id}/card/download`, {
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    });
    if (!response.ok) {
      const payload = await response.json().catch(() => null);
      throw new ApiError(payload?.error ?? "Impossible de générer la carte.", response.status);
    }
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  },

  verify: (uuid: string) =>
    fetch(`${API_URL}/public/verify/${uuid}`).then((r) => r.json() as Promise<VerifyResult>),
};

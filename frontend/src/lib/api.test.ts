import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, ApiError, getToken, setToken } from "./api";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

describe("token storage", () => {
  afterEach(() => {
    window.localStorage.clear();
  });

  it("returns null when no token is stored", () => {
    expect(getToken()).toBeNull();
  });

  it("round-trips a token through localStorage", () => {
    setToken("abc.def.ghi");
    expect(getToken()).toBe("abc.def.ghi");
  });

  it("removes the token when set to null", () => {
    setToken("abc.def.ghi");
    setToken(null);
    expect(getToken()).toBeNull();
  });
});

describe("api requests", () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("returns parsed JSON on success", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({ token: "jwt-token" }));

    const result = await api.login("a@b.com", "secret");

    expect(result).toEqual({ token: "jwt-token" });
  });

  it("sends the stored token as a Bearer header", async () => {
    setToken("my-jwt");
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({ items: [], total: 0, page: 1, limit: 20 }));

    await api.listMembers({});

    const [, options] = vi.mocked(fetch).mock.calls[0];
    const headers = options?.headers as Headers;
    expect(headers.get("Authorization")).toBe("Bearer my-jwt");
  });

  it("does not send an Authorization header when logged out", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({ items: [], total: 0, page: 1, limit: 20 }));

    await api.listMembers({});

    const [, options] = vi.mocked(fetch).mock.calls[0];
    const headers = options?.headers as Headers;
    expect(headers.get("Authorization")).toBeNull();
  });

  it("throws an ApiError carrying the message and status on failure", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({ error: "Adhérent introuvable.", status: 404 }, 404));

    await expect(api.getMember("unknown-id")).rejects.toMatchObject({
      message: "Adhérent introuvable.",
      status: 404,
    });
  });

  it("carries field violations from a 422 response", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(
        { error: "Validation failed", status: 422, violations: { email: ["This value is not a valid email address."] } },
        422,
      ),
    );

    try {
      await api.createMember({ nom: "Doe", prenom: "Jane" });
      expect.unreachable("expected createMember to throw");
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).violations).toEqual({
        email: ["This value is not a valid email address."],
      });
    }
  });

  it("resolves to undefined on a 204 No Content response", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(null, { status: 204 }));

    await expect(api.deleteAdmin("some-id")).resolves.toBeUndefined();
  });

  it("does not set a JSON Content-Type when sending FormData", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({ imported: 0, skippedDuplicates: 0, errors: [] }));
    const file = new File(["a,b\n1,2"], "members.csv", { type: "text/csv" });

    await api.importMembers(file);

    const [, options] = vi.mocked(fetch).mock.calls[0];
    const headers = options?.headers as Headers;
    expect(headers.get("Content-Type")).toBeNull();
    expect(options?.body).toBeInstanceOf(FormData);
  });
});

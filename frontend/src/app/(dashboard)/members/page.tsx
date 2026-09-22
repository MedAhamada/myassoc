"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import type { Adherant, AdherantStatut, Paginated } from "@/lib/types";
import { StatusBadge } from "@/components/StatusBadge";

const STATUT_OPTIONS: { value: AdherantStatut | ""; label: string }[] = [
  { value: "", label: "Tous les statuts" },
  { value: "ACTIF", label: "Actif" },
  { value: "EXPIRE", label: "Expiré" },
  { value: "RESILIE", label: "Résilié" },
];

export default function MembersPage() {
  const [query, setQuery] = useState("");
  const [statut, setStatut] = useState<AdherantStatut | "">("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Paginated<Adherant> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const result = await api.listMembers({
        q: query || undefined,
        statut: statut || undefined,
        page,
        limit: 20,
      });
      setData(result);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger les adhérents.");
    } finally {
      setIsLoading(false);
    }
  }, [query, statut, page]);

  useEffect(() => {
    // `load` sets loading/error state before awaiting the fetch: an intentional,
    // standard data-fetching-on-dependency-change effect, not a synchronization bug.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  const totalPages = data ? Math.max(1, Math.ceil(data.total / data.limit)) : 1;

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <h1 className="text-2xl font-bold text-brand-navy">Adhérents</h1>
        <div className="flex gap-2">
          <Link
            href="/members/import"
            className="inline-flex items-center justify-center rounded-lg border border-brand-navy px-4 py-2 text-sm font-semibold text-brand-navy hover:bg-brand-navy/5"
          >
            Importer
          </Link>
          <Link
            href="/members/new"
            className="inline-flex items-center justify-center rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-dark"
          >
            + Nouvel adhérent
          </Link>
        </div>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row">
        <input
          type="search"
          placeholder="Rechercher (nom, prénom, email)…"
          value={query}
          onChange={(e) => {
            setPage(1);
            setQuery(e.target.value);
          }}
          className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy focus:outline-none sm:max-w-xs"
        />
        <select
          value={statut}
          onChange={(e) => {
            setPage(1);
            setStatut(e.target.value as AdherantStatut | "");
          }}
          className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy focus:outline-none"
        >
          {STATUT_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Code</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Nom</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Téléphone</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Statut</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                  Chargement…
                </td>
              </tr>
            )}
            {!isLoading && data?.items.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                  Aucun adhérent trouvé.
                </td>
              </tr>
            )}
            {data?.items.map((member) => (
              <tr key={member.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs text-gray-500">{member.code}</td>
                <td className="px-4 py-3">
                  <Link
                    href={`/members/${member.id}`}
                    className="font-medium text-brand-navy hover:underline"
                  >
                    {member.prenom} {member.nom}
                  </Link>
                </td>
                <td className="px-4 py-3 text-gray-600">{member.email ?? "—"}</td>
                <td className="px-4 py-3 text-gray-600">{member.telephone ?? "—"}</td>
                <td className="px-4 py-3">
                  <StatusBadge status={member.statut} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {data && data.total > 0 && (
        <div className="flex items-center justify-between text-sm text-gray-600">
          <span>
            {data.total} adhérent{data.total > 1 ? "s" : ""} — page {page}/{totalPages}
          </span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40"
            >
              Précédent
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              className="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40"
            >
              Suivant
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

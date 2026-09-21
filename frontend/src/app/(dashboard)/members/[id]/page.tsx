"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { Adherant } from "@/lib/types";
import { StatusBadge } from "@/components/StatusBadge";

function formatDate(value: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("fr-FR");
}

export default function MemberDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [member, setMember] = useState<Adherant | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");

  const load = useCallback(async () => {
    setError(null);
    try {
      setMember(await api.getMember(id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger cet adhérent.");
    }
  }, [id]);

  useEffect(() => {
    // `load` clears error state before awaiting the fetch: intentional mount-time
    // data fetching, not a synchronization bug.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function handleRenew() {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await api.renewMembership(id, {
        dateDebut: dateDebut || undefined,
        dateFin: dateFin || undefined,
      });
      setMember(updated);
      setNotice("Adhésion renouvelée.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec du renouvellement.");
    } finally {
      setBusy(false);
    }
  }

  async function handleCancel() {
    if (!confirm("Résilier l'adhésion en cours de cet adhérent ?")) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await api.cancelMembership(id);
      setMember(updated);
      setNotice("Adhésion résiliée.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de la résiliation.");
    } finally {
      setBusy(false);
    }
  }

  async function handleDownload() {
    if (!member) return;
    setBusy(true);
    setError(null);
    try {
      await api.downloadCard(id, `carte-${member.nom}-${member.prenom}.pdf`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec du téléchargement.");
    } finally {
      setBusy(false);
    }
  }

  async function handleSendEmail() {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await api.sendCardEmail(id);
      setNotice("Carte envoyée par e-mail.");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de l'envoi.");
    } finally {
      setBusy(false);
    }
  }

  if (error && !member) {
    return <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>;
  }

  if (!member) {
    return <p className="text-sm text-gray-400">Chargement…</p>;
  }

  const current = member.adhesions?.[0];

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-2xl font-bold text-brand-navy">
            {member.prenom} {member.nom}
          </h1>
          <div className="mt-1">
            <StatusBadge status={member.statut} />
          </div>
        </div>
      </div>

      {notice && <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{notice}</p>}
      {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="grid gap-6 md:grid-cols-2">
        <section className="space-y-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 className="font-semibold text-brand-navy">Coordonnées</h2>
          <dl className="space-y-1 text-sm">
            <div className="flex justify-between"><dt className="text-gray-500">Email</dt><dd>{member.email ?? "—"}</dd></div>
            <div className="flex justify-between"><dt className="text-gray-500">Téléphone</dt><dd>{member.telephone ?? "—"}</dd></div>
            <div className="flex justify-between"><dt className="text-gray-500">Adresse</dt><dd className="text-right">{member.adresse ?? "—"}</dd></div>
            <div className="flex justify-between"><dt className="text-gray-500">Date de naissance</dt><dd>{formatDate(member.dateNaissance)}</dd></div>
            <div className="flex justify-between"><dt className="text-gray-500">Profession</dt><dd>{member.profession ?? "—"}</dd></div>
          </dl>
        </section>

        <section className="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 className="font-semibold text-brand-navy">Adhésion</h2>
          <p className="text-sm text-gray-600">
            Période actuelle : {current ? `${formatDate(current.dateDebut)} → ${formatDate(current.dateFin)}` : "aucune"}
          </p>

          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="mb-1 block text-xs text-gray-500">Début (optionnel)</label>
              <input
                type="date"
                value={dateDebut}
                onChange={(e) => setDateDebut(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs text-gray-500">Fin (optionnel)</label>
              <input
                type="date"
                value={dateFin}
                onChange={(e) => setDateFin(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
              />
            </div>
          </div>
          <p className="text-xs text-gray-400">
            Laissez vide pour appliquer les valeurs par défaut (aujourd&apos;hui, +1 an).
          </p>

          <div className="flex flex-wrap gap-2">
            <button
              onClick={handleRenew}
              disabled={busy}
              className="rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-dark disabled:opacity-60"
            >
              Renouveler
            </button>
            <button
              onClick={handleCancel}
              disabled={busy}
              className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
            >
              Résilier
            </button>
          </div>

          <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
            <button
              onClick={handleDownload}
              disabled={busy}
              className="rounded-lg bg-brand-navy px-4 py-2 text-sm font-semibold text-white hover:bg-brand-navy-dark disabled:opacity-60"
            >
              Télécharger la carte
            </button>
            <button
              onClick={handleSendEmail}
              disabled={busy || !member.email}
              className="rounded-lg border border-brand-navy px-4 py-2 text-sm font-semibold text-brand-navy hover:bg-brand-navy/5 disabled:opacity-40"
              title={member.email ? undefined : "Aucun email renseigné"}
            >
              Envoyer par e-mail
            </button>
          </div>
        </section>
      </div>

      <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="mb-3 font-semibold text-brand-navy">Historique des adhésions</h2>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="text-left text-gray-500">
                <th className="py-2 pr-4">Début</th>
                <th className="py-2 pr-4">Fin</th>
                <th className="py-2 pr-4">Statut</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {member.adhesions?.length ? (
                member.adhesions.map((a) => (
                  <tr key={a.id}>
                    <td className="py-2 pr-4">{formatDate(a.dateDebut)}</td>
                    <td className="py-2 pr-4">{formatDate(a.dateFin)}</td>
                    <td className="py-2 pr-4"><StatusBadge status={a.statut} /></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-gray-400">
                    Aucune adhésion enregistrée.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

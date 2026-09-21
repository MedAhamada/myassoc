"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import type { Admin } from "@/lib/types";
import { useAuth } from "@/lib/auth-context";

export default function AdminsPage() {
  const { email: currentEmail } = useAuth();
  const [admins, setAdmins] = useState<Admin[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [form, setForm] = useState({ email: "", password: "", nom: "", prenom: "" });
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      setAdmins(await api.listAdmins());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger les administrateurs.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    // `load` sets loading state before awaiting the fetch: intentional mount-time
    // data fetching, not a synchronization bug.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.createAdmin(form);
      setForm({ email: "", password: "", nom: "", prenom: "" });
      await load();
    } catch (err) {
      setFormError(err instanceof ApiError ? err.message : "Échec de la création.");
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: string) {
    if (!confirm("Supprimer cet administrateur ?")) return;
    try {
      await api.deleteAdmin(id);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de la suppression.");
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-brand-navy">Administrateurs</h1>

      {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Nom</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
              <th className="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading && (
              <tr>
                <td colSpan={3} className="px-4 py-6 text-center text-gray-400">Chargement…</td>
              </tr>
            )}
            {admins.map((admin) => (
              <tr key={admin.id} className="hover:bg-gray-50">
                <td className="px-4 py-3">{admin.prenom} {admin.nom}</td>
                <td className="px-4 py-3 text-gray-600">{admin.email}</td>
                <td className="px-4 py-3 text-right">
                  <button
                    onClick={() => handleDelete(admin.id)}
                    disabled={admin.email === currentEmail}
                    className="text-red-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-300 disabled:no-underline"
                  >
                    Supprimer
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <section className="max-w-lg space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="font-semibold text-brand-navy">Ajouter un administrateur</h2>
        <form onSubmit={handleCreate} className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <input
              placeholder="Prénom"
              required
              value={form.prenom}
              onChange={(e) => setForm((f) => ({ ...f, prenom: e.target.value }))}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
            />
            <input
              placeholder="Nom"
              required
              value={form.nom}
              onChange={(e) => setForm((f) => ({ ...f, nom: e.target.value }))}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
            />
          </div>
          <input
            type="email"
            placeholder="Email"
            required
            value={form.email}
            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
          <input
            type="password"
            placeholder="Mot de passe (8 caractères min.)"
            required
            minLength={8}
            value={form.password}
            onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
          />
          {formError && <p className="text-sm text-red-600">{formError}</p>}
          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-lg bg-brand-navy px-4 py-2 text-sm font-semibold text-white hover:bg-brand-navy-dark disabled:opacity-60"
          >
            {isSubmitting ? "Création…" : "Créer"}
          </button>
        </form>
      </section>
    </div>
  );
}

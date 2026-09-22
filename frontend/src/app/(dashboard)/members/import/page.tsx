"use client";

import { useRef, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { ImportResult } from "@/lib/types";

export default function ImportMembersPage() {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleImport() {
    const file = fileInputRef.current?.files?.[0];
    if (!file) {
      setError("Veuillez sélectionner un fichier .csv ou .xlsx.");
      return;
    }
    setError(null);
    setResult(null);
    setIsSubmitting(true);
    try {
      const res = await api.importMembers(file);
      setResult(res);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de l'import.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="max-w-2xl space-y-6">
      <h1 className="text-2xl font-bold text-brand-navy">Importer des adhérents</h1>
      <p className="text-sm text-gray-600">
        Importez un fichier <code>.csv</code> ou <code>.xlsx</code> exporté depuis votre
        formulaire d&apos;adhésion. Les colonnes attendues : Code, Nom, Prénom, E-mail, Téléphone,
        Adresse, Date de naissance, Profession / Études, Date début, Date fin.
      </p>
      <ul className="list-disc space-y-1 pl-5 text-sm text-gray-600">
        <li>
          Laissez la colonne <strong>Code</strong> vide pour créer un nouvel adhérent — le code
          est généré automatiquement.
        </li>
        <li>
          Renseignez le <strong>Code</strong> d&apos;un adhérent existant (visible sur sa fiche)
          pour modifier ses informations au lieu d&apos;en créer un nouveau. Les cellules laissées
          vides conservent alors leur valeur actuelle.
        </li>
        <li>
          Renseignez <strong>Date début</strong> / <strong>Date fin</strong> (format JJ/MM/AAAA)
          pour créer automatiquement une adhésion sur cette période.
        </li>
      </ul>
      <a
        href="/templates/modele-import-adherents.csv"
        download
        className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-navy hover:underline"
      >
        ⬇ Télécharger le modèle de fichier (.csv)
      </a>

      <div className="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <input
          ref={fileInputRef}
          type="file"
          accept=".csv,.xlsx"
          className="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-brand-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-navy-dark"
        />

        {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

        <button
          onClick={handleImport}
          disabled={isSubmitting}
          className="rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white hover:bg-brand-green-dark disabled:opacity-60"
        >
          {isSubmitting ? "Import en cours…" : "Importer"}
        </button>
      </div>

      {result && (
        <div className="space-y-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <p className="text-sm">
            <strong className="text-brand-green">{result.imported}</strong> adhérent(s) créé(s),{" "}
            <strong className="text-brand-navy">{result.updated}</strong> modifié(s),{" "}
            <strong>{result.skippedDuplicates}</strong> doublon(s) ignoré(s).
          </p>
          {result.errors.length > 0 && (
            <div>
              <p className="mb-1 text-sm font-medium text-red-700">
                {result.errors.length} ligne(s) en erreur :
              </p>
              <ul className="max-h-64 space-y-1 overflow-y-auto text-sm text-red-600">
                {result.errors.map((e) => (
                  <li key={e.row}>
                    Ligne {e.row} : {e.errors.join(" ")}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

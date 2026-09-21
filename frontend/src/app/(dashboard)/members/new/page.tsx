"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";

const FIELDS: { name: string; label: string; type?: string; required?: boolean }[] = [
  { name: "nom", label: "Nom", required: true },
  { name: "prenom", label: "Prénom", required: true },
  { name: "email", label: "Email", type: "email" },
  { name: "telephone", label: "Téléphone", type: "tel" },
  { name: "dateNaissance", label: "Date de naissance", type: "date" },
  { name: "profession", label: "Profession / Études" },
  { name: "adresse", label: "Adresse" },
];

export default function NewMemberPage() {
  const router = useRouter();
  const [form, setForm] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [violations, setViolations] = useState<Record<string, string[]>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setViolations({});
    setIsSubmitting(true);
    try {
      const member = await api.createMember({
        nom: form.nom ?? "",
        prenom: form.prenom ?? "",
        email: form.email || undefined,
        telephone: form.telephone || undefined,
        dateNaissance: form.dateNaissance || undefined,
        profession: form.profession || undefined,
        adresse: form.adresse || undefined,
      });
      router.push(`/members/${member.id}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setViolations(err.violations ?? {});
      } else {
        setError("Une erreur est survenue.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="max-w-xl space-y-6">
      <h1 className="text-2xl font-bold text-brand-navy">Nouvel adhérent</h1>

      {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        {FIELDS.map((field) => (
          <div key={field.name}>
            <label htmlFor={field.name} className="mb-1 block text-sm font-medium text-gray-700">
              {field.label}
              {field.required && <span className="text-red-500"> *</span>}
            </label>
            <input
              id={field.name}
              type={field.type ?? "text"}
              required={field.required}
              value={form[field.name] ?? ""}
              onChange={(e) => setForm((f) => ({ ...f, [field.name]: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-navy focus:ring-1 focus:ring-brand-navy focus:outline-none"
            />
            {violations[field.name] && (
              <p className="mt-1 text-xs text-red-600">{violations[field.name].join(" ")}</p>
            )}
          </div>
        ))}

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full rounded-lg bg-brand-navy px-4 py-2 text-sm font-semibold text-white hover:bg-brand-navy-dark disabled:opacity-60"
        >
          {isSubmitting ? "Enregistrement…" : "Créer l'adhérent"}
        </button>
      </form>
    </div>
  );
}

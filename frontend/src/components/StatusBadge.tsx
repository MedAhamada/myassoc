import type { AdherantStatut, AdhesionStatut } from "@/lib/types";

const STYLES: Record<string, string> = {
  ACTIF: "bg-green-100 text-green-800",
  EN_COURS: "bg-green-100 text-green-800",
  EXPIRE: "bg-amber-100 text-amber-800",
  EXPIREE: "bg-amber-100 text-amber-800",
  RESILIE: "bg-gray-200 text-gray-700",
  CANCELLED: "bg-gray-200 text-gray-700",
};

const LABELS: Record<string, string> = {
  ACTIF: "Actif",
  EN_COURS: "En cours",
  EXPIRE: "Expiré",
  EXPIREE: "Expirée",
  RESILIE: "Résilié",
  CANCELLED: "Annulée",
};

export function StatusBadge({ status }: { status: AdherantStatut | AdhesionStatut }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STYLES[status] ?? "bg-gray-100 text-gray-700"}`}
    >
      {LABELS[status] ?? status}
    </span>
  );
}

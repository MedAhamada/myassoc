import Image from "next/image";
import type { VerifyResult } from "@/lib/types";
import { formatDate } from "@/lib/format";

// Server-side fetch (this page runs on the server): inside Docker Compose,
// `localhost` refers to the frontend container itself, not the nginx service,
// so this needs the internal service URL rather than the browser-facing
// NEXT_PUBLIC_API_URL. Falls back to it for non-Docker / host-only dev.
const API_URL =
  process.env.INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

const CONFIG: Record<VerifyResult["statut"], { label: string; className: string; icon: string }> = {
  VALIDE: { label: "Adhésion valide", className: "bg-green-50 text-green-800 border-green-200", icon: "✅" },
  EXPIREE: { label: "Adhésion expirée", className: "bg-amber-50 text-amber-800 border-amber-200", icon: "⚠️" },
  INVALIDE: { label: "Carte invalide", className: "bg-red-50 text-red-800 border-red-200", icon: "❌" },
};

async function fetchVerification(uuid: string): Promise<VerifyResult> {
  try {
    const res = await fetch(`${API_URL}/public/verify/${uuid}`, { cache: "no-store" });
    return await res.json();
  } catch {
    return { statut: "INVALIDE" };
  }
}

export default async function VerifyPage(props: PageProps<"/verify/[uuid]">) {
  const { uuid } = await props.params;
  const result = await fetchVerification(uuid);
  const config = CONFIG[result.statut];

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-brand-navy px-4 py-12 text-center">
      <Image
        src="/logo.jpeg"
        alt="Logo de l'association"
        width={88}
        height={88}
        className="rounded-full object-cover"
      />

      <div className={`w-full max-w-sm rounded-2xl border-2 bg-white p-8 shadow-xl ${config.className}`}>
        <p className="text-4xl">{config.icon}</p>
        <h1 className="mt-3 text-xl font-bold">{config.label}</h1>
        {result.statut !== "INVALIDE" && (
          <div className="mt-4 space-y-1 text-sm text-gray-700">
            <p className="font-medium">
              {result.prenom} {result.nom}
            </p>
            {result.dateFin && <p>Valable jusqu&apos;au {formatDate(result.dateFin)}</p>}
          </div>
        )}
      </div>

      <p className="text-xs text-white/60">C.J.O.M.M — vérification de carte d&apos;adhérent</p>
    </div>
  );
}

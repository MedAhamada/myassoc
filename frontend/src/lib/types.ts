export type AdherantStatut = "ACTIF" | "EXPIRE" | "RESILIE";
export type AdhesionStatut = "EN_COURS" | "EXPIREE" | "CANCELLED";

export interface Admin {
  id: string;
  email: string;
  nom: string;
  prenom: string;
  roles: string[];
  createdAt: string;
  updatedAt: string;
}

export interface Adhesion {
  id: string;
  dateDebut: string;
  dateFin: string;
  statut: AdhesionStatut;
  createdAt: string;
}

export interface Adherant {
  id: string;
  uuid: string;
  code: string;
  nom: string;
  prenom: string;
  email: string | null;
  telephone: string | null;
  adresse: string | null;
  statut: AdherantStatut;
  dateNaissance: string | null;
  profession: string | null;
  commentaires: string | null;
  createdAt: string;
  updatedAt: string;
  adhesions?: Adhesion[];
  /** The membership period covering today, if any — distinct from adhesions[0], which is simply the most recently created period. */
  adhesionEnCours?: Adhesion | null;
}

/**
 * POST /members/{id}/renew sends the card automatically when the adherant
 * has an e-mail on file (best-effort — `cardEmailSent` reflects whether that
 * actually happened, not just whether an address was present).
 */
export interface RenewResult extends Adherant {
  cardEmailSent: boolean;
}

export interface Paginated<T> {
  items: T[];
  total: number;
  page: number;
  limit: number;
}

export interface ApiErrorPayload {
  error: string;
  status: number;
  violations?: Record<string, string[]>;
}

export interface ImportResult {
  imported: number;
  updated: number;
  skippedDuplicates: number;
  errors: { row: number; errors: string[] }[];
}

export interface VerifyResult {
  statut: "VALIDE" | "EXPIREE" | "INVALIDE";
  nom?: string;
  prenom?: string;
  dateFin?: string;
}

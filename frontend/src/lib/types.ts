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
  skippedDuplicates: number;
  errors: { row: number; errors: string[] }[];
}

export interface VerifyResult {
  statut: "VALIDE" | "EXPIREE" | "INVALIDE";
  nom?: string;
  prenom?: string;
  dateFin?: string;
}

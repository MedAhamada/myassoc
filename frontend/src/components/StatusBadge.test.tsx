import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { StatusBadge } from "./StatusBadge";

describe("StatusBadge", () => {
  it.each([
    ["ACTIF", "Actif"],
    ["EXPIRE", "Expiré"],
    ["RESILIE", "Résilié"],
    ["EN_COURS", "En cours"],
    ["EXPIREE", "Expirée"],
    ["CANCELLED", "Annulée"],
  ] as const)("renders the French label for %s", (status, label) => {
    render(<StatusBadge status={status} />);
    expect(screen.getByText(label)).toBeInTheDocument();
  });

  it("gives ACTIF and EN_COURS a green style", () => {
    const { rerender } = render(<StatusBadge status="ACTIF" />);
    expect(screen.getByText("Actif")).toHaveClass("bg-green-100");

    rerender(<StatusBadge status="EN_COURS" />);
    expect(screen.getByText("En cours")).toHaveClass("bg-green-100");
  });
});

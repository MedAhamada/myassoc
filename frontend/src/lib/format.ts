export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("fr-FR");
}

/** Converts an ISO datetime string to the `yyyy-mm-dd` shape an `<input type="date">` expects. */
export function toDateInputValue(value: string | null | undefined): string {
  return value ? value.slice(0, 10) : "";
}

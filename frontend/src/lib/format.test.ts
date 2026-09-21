import { describe, expect, it } from "vitest";
import { formatDate, toDateInputValue } from "./format";

describe("formatDate", () => {
  it("formats an ISO datetime as a French date", () => {
    expect(formatDate("2026-01-15T00:00:00+01:00")).toBe("15/01/2026");
  });

  it("returns an em dash for null or undefined", () => {
    expect(formatDate(null)).toBe("—");
    expect(formatDate(undefined)).toBe("—");
  });
});

describe("toDateInputValue", () => {
  it("truncates an ISO datetime to yyyy-mm-dd", () => {
    expect(toDateInputValue("2026-01-15T00:00:00+01:00")).toBe("2026-01-15");
  });

  it("returns an empty string for null or undefined", () => {
    expect(toDateInputValue(null)).toBe("");
    expect(toDateInputValue(undefined)).toBe("");
  });
});

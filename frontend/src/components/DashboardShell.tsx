"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/lib/auth-context";

const NAV_ITEMS = [
  { href: "/members", label: "Adhérents" },
  // { href: "/members/new", label: "Nouvel adhérent" },
  // { href: "/members/import", label: "Importer" },
  { href: "/admins", label: "Administrateurs" },
];

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { email, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-10 bg-brand-navy text-white shadow-md">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <div className="flex items-center gap-3">
            <Image
              src="/logo.jpeg"
              alt="Logo"
              width={36}
              height={36}
              className="rounded-full object-cover"
            />
            <span className="text-lg font-bold">C.J.O.M.M</span>
          </div>

          <nav className="hidden items-center gap-1 md:flex">
            {NAV_ITEMS.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={`rounded-md px-3 py-2 text-sm font-medium transition ${
                  pathname === item.href
                    ? "bg-brand-gold text-brand-navy-dark"
                    : "text-white/90 hover:bg-white/10"
                }`}
              >
                {item.label}
              </Link>
            ))}
          </nav>

          <div className="hidden items-center gap-3 md:flex">
            <span className="text-sm text-white/80">{email}</span>
            <button
              onClick={logout}
              className="rounded-md bg-white/10 px-3 py-1.5 text-sm font-medium hover:bg-white/20"
            >
              Déconnexion
            </button>
          </div>

          <button
            className="rounded-md p-2 text-white md:hidden"
            aria-label="Ouvrir le menu"
            onClick={() => setMenuOpen((open) => !open)}
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

        {menuOpen && (
          <nav className="flex flex-col gap-1 border-t border-white/10 px-4 py-3 md:hidden">
            {NAV_ITEMS.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setMenuOpen(false)}
                className={`rounded-md px-3 py-2 text-sm font-medium ${
                  pathname === item.href
                    ? "bg-brand-gold text-brand-navy-dark"
                    : "text-white/90 hover:bg-white/10"
                }`}
              >
                {item.label}
              </Link>
            ))}
            <div className="mt-2 flex items-center justify-between border-t border-white/10 pt-2">
              <span className="text-sm text-white/80">{email}</span>
              <button
                onClick={logout}
                className="rounded-md bg-white/10 px-3 py-1.5 text-sm font-medium hover:bg-white/20"
              >
                Déconnexion
              </button>
            </div>
          </nav>
        )}
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">{children}</main>
    </div>
  );
}

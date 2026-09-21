"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const { email, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !email) {
      router.replace("/login");
    }
  }, [isLoading, email, router]);

  if (isLoading || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-gray-500">
        Chargement…
      </div>
    );
  }

  return <>{children}</>;
}

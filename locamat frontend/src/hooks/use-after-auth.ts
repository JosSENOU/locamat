import { useNavigate } from "@tanstack/react-router";
import { homeFor, safeRedirect } from "@/lib/constants";
import type { User } from "@/types/api";

export function useAfterAuth(redirect: string | undefined) {
  const navigate = useNavigate();
  return (u: User) => {
    const target = u.role === "locataire" ? (safeRedirect(redirect) ?? "/") : homeFor(u.role);
    void navigate({ to: target, replace: true });
  };
}


import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "@tanstack/react-router";
import { toast } from "sonner";
import { api, setUnauthorizedHandler, tokenStore } from "@/lib/api";
import type { AuthResponse, RegistrationResponse, User } from "@/types/api";
import type { BecomeOwnerValues, LoginValues, ProfileValues, RegisterValues } from "@/lib/schemas";
import { FullPageLoader } from "@/components/FullPageLoader";

type Status = "idle" | "loading" | "ready";

interface AuthContextValue {
  user: User | null;
  status: Status;
  login: (values: LoginValues) => Promise<User>;
  /** Connexion dédiée au super-administrateur (point d'entrée API distinct). */
  loginSuperAdmin: (values: LoginValues) => Promise<User>;
  register: (values: RegisterValues) => Promise<RegistrationResponse>;
  becomeOwner: (values: BecomeOwnerValues) => Promise<User>;
  logout: () => Promise<void>;
  updateProfile: (
    values: ProfileValues,
    photo?: File | null,
    removePhoto?: boolean,
  ) => Promise<User>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [status, setStatus] = useState<Status>("idle");
  const queryClient = useQueryClient();
  const router = useRouter();

  // Hydrate from the stored token (client only).
  useEffect(() => {
    const token = tokenStore.get();
    if (!token) {
      setStatus("ready");
      return;
    }
    setStatus("loading");
    api<User>("/user", { skipAuthRedirect: true })
      .then((u) => setUser(u))
      .catch(() => tokenStore.clear())
      .finally(() => setStatus("ready"));
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      tokenStore.clear();
      setUser(null);
      queryClient.clear();
      toast.error("Session expirée", {
        id: "session-expired",
        description: "Veuillez vous reconnecter.",
      });
      const here = router.state.location.href;
      if (here.startsWith("/super-admin")) {
        void router.navigate({ to: "/super-admin/connexion", replace: true });
        return;
      }
      void router.navigate({
        to: "/connexion",
        search: here.startsWith("/connexion") ? {} : { redirect: here },
        replace: true,
      });
    });
    return () => setUnauthorizedHandler(null);
  }, [queryClient, router]);

  const login = useCallback(
    async (values: LoginValues) => {
      const res = await api<AuthResponse>("/login", { method: "POST", body: values });
      tokenStore.set(res.token);
      queryClient.clear();
      setUser(res.user);
      return res.user;
    },
    [queryClient],
  );

  const loginSuperAdmin = useCallback(
    async (values: LoginValues) => {
      const res = await api<AuthResponse>("/super-admin/login", { method: "POST", body: values });
      tokenStore.set(res.token);
      queryClient.clear();
      setUser(res.user);
      return res.user;
    },
    [queryClient],
  );

  const register = useCallback(async (values: RegisterValues) => {
    // Seul un propriétaire envoie une localisation ; les champs vides sont omis.
    const { ville, quartier, adresse, ...rest } = values;
    const body = {
      ...rest,
      telephone: values.telephone || undefined,
      ...(values.role === "proprietaire"
        ? { ville, quartier: quartier || undefined, adresse: adresse || undefined }
        : {}),
    };
    return api<RegistrationResponse>("/register", { method: "POST", body });
  }, []);

  const becomeOwner = useCallback(
    async (values: BecomeOwnerValues) => {
      const u = await api<User>("/profile/become-owner", { method: "POST", body: values });
      queryClient.clear();
      setUser(u);
      return u;
    },
    [queryClient],
  );

  const logout = useCallback(async () => {
    try {
      await api("/logout", { method: "POST", skipAuthRedirect: true });
    } catch {
      // ignore: we log out locally anyway
    }
    await queryClient.cancelQueries();
    queryClient.clear();
    tokenStore.clear();
    setUser(null);
    toast.success("Vous êtes déconnecté.");
    const here = router.state.location.pathname;
    void router.navigate({
      to: here.startsWith("/super-admin") ? "/super-admin/connexion" : "/",
      replace: true,
    });
  }, [queryClient, router]);

  const updateProfile = useCallback(
    async (values: ProfileValues, photo?: File | null, removePhoto = false) => {
      let u: User;
      if (photo || removePhoto) {
        const formData = new FormData();
        formData.set("_method", "PUT");
        formData.set("name", values.name);
        formData.set("telephone", values.telephone || "");
        if (values.ville !== undefined) {
          formData.set("ville", values.ville);
          formData.set("quartier", values.quartier ?? "");
          formData.set("adresse", values.adresse ?? "");
        }
        if (photo) formData.set("photo", photo);
        if (removePhoto) formData.set("remove_photo", "1");
        u = await api<User>("/profile", { method: "POST", formData });
      } else {
        u = await api<User>("/profile", {
          method: "PUT",
          body: {
            name: values.name,
            telephone: values.telephone || null,
            ...(values.ville !== undefined
              ? {
                  ville: values.ville,
                  quartier: values.quartier || null,
                  adresse: values.adresse || null,
                }
              : {}),
          },
        });
      }
      setUser(u);
      return u;
    },
    [],
  );

  const value = useMemo(
    () => ({ user, status, login, loginSuperAdmin, register, becomeOwner, logout, updateProfile }),
    [user, status, login, loginSuperAdmin, register, becomeOwner, logout, updateProfile],
  );

  return (
    <AuthContext.Provider value={value}>
      {status === "loading" ? <FullPageLoader label="Connexion à votre compte…" /> : children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth doit être utilisé dans <AuthProvider>");
  return ctx;
}

const RAW_URL = (import.meta.env.VITE_API_URL as string | undefined) || "http://localhost:8000/api";

export const API_URL = RAW_URL.replace(/\/+$/, "");
export const API_ORIGIN = API_URL.replace(/\/api$/, "");

const TOKEN_KEY = "locamat_token";

export const tokenStore = {
  get(): string | null {
    if (typeof window === "undefined") return null;
    return window.localStorage.getItem(TOKEN_KEY);
  },
  set(token: string) {
    window.localStorage.setItem(TOKEN_KEY, token);
  },
  clear() {
    if (typeof window === "undefined") return;
    window.localStorage.removeItem(TOKEN_KEY);
  },
};

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  status: number;
  errors: FieldErrors;
  constructor(status: number, message: string, errors: FieldErrors = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
  get hasFieldErrors() {
    return Object.keys(this.errors).length > 0;
  }
}

let unauthorizedHandler: (() => void) | null = null;
export function setUnauthorizedHandler(fn: (() => void) | null) {
  unauthorizedHandler = fn;
}

type QueryValue = string | number | boolean | undefined | null;

export interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  formData?: FormData;
  query?: Record<string, QueryValue>;
  signal?: AbortSignal;
  /** Do not trigger the global "session expired" flow on 401. */
  skipAuthRedirect?: boolean;
}

const DEFAULT_MESSAGES: Record<number, string> = {
  0: "Impossible de joindre le serveur. Vérifiez votre connexion.",
  403: "Vous n'avez pas l'autorisation d'effectuer cette action.",
  404: "Ressource introuvable.",
  429: "Trop de tentatives, réessayez dans une minute.",
  500: "Une erreur serveur est survenue. Réessayez plus tard.",
};

interface ErrorBody {
  message?: string;
  errors?: FieldErrors | unknown[];
}

export async function api<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const url = new URL(API_URL + path);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
    }
  }

  const headers: Record<string, string> = { Accept: "application/json" };
  const token = tokenStore.get();
  if (token) headers.Authorization = `Bearer ${token}`;

  let body: BodyInit | undefined;
  if (opts.formData) {
    body = opts.formData;
  } else if (opts.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(opts.body);
  }

  let res: Response;
  try {
    res = await fetch(url.toString(), {
      method: opts.method ?? (body ? "POST" : "GET"),
      headers,
      body,
      signal: opts.signal,
    });
  } catch (e) {
    if (e instanceof DOMException && e.name === "AbortError") throw e;
    throw new ApiError(0, DEFAULT_MESSAGES[0] ?? "Erreur réseau.");
  }

  if (res.status === 204) return undefined as T;

  const text = await res.text();
  let json: unknown = null;
  if (text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
  }

  if (!res.ok) {
    if (res.status === 401 && token && !opts.skipAuthRedirect) {
      unauthorizedHandler?.();
    }
    const data = (json ?? {}) as ErrorBody;
    const errors = data.errors && !Array.isArray(data.errors) ? data.errors : {};
    const message =
      res.status === 429
        ? (DEFAULT_MESSAGES[429] ?? "Trop de tentatives.")
        : data.message ||
          DEFAULT_MESSAGES[res.status] ||
          ((res.status >= 500 ? DEFAULT_MESSAGES[500] : undefined) ?? "Une erreur est survenue.");
    throw new ApiError(res.status, message, errors);
  }

  return json as T;
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;
  return "Une erreur est survenue.";
}

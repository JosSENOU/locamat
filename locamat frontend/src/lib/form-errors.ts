import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { toast } from "sonner";
import { ApiError, errorMessage } from "./api";

/**
 * Maps API validation errors onto react-hook-form fields.
 * Unknown fields (or errors without field details) are shown in a toast.
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly string[],
): void {
  if (error instanceof ApiError && error.hasFieldErrors) {
    const unmapped: string[] = [];
    let first = true;
    for (const [key, msgs] of Object.entries(error.errors)) {
      const msg = msgs[0];
      if (!msg) continue;
      if (fields.includes(key)) {
        setError(key as Path<T>, { type: "server", message: msg }, { shouldFocus: first });
        first = false;
      } else {
        unmapped.push(msg);
      }
    }
    if (unmapped.length) toast.error(unmapped[0]);
    return;
  }
  toast.error(errorMessage(error));
}

import { Loader2 } from "lucide-react";
import { Button, type ButtonProps } from "./ui/button";

export function LoadingButton({ loading, children, disabled, ...props }: ButtonProps & { loading?: boolean }) {
  return (
    <Button disabled={disabled || loading} aria-busy={loading} {...props}>
      {loading && <Loader2 className="animate-spin" aria-hidden />}
      {children}
    </Button>
  );
}

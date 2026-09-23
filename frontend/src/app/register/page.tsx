import type { Metadata } from "next";
import { RegisterForm } from "./RegisterForm";

export const metadata: Metadata = { title: "Create an account", robots: { index: false } };

export default function RegisterPage() {
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">Create an account</h1>
      <RegisterForm />
    </div>
  );
}

import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Checkbox, TextInput } from "./Field";

describe("form fields are accessible", () => {
  it("associates label, hint and error with the control", () => {
    render(<TextInput label="Email" hint="We never share it." error="Invalid email" required />);
    const input = screen.getByLabelText(/Email/);
    expect(input).toBeRequired();
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAccessibleDescription(/We never share it\. Invalid email/);
    expect(screen.getByRole("alert")).toHaveTextContent("Invalid email");
  });

  it("labels checkboxes", () => {
    render(<Checkbox label="I agree" />);
    expect(screen.getByRole("checkbox", { name: "I agree" })).toBeInTheDocument();
  });
});

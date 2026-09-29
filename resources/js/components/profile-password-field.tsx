import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { PasswordInput } from '@/components/ui/password-input';

export function ProfilePasswordField({
    id,
    label,
    value,
    error,
    autoComplete,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    autoComplete: 'current-password' | 'new-password';
    onChange: (value: string) => void;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <PasswordInput
                id={id}
                value={value}
                autoComplete={autoComplete}
                aria-invalid={Boolean(error)}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export type AccountType = 'adviser' | 'student';

export type HonorificOption = {
    value: string;
    label: string;
};

export type User = {
    id: number;
    account_type: AccountType;
    title: string | null;
    name: string;
    display_name: string;
    email: string | null;
    avatar: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

export type Auth = {
    user: User;
};

export type AccountType = 'adviser' | 'student';

export type SignupActivation = {
    step: 'verify' | 'complete';
    identifier: string | null;
};

export type User = {
    id: number;
    account_type: AccountType;
    name: string;
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

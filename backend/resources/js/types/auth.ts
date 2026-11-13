export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type ActiveOrganization = {
    id: number;
    name: string;
    slug: string;
    role: string;
};

/**
 * What the user may do in the current organization (all false outside organization pages), plus whether they
 * administer the platform (super-admin, independent of any organization).
 */
export type Permissions = {
    runOperations: boolean;
    manageCatalog: boolean;
    manageWarehouses: boolean;
    manageMembers: boolean;
    manageSettings: boolean;
    administerPlatform: boolean;
};

export type Auth = {
    user: User;
    activeOrganizations: ActiveOrganization[];
    currentOrganizationId: number | null;
    can: Permissions;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

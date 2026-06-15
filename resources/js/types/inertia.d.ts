export type UserRole = 'super_admin' | 'owner' | 'member';

export interface AuthUser {
    id: string | number;
    name: string;
    email: string;
    role: UserRole;
    roleLabel: string;
    tenantId: string | null;
    tenant: { id: string; name: string } | null;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: { user: AuthUser | null };
        flash: { success: string | null; error: string | null };
    }
}

export type UserRole = 'super_admin' | 'owner' | 'member';

export interface AuthUser {
    id: string | number;
    name: string;
    email: string;
    role: UserRole;
    roleLabel: string;
    isOwner: boolean;
    allowedSections: string[];
    tenantId: string | null;
    tenant: {
        id: string;
        name: string;
        plan: string;
        planKey: string;
        features: {
            maxOperators: number;
            crm: boolean;
            analytics: boolean;
            broadcasts: boolean;
            clientBase: boolean;
            allChannels: boolean;
            webWidget: boolean;
            reminders: boolean;
            rag: boolean;
            maxNotifyEmail: number;
            maxNotifyTelegram: number;
        };
        accessExpiresAt: string | null;
        hasActiveAccess: boolean;
    } | null;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: { user: AuthUser | null };
        flash: { success: string | null; error: string | null; status: string | null };
        impersonating?: boolean;
    }
}

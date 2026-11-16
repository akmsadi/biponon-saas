import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            canRegister: boolean;
            organizationTimezone: string | null;
            organizationCurrency: string | null;
            demoMode: boolean;
            newAccessRequests: number;
            [key: string]: unknown;
        };
    }
}

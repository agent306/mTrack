export type SharedUser = {
    id: number;
    name: string;
    email: string;
    tenant_id: number | null;
    status: string;
};

export type SharedTenant = {
    id: number;
    name: string;
    status: string;
    billing_status: string;
};

export type PageProps = {
    auth: {
        user: SharedUser | null;
        tenant: SharedTenant | null;
    };
    flash: {
        status?: string;
        error?: string;
    };
};

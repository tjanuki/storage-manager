import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Video {
    id: number;
    user_id: number;
    title: string;
    description: string | null;
    original_filename: string;
    s3_key: string;
    s3_bucket: string;
    s3_region: string;
    size: number;
    formatted_size: string;
    mime_type: string;
    duration: number | null;
    formatted_duration: string | null;
    status: 'pending' | 'uploading' | 'processing' | 'completed' | 'failed';
    upload_id: string | null;
    metadata: Record<string, any> | null;
    uploaded_at: string | null;
    created_at: string;
    updated_at: string;
    s3_url?: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

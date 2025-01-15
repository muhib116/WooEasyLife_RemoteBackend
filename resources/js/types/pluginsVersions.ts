import { User } from "./customer";


export type PluginsVersion = {
    id: number; // Primary key
    version: string | null; // Nullable string
    path: string | null; // Nullable text
    download_count: number; // Nullable big integer
    settings: Record<string, any> | null; // JSON column as object
    deleted_at: string | null; // Soft delete timestamp
    created_at: string; // Timestamp for record creation
    created_by?: number | null
    creator?: User
    updated_at: string; // Timestamp for record update
};
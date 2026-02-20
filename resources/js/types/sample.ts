import type {
    PassData,
    PassImageSlot,
    PassPlatform,
    PassType,
} from '@/types/pass';

export type SampleSource = 'system' | 'user';
export type SampleSourceFilter = SampleSource | 'all';

export interface MediaLibraryAsset {
    id: string;
    owner_user_id: string | null;
    source: SampleSource;
    slot: PassImageSlot | null;
    path: string;
    url: string;
    width: number;
    height: number;
    mime: string;
    size_bytes: number;
    created_at?: string;
    updated_at?: string;
}

export type PassTypeSampleImages = Partial<
    Record<PassImageSlot, MediaLibraryAsset | string>
>;

export interface PassTypeSample {
    id: string;
    owner_user_id: string | null;
    source: SampleSource;
    name: string;
    description: string | null;
    pass_type: PassType;
    platform: PassPlatform | null;
    fields: PassData;
    images: PassTypeSampleImages;
    created_at?: string;
    updated_at?: string;
}

export interface PassTypeSampleCreatePayload {
    name: string;
    description?: string | null;
    pass_type: PassType;
    platform?: PassPlatform | null;
    fields: PassData;
    images: Partial<Record<PassImageSlot, string>>;
}

export interface SampleListParams {
    pass_type?: PassType;
    platform?: PassPlatform;
    source?: SampleSourceFilter;
    page?: number;
    per_page?: number;
}

export interface MediaLibraryListParams {
    source?: SampleSourceFilter;
    slot?: PassImageSlot;
    page?: number;
    per_page?: number;
}

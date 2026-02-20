import passes from '@/routes/passes';
import type {
    MediaLibraryAsset,
    MediaLibraryListParams,
    PassTypeSample,
    PassTypeSampleCreatePayload,
    SampleListParams,
} from '@/types/sample';
import type {
    PassImageSlot,
    PassImages,
    PassImageUploadResult,
    PassPlatform,
    PassType,
} from '@/types/pass';
import { getVariantPreviewUrl, normalizePassImages } from '@/lib/pass-images';

const getCsrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content || '';

const buildUrl = (
    base: string,
    params?: Record<string, string | number | undefined>,
) => {
    const url = new URL(base, window.location.origin);
    if (params) {
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });
    }
    return url.toString();
};

export const listSamples = async (
    params: SampleListParams = {},
): Promise<{ data: PassTypeSample[] } | PassTypeSample[]> => {
    const url = buildUrl(
        passes.samples.index().url,
        params as Record<string, string>,
    );
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Failed to load samples');
    }

    return (await response.json()) as
        | { data: PassTypeSample[] }
        | PassTypeSample[];
};

export const createSample = async (payload: PassTypeSampleCreatePayload) => {
    const response = await fetch(passes.samples.store().url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error('Failed to create sample');
    }

    return (await response.json()) as PassTypeSample;
};

export const deleteSample = async (sampleId: string) => {
    const response = await fetch(
        passes.samples.destroy({ sample: sampleId }).url,
        {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        },
    );

    if (!response.ok) {
        throw new Error('Failed to delete sample');
    }
};

export const listMediaAssets = async (
    params: MediaLibraryListParams = {},
): Promise<{ data: MediaLibraryAsset[] } | MediaLibraryAsset[]> => {
    const url = buildUrl(
        passes.media.assets.index().url,
        params as Record<string, string>,
    );
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Failed to load assets');
    }

    return (await response.json()) as
        | { data: MediaLibraryAsset[] }
        | MediaLibraryAsset[];
};

export const uploadMediaAsset = async (file: File, slot?: string) => {
    const formData = new FormData();
    formData.append('image', file);
    if (slot) {
        formData.append('slot', slot);
    }

    const response = await fetch(passes.media.assets.store().url, {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error('Failed to upload asset');
    }

    return (await response.json()) as MediaLibraryAsset;
};

export const deleteMediaAsset = async (assetId: string) => {
    const response = await fetch(
        passes.media.assets.destroy({ asset: assetId }).url,
        {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        },
    );

    if (!response.ok) {
        throw new Error('Failed to delete asset');
    }
};

export const getPassTypeFieldMap = async (
    passType: PassType,
    platform: PassPlatform,
) => {
    const url = buildUrl(passes.fieldMap.index().url, {
        pass_type: passType,
        platform,
    });

    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Failed to load pass type field map');
    }

    return (await response.json()) as Record<string, unknown>;
};

const IMAGE_SLOTS: PassImageSlot[] = [
    'icon',
    'logo',
    'strip',
    'thumbnail',
    'background',
    'footer',
];

export const buildImagesFromSample = (
    sample: PassTypeSample,
    platform: PassPlatform,
): PassImages => {
    const variants: PassImages['variants'] = { [platform]: {} };

    IMAGE_SLOTS.forEach((slot) => {
        const entry = sample.images?.[slot];
        if (!entry) return;

        const asset =
            typeof entry === 'string' ? { path: entry, url: entry } : entry;
        const path = asset.path ?? asset.url;
        if (!path) return;

        variants[platform] = variants[platform] || {};
        variants[platform]![slot] = {
            '1x': {
                path,
                url: asset.url ?? path,
                width: asset.width ?? 0,
                height: asset.height ?? 0,
                quality_warning: false,
            },
        };
    });

    return { originals: {}, variants };
};

export const collectSampleImagePayload = (
    images: PassImages,
    platform: PassPlatform,
) => {
    const normalized = normalizePassImages(images, platform);
    const payload: Partial<Record<PassImageSlot, string>> = {};

    IMAGE_SLOTS.forEach((slot) => {
        const previewUrl = getVariantPreviewUrl(normalized, platform, slot);
        const originalPath = normalized.originals?.[slot]?.path;
        const value = previewUrl ?? originalPath;
        if (value) {
            payload[slot] = value;
        }
    });

    return payload;
};

export const hasAllSampleImageSlots = (
    payload: Partial<Record<PassImageSlot, string>>,
) => {
    return IMAGE_SLOTS.every((slot) => Boolean(payload[slot]));
};

export const buildUploadResultFromAsset = (
    asset: MediaLibraryAsset,
    slot: PassImageSlot,
    platform: PassPlatform,
): PassImageUploadResult => {
    return {
        original: {
            path: asset.path,
            url: asset.url,
            width: asset.width,
            height: asset.height,
            mime: asset.mime,
        },
        variants: [
            {
                platform,
                slot,
                scale: '1x',
                path: asset.path,
                url: asset.url,
                width: asset.width,
                height: asset.height,
                quality_warning: false,
            },
        ],
    };
};

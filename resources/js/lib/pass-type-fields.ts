import type { PassPlatform, PassType } from '@/types/pass';
import { getPassTypeFieldMap } from '@/lib/samples';

export interface PassTypeFieldMap {
    pass_type: PassType;
    platform: PassPlatform;
    field_groups: string[];
    constraints?: {
        requires?: string[];
    };
}

export const fetchPassTypeFieldMap = async (
    passType: PassType,
    platform: PassPlatform,
) => {
    return (await getPassTypeFieldMap(passType, platform)) as PassTypeFieldMap;
};

export const shouldShowFieldGroup = (
    fieldMap: PassTypeFieldMap | null,
    group: string,
) => {
    if (!fieldMap) return true;
    return fieldMap.field_groups.includes(group);
};

export const requiresTransitType = (fieldMap: PassTypeFieldMap | null) => {
    return fieldMap?.constraints?.requires?.includes('transitType') ?? false;
};

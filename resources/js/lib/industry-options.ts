/**
 * Industry options for account signup dropdown
 * Used in Signup form and Account Settings
 */
export const INDUSTRY_OPTIONS = [
    { value: 'retail', label: 'Retail' },
    { value: 'hospitality', label: 'Hospitality' },
    { value: 'transportation', label: 'Transportation' },
    { value: 'finance', label: 'Finance' },
    { value: 'healthcare', label: 'Healthcare' },
    { value: 'education', label: 'Education' },
    { value: 'entertainment', label: 'Entertainment' },
    { value: 'sports', label: 'Sports' },
    { value: 'travel', label: 'Travel' },
    { value: 'government', label: 'Government' },
    { value: 'manufacturing', label: 'Manufacturing' },
    { value: 'utilities', label: 'Utilities' },
    { value: 'real-estate', label: 'Real Estate' },
    { value: 'legal', label: 'Legal' },
    { value: 'consulting', label: 'Consulting' },
    { value: 'technology', label: 'Technology' },
    { value: 'media', label: 'Media' },
    { value: 'food-beverage', label: 'Food & Beverage' },
    { value: 'fitness', label: 'Fitness' },
    { value: 'insurance', label: 'Insurance' },
] as const;

export type IndustryValue = (typeof INDUSTRY_OPTIONS)[number]['value'];

/**
 * Get industry label by value
 */
export const getIndustryLabel = (value: string): string => {
    const industry = INDUSTRY_OPTIONS.find((opt) => opt.value === value);
    return industry?.label || value;
};

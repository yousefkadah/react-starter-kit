import { useState } from 'react';
import axios, { AxiosError } from 'axios';

export interface DistributionLink {
    id: number;
    pass_id: number;
    slug: string;
    status: 'active' | 'disabled';
    url: string;
    last_accessed_at: string | null;
    accessed_count: number;
    created_at: string;
    updated_at: string;
}

export interface UseDistributionLinksOptions {
    passId: number;
}

export function useDistributionLinks({ passId }: UseDistributionLinksOptions) {
    const [links, setLinks] = useState<DistributionLink[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pagination, setPagination] = useState({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    });

    const fetchLinks = async (page = 1) => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get(
                `/passes/${passId}/distribution-links`,
                { params: { page } },
            );

            setLinks(response.data.data || response.data || []);
            if (response.data.meta) {
                setPagination(response.data.meta);
            }
        } catch (err) {
            const axiosError = err as AxiosError;
            setError(axiosError.message || 'Failed to load distribution links');
        } finally {
            setLoading(false);
        }
    };

    const createLink = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.post(
                `/passes/${passId}/distribution-links`,
            );

            const newLink = response.data;
            setLinks([newLink, ...links]);
            return newLink;
        } catch (err) {
            const axiosError = err as AxiosError;
            setError(
                axiosError.message || 'Failed to create distribution link',
            );
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const updateLink = async (
        linkId: number,
        status: 'active' | 'disabled',
    ) => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.patch(
                `/passes/${passId}/distribution-links/${linkId}`,
                { status },
            );

            const updatedLink = response.data;
            setLinks(
                links.map((link) => (link.id === linkId ? updatedLink : link)),
            );
            return updatedLink;
        } catch (err) {
            const axiosError = err as AxiosError;
            setError(
                axiosError.message || 'Failed to update distribution link',
            );
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const disableLink = async (linkId: number) => {
        return updateLink(linkId, 'disabled');
    };

    const enableLink = async (linkId: number) => {
        return updateLink(linkId, 'active');
    };

    const copyToClipboard = (url: string) => {
        navigator.clipboard.writeText(url);
    };

    return {
        links,
        loading,
        error,
        pagination,
        fetchLinks,
        createLink,
        updateLink,
        disableLink,
        enableLink,
        copyToClipboard,
    };
}

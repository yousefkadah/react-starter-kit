import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useDistributionLinks, DistributionLink } from '@/hooks/useDistributionLinks';
import { format } from 'date-fns';

interface DistributionPanelProps {
  pass: {
    id: number;
    serial_number: string;
    [key: string]: any;
  };
}

export default function DistributionPanel({ pass }: DistributionPanelProps) {
  const { links, loading, error, fetchLinks, createLink, disableLink, enableLink, copyToClipboard } =
    useDistributionLinks({
      passId: pass.id,
    });

  const [isCreating, setIsCreating] = useState(false);

  useEffect(() => {
    fetchLinks();
  }, []);

  const handleCreateLink = async () => {
    setIsCreating(true);
    try {
      await createLink();
    } catch (err) {
      console.error('Failed to create link:', err);
    } finally {
      setIsCreating(false);
    }
  };

  const handleToggleStatus = async (link: DistributionLink) => {
    try {
      if (link.status === 'active') {
        await disableLink(link.id);
      } else {
        await enableLink(link.id);
      }
    } catch (err) {
      console.error('Failed to update link status:', err);
    }
  };

  return (
    <>
      <Head title="Distribution Links" />

      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8 px-4 sm:px-6 lg:px-8">
        <div className="max-w-6xl mx-auto">
          {/* Header */}
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900">Distribution Links</h1>
            <p className="mt-2 text-gray-600">
              Manage shareable links for pass {pass.serial_number}
            </p>
          </div>

          {/* Error Message */}
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          )}

          {/* Create Button */}
          <div className="mb-6">
            <button
              onClick={handleCreateLink}
              disabled={isCreating || loading}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              {isCreating ? 'Creating...' : '+ Create New Link'}
            </button>
          </div>

          {/* Links Table */}
          <div className="bg-white rounded-lg shadow overflow-hidden">
            {loading && links.length === 0 ? (
              <div className="p-8 text-center">
                <p className="text-gray-500">Loading links...</p>
              </div>
            ) : links.length === 0 ? (
              <div className="p-8 text-center">
                <p className="text-gray-500">No distribution links yet. Create one to get started!</p>
              </div>
            ) : (
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Slug
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Accessed
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Last Access
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {links.map((link) => (
                    <tr key={link.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                        {link.slug.substring(0, 8)}...
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            link.status === 'active'
                              ? 'bg-green-100 text-green-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {link.status === 'active' ? '✓ Active' : '✕ Disabled'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {link.accessed_count || 0}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {link.last_accessed_at ? format(new Date(link.last_accessed_at), 'MMM d, h:mm a') : '—'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {format(new Date(link.created_at), 'MMM d, yyyy')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                        <button
                          onClick={() => copyToClipboard(link.url)}
                          className="text-blue-600 hover:text-blue-900 font-medium"
                        >
                          Copy
                        </button>
                        <button
                          onClick={() => handleToggleStatus(link)}
                          disabled={loading}
                          className={`font-medium ${
                            link.status === 'active'
                              ? 'text-red-600 hover:text-red-900'
                              : 'text-green-600 hover:text-green-900'
                          } disabled:text-gray-400 disabled:cursor-not-allowed`}
                        >
                          {link.status === 'active' ? 'Disable' : 'Enable'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

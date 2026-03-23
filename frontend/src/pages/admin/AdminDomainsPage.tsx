import { useState, useEffect, type FormEvent } from 'react';
import { getAdminDomains, addAdminDomain, removeAdminDomain } from '../../api/client';

interface AdminDomain {
  id: number;
  domain: string;
  display_name: string;
  icon_url: string;
  added_by: number;
  created_at: string;
}

export default function AdminDomainsPage() {
  const [domains, setDomains] = useState<AdminDomain[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [newDomain, setNewDomain] = useState('');
  const [newDisplayName, setNewDisplayName] = useState('');
  const [adding, setAdding] = useState(false);

  useEffect(() => {
    loadDomains();
  }, []);

  async function loadDomains() {
    try {
      const res = await getAdminDomains();
      setDomains(res.domains);
    } catch {
      setError('Failed to load domains');
    } finally {
      setLoading(false);
    }
  }

  async function handleAdd(e: FormEvent) {
    e.preventDefault();
    if (!newDomain.trim()) return;
    setAdding(true);
    setError('');
    try {
      await addAdminDomain({ domain: newDomain.trim(), display_name: newDisplayName.trim() });
      setNewDomain('');
      setNewDisplayName('');
      loadDomains();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to add domain');
    } finally {
      setAdding(false);
    }
  }

  async function handleRemove(id: number, domain: string) {
    if (!confirm(`Remove ${domain} from global defaults?`)) return;
    try {
      await removeAdminDomain(id);
      loadDomains();
    } catch {
      setError('Failed to remove domain');
    }
  }

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading domains...</p>
      </div>
    );
  }

  return (
    <div className="page">
      <div className="section-header">
        <h1>Default Allowed Domains</h1>
      </div>

      {error && <div className="error-message">{error}</div>}

      <div className="card">
        <h3>Add Domain</h3>
        <form onSubmit={handleAdd} className="inline-form-row">
          <input
            type="text"
            value={newDomain}
            onChange={(e) => setNewDomain(e.target.value)}
            placeholder="example.com"
            required
          />
          <input
            type="text"
            value={newDisplayName}
            onChange={(e) => setNewDisplayName(e.target.value)}
            placeholder="Display name (optional)"
          />
          <button type="submit" className="btn btn-primary" disabled={adding}>
            {adding ? 'Adding...' : 'Add Domain'}
          </button>
        </form>
      </div>

      <div className="card">
        <h3>Domains ({domains.length})</h3>
        {domains.length === 0 ? (
          <p className="text-muted">No default domains configured.</p>
        ) : (
          <div className="member-list">
            {domains.map((d) => (
              <div key={d.id} className="member-row">
                <div className="member-info">
                  <span className="member-name">{d.display_name || d.domain}</span>
                  <span className="member-email">{d.domain}</span>
                </div>
                <div className="member-actions">
                  <button className="btn btn-sm btn-danger" onClick={() => handleRemove(d.id, d.domain)}>
                    Remove
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

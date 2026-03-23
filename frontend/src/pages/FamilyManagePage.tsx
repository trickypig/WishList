import { useState, useEffect, type FormEvent } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getFamily, inviteToFamily, removeFamilyMember, setChildStatus, getFamilyDomains, addFamilyDomain, removeFamilyDomain } from '../api/client';
import type { Family, AllowedDomain } from '../types';
import MemberList from '../components/families/MemberList';

export default function FamilyManagePage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [family, setFamily] = useState<Family | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviting, setInviting] = useState(false);
  const [inviteSuccess, setInviteSuccess] = useState('');
  const [copied, setCopied] = useState(false);

  // Domain management state
  const [domains, setDomains] = useState<AllowedDomain[]>([]);
  const [newDomain, setNewDomain] = useState('');
  const [newDomainName, setNewDomainName] = useState('');
  const [addingDomain, setAddingDomain] = useState(false);

  useEffect(() => {
    loadFamily();
  }, [id]); // eslint-disable-line react-hooks/exhaustive-deps

  async function loadFamily() {
    try {
      const res = await getFamily(Number(id));
      setFamily(res.family);
      // Load domains if user is a parent admin
      loadDomains();
    } catch {
      setError('Failed to load family');
    } finally {
      setLoading(false);
    }
  }

  async function loadDomains() {
    try {
      const res = await getFamilyDomains(Number(id));
      setDomains(res.domains);
    } catch {
      // Non-critical — domains section just won't show data
    }
  }

  async function handleInvite(e: FormEvent) {
    e.preventDefault();
    setInviting(true);
    setError('');
    setInviteSuccess('');
    try {
      await inviteToFamily(Number(id), inviteEmail);
      setInviteSuccess(`Invite sent to ${inviteEmail}`);
      setInviteEmail('');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to send invite');
    } finally {
      setInviting(false);
    }
  }

  async function handleRemoveMember(userId: number) {
    if (!confirm('Remove this member from the family?')) return;
    try {
      await removeFamilyMember(Number(id), userId);
      loadFamily();
    } catch {
      setError('Failed to remove member');
    }
  }

  async function handleToggleChild(userId: number, isChild: number) {
    try {
      await setChildStatus(Number(id), userId, isChild);
      loadFamily();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update child status');
    }
  }

  async function handleLeave() {
    if (!user || !confirm('Are you sure you want to leave this family?')) return;
    try {
      await removeFamilyMember(Number(id), user.id);
      navigate('/families');
    } catch {
      setError('Failed to leave family');
    }
  }

  function copyInviteCode() {
    if (family?.invite_code) {
      navigator.clipboard.writeText(family.invite_code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  }

  async function handleAddDomain(e: FormEvent) {
    e.preventDefault();
    if (!newDomain.trim()) return;
    setAddingDomain(true);
    setError('');
    try {
      await addFamilyDomain(Number(id), { domain: newDomain.trim(), display_name: newDomainName.trim() });
      setNewDomain('');
      setNewDomainName('');
      loadDomains();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to add domain');
    } finally {
      setAddingDomain(false);
    }
  }

  async function handleRemoveDomain(domain: string) {
    if (!confirm(`Remove ${domain} from allowed domains?`)) return;
    try {
      await removeFamilyDomain(Number(id), domain);
      loadDomains();
    } catch {
      setError('Failed to remove domain');
    }
  }

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading family...</p>
      </div>
    );
  }

  if (!family) {
    return <div className="error-page"><h2>Family not found</h2></div>;
  }

  const isAdmin = family.role === 'admin';
  const isParent = isAdmin && !user?.is_child;

  return (
    <div className="page">
      <h1>{family.name}</h1>

      {error && <div className="error-message">{error}</div>}
      {inviteSuccess && <div className="success-message">{inviteSuccess}</div>}

      <div className="card">
        <h3>Invite Code</h3>
        <div className="invite-code-row">
          <code className="invite-code">{family.invite_code}</code>
          <button className="btn btn-sm btn-outline" onClick={copyInviteCode}>
            {copied ? 'Copied!' : 'Copy'}
          </button>
        </div>
        <p className="text-muted">Share this code with others so they can join your family group.</p>
      </div>

      <div className="card">
        <h3>Members</h3>
        {family.members && family.members.length > 0 ? (
          <MemberList
            members={family.members}
            isAdmin={isAdmin}
            isParent={isParent}
            currentUserId={user?.id || 0}
            onRemove={handleRemoveMember}
            onToggleChild={handleToggleChild}
          />
        ) : (
          <p className="text-muted">No members found.</p>
        )}
      </div>

      {isAdmin && (
        <div className="card">
          <h3>Invite by Email</h3>
          <form onSubmit={handleInvite} className="inline-form-row">
            <input
              type="email"
              value={inviteEmail}
              onChange={(e) => setInviteEmail(e.target.value)}
              placeholder="email@example.com"
              required
            />
            <button type="submit" className="btn btn-primary" disabled={inviting}>
              {inviting ? 'Sending...' : 'Send Invite'}
            </button>
          </form>
        </div>
      )}

      {isParent && (
        <div className="card">
          <h3>Allowed Shopping Domains</h3>
          <p className="text-muted">Manage which shopping sites are allowed for this family.</p>
          <div className="domain-list" style={{ marginBottom: '1rem' }}>
            {domains.length === 0 ? (
              <p className="text-muted">No domains configured.</p>
            ) : (
              domains.map((d) => (
                <div key={d.domain} className="member-row" style={{ padding: '0.5rem 0' }}>
                  <div className="member-info">
                    <span className="member-name">{d.display_name || d.domain}</span>
                    <span className="member-email">{d.domain}</span>
                  </div>
                  <div className="member-actions">
                    <span className="role-badge" style={{ fontSize: '0.7rem' }}>{d.source}</span>
                    <button className="btn btn-sm btn-danger" onClick={() => handleRemoveDomain(d.domain)}>
                      Remove
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
          <form onSubmit={handleAddDomain} className="inline-form-row">
            <input
              type="text"
              value={newDomain}
              onChange={(e) => setNewDomain(e.target.value)}
              placeholder="example.com"
              required
            />
            <input
              type="text"
              value={newDomainName}
              onChange={(e) => setNewDomainName(e.target.value)}
              placeholder="Display name (optional)"
            />
            <button type="submit" className="btn btn-primary" disabled={addingDomain}>
              {addingDomain ? 'Adding...' : 'Add Domain'}
            </button>
          </form>
        </div>
      )}

      {!isAdmin && (
        <button className="btn btn-danger" onClick={handleLeave} style={{ marginTop: '1rem' }}>
          Leave Family
        </button>
      )}
    </div>
  );
}

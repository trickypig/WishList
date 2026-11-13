import { useState, useEffect, type FormEvent } from 'react';
import { useParams, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getFamily, inviteToFamily, getFamilyInvites, resendFamilyInvite, revokeFamilyInvite, removeFamilyMember, setChildStatus, getFamilyDomains, addFamilyDomain, removeFamilyDomain } from '../api/client';
import type { Family, AllowedDomain, FamilyInviteSummary } from '../types';
import MemberList from '../components/families/MemberList';

export default function FamilyManagePage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const joinedMessage = (location.state as { message?: string } | null)?.message ?? '';
  const [family, setFamily] = useState<Family | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviting, setInviting] = useState(false);
  const [inviteSuccess, setInviteSuccess] = useState('');
  const [copied, setCopied] = useState(false);
  const [invites, setInvites] = useState<FamilyInviteSummary[]>([]);
  const [busyInviteId, setBusyInviteId] = useState<number | null>(null);

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
      if (res.family.role === 'admin') {
        loadInvites();
      }
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

  async function loadInvites() {
    try {
      const res = await getFamilyInvites(Number(id));
      setInvites(res.invites);
    } catch {
      // Non-critical — the invite links list just won't show data
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
      loadInvites();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to send invite');
    } finally {
      setInviting(false);
    }
  }

  async function handleResendInvite(invite: FamilyInviteSummary) {
    setBusyInviteId(invite.id);
    setError('');
    setInviteSuccess('');
    try {
      await resendFamilyInvite(Number(id), invite.id);
      setInviteSuccess(`Invite sent to ${invite.email}`);
      loadInvites();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to resend invite');
    } finally {
      setBusyInviteId(null);
    }
  }

  async function handleRevokeInvite(invite: FamilyInviteSummary) {
    if (!confirm(`Revoke all invite links sent to ${invite.email}? Anyone who already joined stays in the family.`)) return;
    setBusyInviteId(invite.id);
    setError('');
    setInviteSuccess('');
    try {
      await revokeFamilyInvite(Number(id), invite.id);
      loadInvites();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to revoke invite');
    } finally {
      setBusyInviteId(null);
    }
  }

  async function handleRemoveMember(userId: number) {
    if (!confirm('Remove this member from the family? Any invite links they were sent still work until revoked under Invite links.')) return;
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

      {joinedMessage && !error && <div className="success-message">{joinedMessage}</div>}
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

      {isAdmin && (
        <div className="card">
          <h3>Invite Links</h3>
          <p className="text-muted">Links stay valid for 14 days, even after someone uses them. Revoke a link to stop it working.</p>
          {invites.length === 0 ? (
            <p className="text-muted">No active invite links.</p>
          ) : (
            invites.map((inv) => (
              <div key={inv.id} className="member-row" style={{ padding: '0.5rem 0' }}>
                <div className="member-info">
                  <span className="member-name">{inv.email}</span>
                  <span className="member-email">
                    Sent by {inv.invited_by_name} on {formatServerDate(inv.created_at)} · Expires {formatServerDate(inv.expires_at)}
                    {inv.accepted_by_name && <> · Used by {inv.accepted_by_name}</>}
                    {inv.declined_by_name && <> · Declined by {inv.declined_by_name}</>}
                  </span>
                </div>
                <div className="member-actions">
                  <button className="btn btn-sm btn-outline" onClick={() => handleResendInvite(inv)} disabled={busyInviteId === inv.id}>
                    Resend
                  </button>
                  <button className="btn btn-sm btn-danger" onClick={() => handleRevokeInvite(inv)} disabled={busyInviteId === inv.id}>
                    Revoke
                  </button>
                </div>
              </div>
            ))
          )}
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

/** Server timestamps are UTC 'YYYY-MM-DD HH:MM:SS'; show them as a local date. */
function formatServerDate(value: string): string {
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleDateString();
}

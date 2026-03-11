import { useState, useEffect, type FormEvent } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getFamily, inviteToFamily, removeFamilyMember } from '../api/client';
import type { Family } from '../types';
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

  useEffect(() => {
    loadFamily();
  }, [id]); // eslint-disable-line react-hooks/exhaustive-deps

  async function loadFamily() {
    try {
      const res = await getFamily(Number(id));
      setFamily(res.family);
    } catch {
      setError('Failed to load family');
    } finally {
      setLoading(false);
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
            currentUserId={user?.id || 0}
            onRemove={handleRemoveMember}
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

      {!isAdmin && (
        <button className="btn btn-danger" onClick={handleLeave} style={{ marginTop: '1rem' }}>
          Leave Family
        </button>
      )}
    </div>
  );
}

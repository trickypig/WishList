import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getInvite } from '../api/client';
import type { InvitePreview } from '../types';
import { finishInvite, inviteQuery } from './inviteFlow';

const INVALID_MESSAGE = 'This invite link has expired or is no longer valid. Ask a family member to send a new one.';

export default function InvitePage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const { user, loading, logout } = useAuth();
  const navigate = useNavigate();
  const [invite, setInvite] = useState<InvitePreview | null>(null);
  const [error, setError] = useState(token ? '' : INVALID_MESSAGE);
  const [joining, setJoining] = useState(false);

  useEffect(() => {
    if (!token) return;
    getInvite(token)
      .then(setInvite)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : INVALID_MESSAGE));
  }, [token]);

  // Signed out: send them to sign in or register, carrying the invite along.
  useEffect(() => {
    if (loading || user || !invite) return;
    const page = invite.account_exists ? '/login' : '/register';
    navigate(page + inviteQuery(token, invite.email), { replace: true });
  }, [loading, user, invite, token, navigate]);

  async function handleJoin() {
    setJoining(true);
    const joinError = await finishInvite(token, navigate);
    if (joinError !== null) {
      setError(joinError);
      setJoining(false);
    }
  }

  if (error) {
    return (
      <div className="auth-page">
        <div className="auth-card">
          <h1>Invite link</h1>
          <div className="error-message">{error}</div>
        </div>
      </div>
    );
  }

  if (loading || !invite || !user) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading...</p>
      </div>
    );
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <h1>Join {invite.family_name}</h1>
        <p className="auth-subtitle">{invite.inviter_name} invited you.</p>
        <p>You're signed in as {user.display_name} ({user.email}).</p>
        <button className="btn btn-primary btn-full" onClick={handleJoin} disabled={joining}>
          {joining ? 'Joining...' : 'Join'}
        </button>
        <p className="auth-footer">
          Not you? <button type="button" className="btn-link" onClick={logout}>Sign out</button>
        </p>
      </div>
    </div>
  );
}

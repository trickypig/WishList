import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { acceptMyInvite, declineMyInvite, getMyInvites } from '../../api/client';
import type { PendingInvite } from '../../types';

/** Offers to join any family that has invited the signed-in person's email address. */
export default function PendingInvitesBanner() {
  const navigate = useNavigate();
  const [invites, setInvites] = useState<PendingInvite[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    getMyInvites()
      .then((res) => setInvites(res.invites))
      .catch(() => {
        // Non-critical — the offer just won't show
      });
  }, []);

  async function handleJoin(invite: PendingInvite) {
    setBusyId(invite.id);
    setError('');
    try {
      const { family, already_member } = await acceptMyInvite(invite.id);
      const message = already_member
        ? `You're already a member of ${family.name}`
        : `You've joined ${family.name}`;
      navigate(`/families/${family.id}`, { state: { message } });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not join the family');
      setBusyId(null);
    }
  }

  async function handleDecline(invite: PendingInvite) {
    setBusyId(invite.id);
    setError('');
    try {
      await declineMyInvite(invite.id);
      setInvites((current) => current.filter((i) => i.family_id !== invite.family_id));
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not decline the invite');
    } finally {
      setBusyId(null);
    }
  }

  if (invites.length === 0) return null;

  return (
    <div className="card pending-invites">
      {error && <div className="error-message">{error}</div>}
      {invites.map((invite) => (
        <div key={invite.family_id} className="pending-invite-row">
          <span>
            {invite.inviter_name} invited you to join <strong>{invite.family_name}</strong>.
          </span>
          <div className="member-actions">
            <button className="btn btn-sm btn-primary" onClick={() => handleJoin(invite)} disabled={busyId === invite.id}>
              Join
            </button>
            <button className="btn btn-sm btn-outline" onClick={() => handleDecline(invite)} disabled={busyId === invite.id}>
              Decline
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}

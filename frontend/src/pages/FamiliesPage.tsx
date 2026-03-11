import { useState, useEffect, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { getFamilies, createFamily, joinFamily } from '../api/client';
import type { Family } from '../types';

export default function FamiliesPage() {
  const [families, setFamilies] = useState<Family[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [newName, setNewName] = useState('');
  const [creating, setCreating] = useState(false);

  const [joinCode, setJoinCode] = useState('');
  const [joining, setJoining] = useState(false);

  const [success, setSuccess] = useState('');

  useEffect(() => {
    getFamilies()
      .then((res) => setFamilies(res.families))
      .catch(() => setError('Failed to load families'))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setCreating(true);
    setError('');
    setSuccess('');
    try {
      const res = await createFamily(newName);
      setFamilies([...families, res.family]);
      setNewName('');
      setSuccess(`Family "${res.family.name}" created!`);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to create family');
    } finally {
      setCreating(false);
    }
  }

  async function handleJoin(e: FormEvent) {
    e.preventDefault();
    setJoining(true);
    setError('');
    setSuccess('');
    try {
      const res = await joinFamily(joinCode);
      setFamilies([...families, res.family]);
      setJoinCode('');
      setSuccess(`Joined family "${res.family.name}"!`);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to join family');
    } finally {
      setJoining(false);
    }
  }

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading families...</p>
      </div>
    );
  }

  return (
    <div className="page">
      <h1>My Families</h1>

      {error && <div className="error-message">{error}</div>}
      {success && <div className="success-message">{success}</div>}

      <div className="families-grid">
        <div className="card">
          <h3>Create a Family</h3>
          <form onSubmit={handleCreate} className="inline-form-row">
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="Family name"
              required
            />
            <button type="submit" className="btn btn-primary" disabled={creating}>
              {creating ? 'Creating...' : 'Create'}
            </button>
          </form>
        </div>

        <div className="card">
          <h3>Join a Family</h3>
          <form onSubmit={handleJoin} className="inline-form-row">
            <input
              type="text"
              value={joinCode}
              onChange={(e) => setJoinCode(e.target.value)}
              placeholder="Invite code"
              required
            />
            <button type="submit" className="btn btn-primary" disabled={joining}>
              {joining ? 'Joining...' : 'Join'}
            </button>
          </form>
        </div>
      </div>

      {families.length === 0 ? (
        <div className="empty-state">
          <p>You are not a member of any families yet. Create or join one above!</p>
        </div>
      ) : (
        <div className="list-grid" style={{ marginTop: '1.5rem' }}>
          {families.map((family) => (
            <div key={family.id} className="family-card card">
              <h3>{family.name}</h3>
              <div className="family-card-meta">
                <span className={`role-badge role-${family.role}`}>{family.role}</span>
                {family.member_count != null && (
                  <span className="text-muted">
                    {family.member_count} {family.member_count === 1 ? 'member' : 'members'}
                  </span>
                )}
              </div>
              <div className="family-card-actions">
                <Link to={`/families/${family.id}`} className="btn btn-sm btn-outline">Manage</Link>
                <Link to={`/families/${family.id}/lists`} className="btn btn-sm btn-primary">View Lists</Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

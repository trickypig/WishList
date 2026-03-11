import { useState, useEffect, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { getLists, createList, getFamilies } from '../api/client';
import type { WishList, Family } from '../types';
import ListCard from '../components/lists/ListCard';

export default function DashboardPage() {
  const [lists, setLists] = useState<WishList[]>([]);
  const [families, setFamilies] = useState<Family[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([getLists(), getFamilies()])
      .then(([listsRes, familiesRes]) => {
        setLists(listsRes.lists);
        setFamilies(familiesRes.families);
      })
      .catch(() => setError('Failed to load data'))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setCreating(true);
    setError('');
    try {
      const res = await createList({ title, description, visibility: 'private' });
      setLists([res.list, ...lists]);
      setTitle('');
      setDescription('');
      setShowForm(false);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to create list');
    } finally {
      setCreating(false);
    }
  }

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading your lists...</p>
      </div>
    );
  }

  return (
    <div className="dashboard-layout">
      <main className="dashboard-main">
        <div className="section-header">
          <h1>My Wish Lists</h1>
          <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
            {showForm ? 'Cancel' : '+ New List'}
          </button>
        </div>

        {error && <div className="error-message">{error}</div>}

        {showForm && (
          <form className="inline-form card" onSubmit={handleCreate}>
            <div className="form-group">
              <label htmlFor="list-title">Title</label>
              <input
                id="list-title"
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
                placeholder="My Birthday Wishlist"
              />
            </div>
            <div className="form-group">
              <label htmlFor="list-desc">Description</label>
              <textarea
                id="list-desc"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Optional description"
                rows={2}
              />
            </div>
            <div className="form-actions">
              <button type="submit" className="btn btn-primary" disabled={creating}>
                {creating ? 'Creating...' : 'Create List'}
              </button>
            </div>
          </form>
        )}

        {lists.length === 0 ? (
          <div className="empty-state">
            <p>You haven't created any wish lists yet.</p>
            <button className="btn btn-primary" onClick={() => setShowForm(true)}>
              Create Your First List
            </button>
          </div>
        ) : (
          <div className="list-grid">
            {lists.map((list) => (
              <ListCard key={list.id} list={list} />
            ))}
          </div>
        )}
      </main>

      <aside className="dashboard-sidebar">
        <h2>My Families</h2>
        {families.length === 0 ? (
          <p className="text-muted">No families yet. <Link to="/families">Join or create one.</Link></p>
        ) : (
          <div className="sidebar-list">
            {families.map((family) => (
              <div key={family.id} className="sidebar-item">
                <Link to={`/families/${family.id}`} className="sidebar-item-name">
                  {family.name}
                </Link>
                <Link to={`/families/${family.id}/lists`} className="btn btn-sm btn-ghost">
                  View Lists
                </Link>
              </div>
            ))}
          </div>
        )}
        <Link to="/families" className="btn btn-outline btn-full" style={{ marginTop: '0.75rem' }}>
          Manage Families
        </Link>
      </aside>
    </div>
  );
}

import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getFamilyLists, getFamily } from '../api/client';
import type { WishList, Family } from '../types';
import ListCard from '../components/lists/ListCard';

export default function FamilyListsPage() {
  const { id } = useParams<{ id: string }>();
  const [lists, setLists] = useState<WishList[]>([]);
  const [family, setFamily] = useState<Family | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const familyId = Number(id);
    Promise.all([getFamilyLists(familyId), getFamily(familyId)])
      .then(([listsRes, familyRes]) => {
        setLists(listsRes.lists);
        setFamily(familyRes.family);
      })
      .catch(() => setError('Failed to load family lists'))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading family lists...</p>
      </div>
    );
  }

  return (
    <div className="page">
      <div className="section-header">
        <div>
          <h1>{family?.name || 'Family'} Lists</h1>
          <p className="text-muted">Wish lists shared with this family</p>
        </div>
        <Link to={`/families/${id}`} className="btn btn-outline">Manage Family</Link>
      </div>

      {error && <div className="error-message">{error}</div>}

      {lists.length === 0 ? (
        <div className="empty-state">
          <p>No lists have been shared with this family yet.</p>
        </div>
      ) : (
        <div className="list-grid">
          {lists.map((list) => (
            <ListCard key={list.id} list={list} showOwner />
          ))}
        </div>
      )}
    </div>
  );
}

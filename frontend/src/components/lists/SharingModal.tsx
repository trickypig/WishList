import { useState, useEffect, type FormEvent } from 'react';
import type { Family } from '../../types';
import { getFamilies, updateListSharing } from '../../api/client';

interface SharingModalProps {
  listId: number;
  currentVisibility: string;
  currentFamilyIds: number[];
  onClose: () => void;
  onSaved: () => void;
}

export default function SharingModal({ listId, currentVisibility, currentFamilyIds, onClose, onSaved }: SharingModalProps) {
  const [visibility, setVisibility] = useState(currentVisibility);
  const [familyIds, setFamilyIds] = useState<number[]>(currentFamilyIds);
  const [families, setFamilies] = useState<Family[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    getFamilies().then((res) => setFamilies(res.families)).catch(() => {});
  }, []);

  function toggleFamily(id: number) {
    setFamilyIds((prev) =>
      prev.includes(id) ? prev.filter((f) => f !== id) : [...prev, id]
    );
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await updateListSharing(listId, {
        visibility,
        family_ids: visibility === 'specific_families' ? familyIds : [],
      });
      onSaved();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update sharing');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Sharing Settings</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        {error && <div className="error-message">{error}</div>}
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label className="radio-label">
              <input
                type="radio"
                name="visibility"
                value="private"
                checked={visibility === 'private'}
                onChange={() => setVisibility('private')}
              />
              <span>Private &mdash; Only you can see this list</span>
            </label>
            <label className="radio-label">
              <input
                type="radio"
                name="visibility"
                value="all_families"
                checked={visibility === 'all_families'}
                onChange={() => setVisibility('all_families')}
              />
              <span>All Families &mdash; Shared with all your family groups</span>
            </label>
            <label className="radio-label">
              <input
                type="radio"
                name="visibility"
                value="specific_families"
                checked={visibility === 'specific_families'}
                onChange={() => setVisibility('specific_families')}
              />
              <span>Specific Families &mdash; Choose which families can see this</span>
            </label>
          </div>

          {visibility === 'specific_families' && (
            <div className="form-group">
              <label>Select Families</label>
              {families.length === 0 ? (
                <p className="text-muted">You are not a member of any families yet.</p>
              ) : (
                <div className="checkbox-group">
                  {families.map((family) => (
                    <label key={family.id} className="checkbox-label">
                      <input
                        type="checkbox"
                        checked={familyIds.includes(family.id)}
                        onChange={() => toggleFamily(family.id)}
                      />
                      <span>{family.name}</span>
                    </label>
                  ))}
                </div>
              )}
            </div>
          )}

          <div className="modal-actions">
            <button type="button" className="btn btn-outline" onClick={onClose}>Cancel</button>
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : 'Save'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

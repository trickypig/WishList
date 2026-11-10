import { useState, type FormEvent } from 'react';

interface GroupFormProps {
  initial?: { title: string; description: string | null };
  onSubmit: (data: { title: string; description: string }) => Promise<void>;
  onCancel: () => void;
}

export default function GroupForm({ initial, onSubmit, onCancel }: GroupFormProps) {
  const [title, setTitle] = useState(initial?.title ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!title.trim()) return;
    setSaving(true);
    try {
      await onSubmit({ title: title.trim(), description });
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="group-form" onSubmit={handleSubmit}>
      <input
        type="text"
        className="title-input"
        aria-label="Group title"
        placeholder="Group title"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        autoFocus
      />
      <textarea
        className="desc-input"
        aria-label="Group description"
        placeholder="Description (optional)"
        rows={3}
        value={description}
        onChange={(e) => setDescription(e.target.value)}
      />
      <div className="form-actions">
        <button type="submit" className="btn btn-primary btn-sm" disabled={saving || !title.trim()}>
          {initial ? 'Save' : 'Add Group'}
        </button>
        <button type="button" className="btn btn-outline btn-sm" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

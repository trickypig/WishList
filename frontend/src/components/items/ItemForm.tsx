import { useState, type FormEvent } from 'react';
import type { Item, ItemLink } from '../../types';

interface ItemFormProps {
  initial?: Item;
  prefill?: {
    name?: string;
    price?: number | null;
    image_url?: string;
    links?: { url: string; store_name: string }[];
  };
  onSubmit: (data: {
    name: string;
    description: string;
    price: number | null;
    quantity_desired: number;
    image_url: string;
    links: { url: string; store_name: string }[];
  }) => Promise<void>;
  onCancel: () => void;
}

export default function ItemForm({ initial, prefill, onSubmit, onCancel }: ItemFormProps) {
  const [name, setName] = useState(prefill?.name || initial?.name || '');
  const [description, setDescription] = useState(initial?.description || '');
  const [price, setPrice] = useState(
    prefill?.price != null ? prefill.price.toString() : (initial?.price?.toString() || '')
  );
  const [quantityDesired, setQuantityDesired] = useState(initial?.quantity_desired?.toString() || '1');
  const [imageUrl, setImageUrl] = useState(prefill?.image_url || initial?.image_url || '');
  const [links, setLinks] = useState<{ url: string; store_name: string }[]>(
    prefill?.links ||
    initial?.links?.map((l: ItemLink) => ({ url: l.url, store_name: l.store_name })) ||
    []
  );
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  function addLink() {
    setLinks([...links, { url: '', store_name: '' }]);
  }

  function removeLink(index: number) {
    setLinks(links.filter((_, i) => i !== index));
  }

  function updateLink(index: number, field: 'url' | 'store_name', value: string) {
    setLinks(links.map((l, i) => (i === index ? { ...l, [field]: value } : l)));
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await onSubmit({
        name,
        description,
        price: price ? parseFloat(price) : null,
        quantity_desired: parseInt(quantityDesired) || 1,
        image_url: imageUrl,
        links: links.filter((l) => l.url.trim() !== ''),
      });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to save item');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="item-form" onSubmit={handleSubmit}>
      {error && <div className="error-message">{error}</div>}
      <div className="form-row">
        <div className="form-group form-group-grow">
          <label htmlFor="item-name">Name *</label>
          <input
            id="item-name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            placeholder="Item name"
          />
        </div>
        <div className="form-group">
          <label htmlFor="item-price">Price</label>
          <input
            id="item-price"
            type="number"
            step="0.01"
            min="0"
            value={price}
            onChange={(e) => setPrice(e.target.value)}
            placeholder="0.00"
          />
        </div>
        <div className="form-group">
          <label htmlFor="item-qty">Qty Desired</label>
          <input
            id="item-qty"
            type="number"
            min="1"
            value={quantityDesired}
            onChange={(e) => setQuantityDesired(e.target.value)}
          />
        </div>
      </div>
      <div className="form-group">
        <label htmlFor="item-desc">Description</label>
        <textarea
          id="item-desc"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Optional description"
          rows={2}
        />
      </div>
      <div className="form-group">
        <label htmlFor="item-img">Image URL</label>
        <input
          id="item-img"
          type="url"
          value={imageUrl}
          onChange={(e) => setImageUrl(e.target.value)}
          placeholder="https://example.com/image.jpg"
        />
      </div>
      <div className="form-group">
        <label>Links</label>
        {links.map((link, index) => (
          <div key={index} className="link-row">
            <input
              type="url"
              value={link.url}
              onChange={(e) => updateLink(index, 'url', e.target.value)}
              placeholder="https://store.com/item"
              className="link-url"
            />
            <input
              type="text"
              value={link.store_name}
              onChange={(e) => updateLink(index, 'store_name', e.target.value)}
              placeholder="Store name"
              className="link-store"
            />
            <button type="button" className="btn btn-sm btn-danger" onClick={() => removeLink(index)}>
              &times;
            </button>
          </div>
        ))}
        <button type="button" className="btn btn-sm btn-outline" onClick={addLink}>
          + Add Link
        </button>
      </div>
      <div className="form-actions">
        <button type="button" className="btn btn-outline" onClick={onCancel}>Cancel</button>
        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? 'Saving...' : (initial ? 'Update Item' : 'Add Item')}
        </button>
      </div>
    </form>
  );
}

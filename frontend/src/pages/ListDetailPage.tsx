import { useState, useEffect, useCallback, type DragEvent } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  getList, updateList, deleteList, createItem, updateItem,
  deleteItem, reorderItems, purchaseItem, unpurchaseItem, scrapeUrl,
} from '../api/client';
import type { WishList, Item } from '../types';
import ItemCard from '../components/items/ItemCard';
import ItemForm from '../components/items/ItemForm';
import SharingModal from '../components/lists/SharingModal';

export default function ListDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [list, setList] = useState<WishList | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editingTitle, setEditingTitle] = useState(false);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [showItemForm, setShowItemForm] = useState(false);
  const [editingItem, setEditingItem] = useState<Item | null>(null);
  const [showSharing, setShowSharing] = useState(false);
  const [dragOver, setDragOver] = useState(false);
  const [scraping, setScraping] = useState(false);
  const [prefill, setPrefill] = useState<{
    name?: string;
    price?: number | null;
    image_url?: string;
    links?: { url: string; store_name: string }[];
  } | undefined>(undefined);

  const isOwner = list ? list.user_id === user?.id : false;

  const loadList = useCallback(async () => {
    try {
      const res = await getList(Number(id));
      setList(res.list);
      setTitle(res.list.title);
      setDescription(res.list.description);
    } catch {
      setError('Failed to load list');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadList();
  }, [loadList]);

  async function handleUpdateTitle() {
    if (!list) return;
    try {
      await updateList(list.id, { title, description });
      setEditingTitle(false);
      loadList();
    } catch {
      setError('Failed to update list');
    }
  }

  async function handleDeleteList() {
    if (!list || !confirm('Are you sure you want to delete this list?')) return;
    try {
      await deleteList(list.id);
      navigate('/dashboard');
    } catch {
      setError('Failed to delete list');
    }
  }

  function extractUrl(e: DragEvent): string | null {
    // Try text/uri-list first (most browsers use this for dragged links)
    const uriList = e.dataTransfer.getData('text/uri-list');
    if (uriList) {
      const firstUrl = uriList.split('\n').find(line => line.startsWith('http'));
      if (firstUrl) return firstUrl.trim();
    }
    // Fallback to text/plain
    const text = e.dataTransfer.getData('text/plain');
    if (text && (text.startsWith('http://') || text.startsWith('https://'))) {
      return text.trim().split('\n')[0].trim();
    }
    return null;
  }

  function handleDragOver(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setDragOver(true);
  }

  function handleDragLeave(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);
  }

  async function handleDrop(e: DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);

    const url = extractUrl(e);
    if (!url) {
      setError('Could not find a URL in the dropped content');
      return;
    }

    setScraping(true);
    setError('');
    try {
      const data = await scrapeUrl(url);
      setPrefill({
        name: data.name || '',
        price: data.price,
        image_url: data.image_url || '',
        links: [{ url: data.url, store_name: data.store_name || '' }],
      });
      setShowItemForm(true);
    } catch (err) {
      // Scraping failed — still open form with just the URL, but show the error
      const msg = err instanceof Error ? err.message : 'Could not fetch product info';
      setError(`Auto-fill failed: ${msg}. You can fill in the details manually.`);
      setPrefill({
        links: [{ url, store_name: '' }],
      });
      setShowItemForm(true);
    } finally {
      setScraping(false);
    }
  }

  async function handleAddItem(data: {
    name: string; description: string; price: number | null;
    quantity_desired: number; image_url: string;
    links: { url: string; store_name: string }[];
  }) {
    if (!list) return;
    await createItem(list.id, {
      ...data,
      sort_order: (list.items?.length || 0) + 1,
    });
    setShowItemForm(false);
    setPrefill(undefined);
    loadList();
  }

  async function handleUpdateItem(data: {
    name: string; description: string; price: number | null;
    quantity_desired: number; image_url: string;
    links: { url: string; store_name: string }[];
  }) {
    if (!editingItem) return;
    await updateItem(editingItem.id, data);
    setEditingItem(null);
    loadList();
  }

  async function handleDeleteItem(item: Item) {
    if (!confirm(`Delete "${item.name}"?`)) return;
    try {
      await deleteItem(item.id);
      loadList();
    } catch {
      setError('Failed to delete item');
    }
  }

  async function handleMoveItem(item: Item, direction: 'up' | 'down') {
    if (!list?.items) return;
    const items = [...list.items];
    const index = items.findIndex((i) => i.id === item.id);
    if (direction === 'up' && index > 0) {
      [items[index - 1], items[index]] = [items[index], items[index - 1]];
    } else if (direction === 'down' && index < items.length - 1) {
      [items[index], items[index + 1]] = [items[index + 1], items[index]];
    }
    try {
      await reorderItems(list.id, items.map((i) => i.id));
      loadList();
    } catch {
      setError('Failed to reorder items');
    }
  }

  async function handlePurchase(item: Item) {
    try {
      await purchaseItem(item.id, 1);
      loadList();
    } catch {
      setError('Failed to mark as purchased');
    }
  }

  async function handleUnpurchase(item: Item) {
    try {
      await unpurchaseItem(item.id);
      loadList();
    } catch {
      setError('Failed to undo purchase');
    }
  }

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading list...</p>
      </div>
    );
  }

  if (!list) {
    return <div className="error-page"><h2>List not found</h2></div>;
  }

  const items = list.items || [];

  return (
    <div className="list-detail-page">
      {error && <div className="error-message">{error}</div>}

      <div className="list-detail-header">
        {isOwner && editingTitle ? (
          <div className="edit-title-form">
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="title-input"
            />
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              placeholder="Description"
              className="desc-input"
            />
            <div className="form-actions">
              <button className="btn btn-primary btn-sm" onClick={handleUpdateTitle}>Save</button>
              <button className="btn btn-outline btn-sm" onClick={() => setEditingTitle(false)}>Cancel</button>
            </div>
          </div>
        ) : (
          <div className="list-title-block">
            <div>
              <h1>{list.title}</h1>
              {list.description && <p className="list-description">{list.description}</p>}
              {!isOwner && list.owner_name && (
                <p className="list-owner">By {list.owner_name}</p>
              )}
            </div>
            {isOwner && (
              <div className="list-header-actions">
                <button className="btn btn-sm btn-outline" onClick={() => setEditingTitle(true)}>Edit</button>
                <button className="btn btn-sm btn-outline" onClick={() => setShowSharing(true)}>Sharing</button>
                <button className="btn btn-sm btn-danger" onClick={handleDeleteList}>Delete List</button>
              </div>
            )}
          </div>
        )}
      </div>

      {isOwner && (
        <div className="add-item-section">
          {scraping && (
            <div className="drop-zone drop-zone-loading">
              <div className="spinner" />
              <p>Fetching product info...</p>
            </div>
          )}
          {showItemForm ? (
            <ItemForm
              prefill={prefill}
              onSubmit={handleAddItem}
              onCancel={() => { setShowItemForm(false); setPrefill(undefined); }}
            />
          ) : !scraping ? (
            <div className="add-item-controls">
              <div
                className={`drop-zone ${dragOver ? 'drop-zone-active' : ''}`}
                onDragOver={handleDragOver}
                onDragEnter={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
              >
                <div className="drop-zone-content">
                  <span className="drop-zone-icon">+</span>
                  <span className="drop-zone-text">
                    Drag a link here from Amazon, etc. to auto-fill item details
                  </span>
                </div>
              </div>
              <button className="btn btn-primary" onClick={() => { setPrefill(undefined); setShowItemForm(true); }}>
                + Add Item Manually
              </button>
            </div>
          ) : null}
        </div>
      )}

      {editingItem && (
        <div className="modal-overlay" onClick={() => setEditingItem(null)}>
          <div className="modal modal-wide" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>Edit Item</h2>
              <button className="modal-close" onClick={() => setEditingItem(null)}>&times;</button>
            </div>
            <ItemForm
              initial={editingItem}
              onSubmit={handleUpdateItem}
              onCancel={() => setEditingItem(null)}
            />
          </div>
        </div>
      )}

      <div className="items-list">
        {items.length === 0 ? (
          <div className="empty-state">
            <p>{isOwner ? 'No items yet. Add your first wish!' : 'This list has no items yet.'}</p>
          </div>
        ) : (
          items.map((item, index) => (
            <ItemCard
              key={item.id}
              item={item}
              isOwner={isOwner}
              onEdit={setEditingItem}
              onDelete={handleDeleteItem}
              onMoveUp={(i) => handleMoveItem(i, 'up')}
              onMoveDown={(i) => handleMoveItem(i, 'down')}
              onPurchase={handlePurchase}
              onUnpurchase={handleUnpurchase}
              isFirst={index === 0}
              isLast={index === items.length - 1}
            />
          ))
        )}
      </div>

      {showSharing && (
        <SharingModal
          listId={list.id}
          currentVisibility={list.visibility}
          currentFamilyIds={list.family_ids || []}
          onClose={() => setShowSharing(false)}
          onSaved={() => {
            setShowSharing(false);
            loadList();
          }}
        />
      )}
    </div>
  );
}

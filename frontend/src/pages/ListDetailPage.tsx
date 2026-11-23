import { useState, useEffect, useCallback, useRef, type ClipboardEvent, type DragEvent, type FormEvent } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  getList, getLists, updateList, deleteList, createItem, createSuggestion, updateItem,
  deleteItem, arrangeItems, purchaseItem, unpurchaseItem, scrapeUrl,
  createGroup, updateGroup, deleteGroup,
} from '../api/client';
import type { WishList, Item, ItemGroup } from '../types';
import ItemCard from '../components/items/ItemCard';
import { countOpen, dropBeforeId, listColorVar } from '../lib/listDisplay';
import ItemForm from '../components/items/ItemForm';
import GroupForm from '../components/items/GroupForm';

// Custom drag type so moving an item never gets mistaken for a dropped product link
const ITEM_DRAG_TYPE = 'application/x-wish-item';

/** null is the ungrouped section at the top of the list */
type GroupKey = number | null;

function isItemDrag(e: DragEvent): boolean {
  return e.dataTransfer.types.includes(ITEM_DRAG_TYPE);
}
import SharingModal from '../components/lists/SharingModal';

function isWebUrl(text: string): boolean {
  try {
    const { protocol } = new URL(text);
    return protocol === 'http:' || protocol === 'https:';
  } catch {
    return false;
  }
}

export default function ListDetailPage() {
  const { id } = useParams<{ id: string }>();
  // Each visit starts hidden, including navigation directly between two lists.
  return <ListDetail key={id} />;
}

function ListDetail() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [list, setList] = useState<WishList | null>(null);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [suggestionsLoading, setSuggestionsLoading] = useState(false);
  const requestSequence = useRef(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editingTitle, setEditingTitle] = useState(false);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [showItemForm, setShowItemForm] = useState(false);
  const [editingItem, setEditingItem] = useState<Item | null>(null);
  const [myLists, setMyLists] = useState<Pick<WishList, 'id' | 'title'>[]>([]);
  const [showSharing, setShowSharing] = useState(false);
  const [dragOver, setDragOver] = useState(false);
  const [scraping, setScraping] = useState(false);
  const [urlInput, setUrlInput] = useState('');
  const [showGroupForm, setShowGroupForm] = useState(false);
  const [editingGroupId, setEditingGroupId] = useState<number | null>(null);
  const [draggingId, setDraggingId] = useState<number | null>(null);
  const [dropTarget, setDropTarget] = useState<{ group: GroupKey; beforeId: number | null } | null>(null);
  const [prefill, setPrefill] = useState<{
    name?: string;
    price?: number | null;
    image_url?: string;
    links?: { url: string; store_name: string }[];
  } | undefined>(undefined);

  const isOwner = list ? list.user_id === user?.id : false;

  const loadList = useCallback(async () => {
    const sequence = ++requestSequence.current;
    try {
      const res = await getList(Number(id), showSuggestions);
      if (sequence !== requestSequence.current) return;
      setList(res.list);
      setTitle(res.list.title);
      setDescription(res.list.description);
    } catch {
      if (sequence === requestSequence.current) setError('Failed to load list');
    } finally {
      if (sequence === requestSequence.current) {
        setLoading(false);
        setSuggestionsLoading(false);
      }
    }
  }, [id, showSuggestions]);

  function toggleSuggestions() {
    ++requestSequence.current;
    setList(current => current ? { ...current, suggestions: [] } : current);
    setSuggestionsLoading(!showSuggestions);
    setShowSuggestions(!showSuggestions);
    setError('');
  }

  useEffect(() => {
    loadList();
  }, [loadList]);

  // The owner's other lists, offered as move targets in the item editor
  useEffect(() => {
    if (!isOwner) return;
    let cancelled = false;
    getLists()
      .then((res) => { if (!cancelled) setMyLists(res.lists.map(({ id, title }) => ({ id, title }))); })
      .catch(() => { /* the editor just hides the list picker */ });
    return () => { cancelled = true; };
  }, [isOwner]);

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
    if (isItemDrag(e)) return;
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
    if (isItemDrag(e)) return;
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);

    const url = extractUrl(e);
    if (!url) {
      setError('Could not find a URL in the dropped content');
      return;
    }

    await fillFromUrl(url);
  }

  function handleUrlPaste(e: ClipboardEvent<HTMLInputElement>) {
    const pasted = e.clipboardData.getData('text').trim();
    // Anything that isn't a full URL just lands in the box as normal text
    if (isWebUrl(pasted)) {
      e.preventDefault();
      fillFromUrl(pasted);
    }
  }

  function handleUrlSubmit(e: FormEvent) {
    e.preventDefault();
    const url = urlInput.trim();
    if (!isWebUrl(url)) {
      setError('Please enter a full link starting with http:// or https://');
      return;
    }
    fillFromUrl(url);
  }

  async function fillFromUrl(url: string) {
    setUrlInput('');
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
    await (isOwner ? createItem : createSuggestion)(list.id, {
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
    list_id?: number;
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

  const groups: ItemGroup[] = list?.groups || [];
  const groupIds = new Set(groups.map((g) => Number(g.id)));
  const sectionKeys: GroupKey[] = [null, ...groups.map((g) => Number(g.id))];

  function groupOf(item: Item): GroupKey {
    const gid = item.group_id == null ? null : Number(item.group_id);
    return gid != null && groupIds.has(gid) ? gid : null;
  }

  function itemsIn(group: GroupKey): Item[] {
    return (list?.items || []).filter((i) => groupOf(i) === group);
  }

  /** Moves an item into a section, ahead of beforeId (or to the end), and saves the whole order. */
  async function moveItem(itemId: number, toGroup: GroupKey, beforeId: number | null) {
    if (!list?.items || beforeId === itemId) return;
    const current = list.items;
    const moving = current.find((i) => i.id === itemId);
    if (!moving) return;

    const sections = new Map(sectionKeys.map((k) => [k, itemsIn(k).filter((i) => i.id !== itemId)]));
    const target = sections.get(toGroup);
    if (!target) return;
    const at = beforeId == null ? -1 : target.findIndex((i) => i.id === beforeId);
    target.splice(at < 0 ? target.length : at, 0, moving);

    const ordered = sectionKeys.flatMap((k) =>
      (sections.get(k) || []).map((i) => ({ ...i, group_id: k })),
    );
    const unchanged = ordered.every((i, n) => current[n]?.id === i.id && groupOf(current[n]) === i.group_id);
    if (unchanged) return;

    // Show the new arrangement straight away; the reload afterwards confirms what the server saved
    setList({ ...list, items: ordered.map((i, n) => ({ ...i, sort_order: n })) });
    try {
      await arrangeItems(list.id, ordered.map((i) => ({ id: i.id, group_id: i.group_id })));
    } catch {
      setError('Failed to move item');
    }
    loadList();
  }

  function handleMoveItem(item: Item, direction: 'up' | 'down') {
    const group = groupOf(item);
    const section = itemsIn(group);
    const index = section.findIndex((i) => i.id === item.id);
    if (direction === 'up' && index > 0) {
      moveItem(item.id, group, section[index - 1].id);
    } else if (direction === 'down' && index < section.length - 1) {
      moveItem(item.id, group, section[index + 2]?.id ?? null);
    }
  }

  function handleItemDragOver(e: DragEvent<HTMLDivElement>, group: GroupKey, section: Item[], index: number) {
    if (!isItemDrag(e)) return;
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    // Cards sit in a grid: the right half of a card means "drop after it"
    const beforeId = dropBeforeId(e.clientX, e.currentTarget.getBoundingClientRect(), section, index);
    if (dropTarget?.group !== group || dropTarget.beforeId !== beforeId) {
      setDropTarget({ group, beforeId });
    }
  }

  function handleSectionDragOver(e: DragEvent<HTMLElement>, group: GroupKey) {
    if (!isItemDrag(e)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    // Over a header or the gap between cards: keep the spot already chosen in this section
    if (dropTarget?.group !== group) {
      setDropTarget({ group, beforeId: null });
    }
  }

  function handleSectionDragLeave(e: DragEvent<HTMLElement>) {
    if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
      setDropTarget(null);
    }
  }

  function handleSectionDrop(e: DragEvent<HTMLElement>, group: GroupKey) {
    if (!isItemDrag(e)) return;
    e.preventDefault();
    e.stopPropagation();
    const itemId = Number(e.dataTransfer.getData(ITEM_DRAG_TYPE));
    const beforeId = dropTarget?.group === group ? dropTarget.beforeId : null;
    setDropTarget(null);
    setDraggingId(null);
    if (itemId) moveItem(itemId, group, beforeId);
  }

  async function handleAddGroup(data: { title: string; description: string }) {
    if (!list) return;
    try {
      await createGroup(list.id, data);
      setShowGroupForm(false);
      loadList();
    } catch {
      setError('Failed to add group');
    }
  }

  async function handleUpdateGroup(group: ItemGroup, data: { title: string; description: string }) {
    try {
      await updateGroup(group.id, data);
      setEditingGroupId(null);
      loadList();
    } catch {
      setError('Failed to update group');
    }
  }

  async function handleDeleteGroup(group: ItemGroup) {
    if (!confirm(`Delete the group "${group.title}"? Its items will stay on the list, just outside any group.`)) return;
    try {
      await deleteGroup(group.id);
      loadList();
    } catch {
      setError('Failed to delete group');
    }
  }

  function renderItems(group: GroupKey, compact: boolean) {
    const section = itemsIn(group);
    const dropHere = dropTarget?.group === group;
    return (
      <div className={`items-list ${compact ? 'items-list-compact' : ''} ${dropHere && dropTarget?.beforeId == null ? 'items-list-drop-end' : ''}`}>
        {section.map((item, index) => (
          <div
            key={item.id}
            className={`item-drag-wrap ${draggingId === item.id ? 'item-dragging' : ''} ${dropHere && dropTarget?.beforeId === item.id ? 'item-drop-before' : ''}`}
            draggable={isOwner}
            onDragStart={(e) => {
              e.dataTransfer.setData(ITEM_DRAG_TYPE, String(item.id));
              e.dataTransfer.effectAllowed = 'move';
              setDraggingId(item.id);
            }}
            onDragEnd={() => { setDraggingId(null); setDropTarget(null); }}
            onDragOver={(e) => handleItemDragOver(e, group, section, index)}
            onDrop={(e) => handleSectionDrop(e, group)}
          >
            <ItemCard
              item={item}
              isOwner={isOwner}
              compact={compact}
              tileIndex={index}
              onEdit={setEditingItem}
              onDelete={handleDeleteItem}
              onMoveUp={(i) => handleMoveItem(i, 'up')}
              onMoveDown={(i) => handleMoveItem(i, 'down')}
              onPurchase={handlePurchase}
              onUnpurchase={handleUnpurchase}
              isFirst={index === 0}
              isLast={index === section.length - 1}
            />
          </div>
        ))}
        {section.length === 0 && isOwner && group != null && (
          <p className="item-group-empty">Drag items here</p>
        )}
        {section.length === 0 && isOwner && group == null && draggingId != null && (
          <p className="item-group-empty">Drop here to take an item out of its group</p>
        )}
      </div>
    );
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

  function renderAddItemControls() {
    return (
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
              submitLabel={isOwner ? undefined : "Add Suggestion"}
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
              <form className="url-paste-form" onSubmit={handleUrlSubmit}>
                <input
                  type="text"
                  inputMode="url"
                  className="url-paste-input"
                  aria-label="Product link"
                  placeholder="Or paste a link here"
                  value={urlInput}
                  onChange={(e) => setUrlInput(e.target.value)}
                  onPaste={handleUrlPaste}
                />
                <button type="submit" className="btn btn-outline" disabled={!urlInput.trim()}>
                  Fetch
                </button>
              </form>
              <button className="btn btn-primary" onClick={() => { setPrefill(undefined); setShowItemForm(true); }}>
                {isOwner ? '+ Add Item Manually' : '+ Add Suggestion Manually'}
              </button>
            </div>
          ) : null}
        </div>
    );
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
  const openCount = countOpen(items);

  return (
    <div className="list-detail-page">
      {error && <div className="error-message">{error}</div>}

      <div className="list-detail-header" style={{ background: listColorVar(list.id) }}>
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
            <div className="list-counter">
              {isOwner ? (
                <><b>{items.length}</b> {items.length === 1 ? 'wish' : 'wishes'}</>
              ) : (
                <><b>{openCount.open}</b> of {openCount.total} still open</>
              )}
            </div>
            {isOwner && (
              <div className="list-header-actions">
                <button className="btn btn-sm btn-outline" onClick={() => setEditingTitle(true)}>Edit</button>
                <button className="btn btn-sm btn-outline" onClick={() => setShowSharing(true)}>Sharing</button>
                <button className="btn btn-sm btn-danger" onClick={handleDeleteList}>Delete List</button>
                <span className="list-sharing-summary" role="status">
                  {list.visibility === 'private'
                    ? 'Private — Only you'
                    : list.visibility === 'all_families'
                      ? 'Shared with: All families'
                      : `Shared with: ${list.shared_families?.map(family => family.name).join(', ') || 'No families'}`}
                </span>
              </div>
            )}
          </div>
        )}
      </div>

      {isOwner && renderAddItemControls()}

      {editingItem && (
        <div className="modal-overlay" onClick={() => setEditingItem(null)}>
          <div className="modal modal-wide" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>{editingItem.suggested_by != null ? 'Edit Suggestion' : 'Edit Item'}</h2>
              <button className="modal-close" onClick={() => setEditingItem(null)}>&times;</button>
            </div>
            <ItemForm
              initial={editingItem}
              moveLists={isOwner && editingItem.suggested_by == null ? myLists : undefined}
              onSubmit={handleUpdateItem}
              onCancel={() => setEditingItem(null)}
            />
          </div>
        </div>
      )}

      {isOwner && (
        <div className="group-toolbar">
          {showGroupForm ? (
            <GroupForm onSubmit={handleAddGroup} onCancel={() => setShowGroupForm(false)} />
          ) : (
            <button className="btn btn-outline btn-sm" onClick={() => setShowGroupForm(true)}>
              + Add Group
            </button>
          )}
          {groups.length > 0 && !showGroupForm && (
            <span className="group-toolbar-hint">Drag items onto a group to move them.</span>
          )}
        </div>
      )}

      {items.length === 0 && groups.length === 0 ? (
        <div className="items-list">
          <div className="empty-state">
            <p>{isOwner ? 'No items yet. Add your first wish!' : 'This list has no items yet.'}</p>
          </div>
        </div>
      ) : (
        <div className="list-sections">
          <section
            className={`ungrouped-section ${dropTarget?.group === null ? 'section-drop' : ''}`}
            onDragOver={(e) => handleSectionDragOver(e, null)}
            onDragLeave={handleSectionDragLeave}
            onDrop={(e) => handleSectionDrop(e, null)}
          >
            {renderItems(null, false)}
          </section>

          {groups.map((group) => {
            const key = Number(group.id);
            if (!isOwner && itemsIn(key).length === 0) return null;
            return (
              <section
                key={group.id}
                className={`item-group ${dropTarget?.group === key ? 'section-drop' : ''}`}
                onDragOver={(e) => handleSectionDragOver(e, key)}
                onDragLeave={handleSectionDragLeave}
                onDrop={(e) => handleSectionDrop(e, key)}
              >
                {editingGroupId === key ? (
                  <GroupForm
                    initial={group}
                    onSubmit={(data) => handleUpdateGroup(group, data)}
                    onCancel={() => setEditingGroupId(null)}
                  />
                ) : (
                  <div className="item-group-header">
                    <div className="item-group-heading">
                      <h3 className="item-group-title">{group.title}</h3>
                      {group.description && <p className="item-group-desc">{group.description}</p>}
                    </div>
                    {isOwner && (
                      <div className="item-group-actions">
                        <button className="btn btn-sm btn-ghost" onClick={() => setEditingGroupId(key)}>Edit</button>
                        <button className="btn btn-sm btn-ghost" onClick={() => handleDeleteGroup(group)}>Delete</button>
                      </div>
                    )}
                  </div>
                )}
                {renderItems(key, true)}
              </section>
            );
          })}
        </div>
      )}

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

      <section className="family-suggestions" aria-labelledby="family-suggestions-heading">
        <div className="family-suggestions-header">
          <div>
            <h2 id="family-suggestions-heading">Family suggestions</h2>
            <p>{isOwner ? 'Gift ideas from your family. Hidden until you choose to look.' : 'Gift ideas from family, separate from the official wish list.'}</p>
          </div>
          {isOwner && (
            <button className="btn btn-outline" onClick={toggleSuggestions} aria-expanded={showSuggestions} aria-controls="family-suggestions-content">
              {showSuggestions ? 'Hide family suggestions' : 'Show family suggestions'}
            </button>
          )}
        </div>
        {(!isOwner || showSuggestions) && (
          <div id="family-suggestions-content">
            {!isOwner && renderAddItemControls()}
            {suggestionsLoading ? <p role="status">Loading suggestions...</p> : (
              <div className="items-list">
                {(list.suggestions || []).map((item, index) => (
                  <ItemCard key={item.id} item={item} isOwner={isOwner} tileIndex={items.length + index}
                    canEdit={item.suggested_by === user?.id}
                    canDelete={isOwner || item.suggested_by === user?.id}
                    onEdit={setEditingItem} onDelete={handleDeleteItem}
                    onPurchase={handlePurchase} onUnpurchase={handleUnpurchase} />
                ))}
                {!list.suggestions?.length && <p className="item-group-empty">No family suggestions yet.</p>}
              </div>
            )}
          </div>
        )}
      </section>
    </div>
  );
}

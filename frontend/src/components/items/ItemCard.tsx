import type { Item } from '../../types';

interface ItemCardProps {
  item: Item;
  isOwner: boolean;
  canEdit?: boolean;
  canDelete?: boolean;
  onEdit?: (item: Item) => void;
  onDelete?: (item: Item) => void;
  onMoveUp?: (item: Item) => void;
  onMoveDown?: (item: Item) => void;
  onPurchase?: (item: Item) => void;
  onUnpurchase?: (item: Item) => void;
  isFirst?: boolean;
  isLast?: boolean;
  compact?: boolean;
}

export default function ItemCard({
  item,
  isOwner,
  canEdit = isOwner,
  canDelete = isOwner,
  onEdit,
  onDelete,
  onMoveUp,
  onMoveDown,
  onPurchase,
  onUnpurchase,
  isFirst,
  isLast,
  compact,
}: ItemCardProps) {
  const totalPurchased = item.total_purchased || 0;
  const purchasedByMe = item.purchased_by_me || 0;
  const fullyPurchased = totalPurchased >= item.quantity_desired;

  return (
    <div className={`item-card ${compact ? 'item-card-compact' : ''} ${fullyPurchased && !isOwner ? 'item-card-purchased' : ''}`}>
      <div className="item-card-main">
        {item.image_url && (
          <div className="item-image">
            <img src={item.image_url} alt={item.name} />
          </div>
        )}
        <div className="item-details">
          <div className="item-header">
            <h4 className="item-name">{item.name}</h4>
            {item.price != null && item.price > 0 && (
              <span className="item-price">${Number(item.price).toFixed(2)}</span>
            )}
          </div>
          {item.description && <p className="item-desc">{item.description}</p>}
          {item.suggested_by != null && (
            <p className="item-suggested-by">Suggested by {item.suggested_by_name || 'Family member'}</p>
          )}
          {item.quantity_desired > 1 && isOwner && (
            <span className="item-qty">Qty desired: {item.quantity_desired}</span>
          )}
          {item.links && item.links.length > 0 && (
            <div className="item-links">
              {item.links.map((link, i) => (
                <a
                  key={link.id || i}
                  href={link.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="item-link-btn"
                >
                  {link.store_name || 'Link'}
                </a>
              ))}
            </div>
          )}

          {!isOwner && (
            <div className="item-purchase-info">
              <div className="purchase-progress">
                <div className="purchase-bar">
                  <div
                    className="purchase-bar-fill"
                    style={{ width: `${Math.min(100, (totalPurchased / item.quantity_desired) * 100)}%` }}
                  />
                </div>
                <span className="purchase-text">
                  {totalPurchased} of {item.quantity_desired} purchased
                </span>
              </div>
              {purchasedByMe > 0 && (
                <span className="purchased-by-me">You purchased {purchasedByMe}</span>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="item-actions">
        {isOwner && item.suggested_by == null && (
          <>
            <div className="reorder-buttons">
              <button
                className="btn btn-sm btn-ghost"
                onClick={() => onMoveUp?.(item)}
                disabled={isFirst}
                title="Move up"
              >
                &#9650;
              </button>
              <button
                className="btn btn-sm btn-ghost"
                onClick={() => onMoveDown?.(item)}
                disabled={isLast}
                title="Move down"
              >
                &#9660;
              </button>
            </div>
          </>
        )}
        {canEdit && <button className="btn btn-sm btn-outline" onClick={() => onEdit?.(item)}>Edit</button>}
        {canDelete && <button className="btn btn-sm btn-danger" onClick={() => onDelete?.(item)}>Delete</button>}
        {!isOwner && (
          <>
            {!fullyPurchased && purchasedByMe === 0 && (
              <button className="btn btn-sm btn-primary" onClick={() => onPurchase?.(item)}>
                Mark Purchased
              </button>
            )}
            {purchasedByMe > 0 && (
              <button className="btn btn-sm btn-outline" onClick={() => onUnpurchase?.(item)}>
                Undo Purchase
              </button>
            )}
          </>
        )}
      </div>
    </div>
  );
}

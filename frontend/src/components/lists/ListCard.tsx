import { Link } from 'react-router-dom';
import type { WishList } from '../../types';
import { itemCountLabel, listColorVar } from '../../lib/listDisplay';

interface ListCardProps {
  list: WishList;
  showOwner?: boolean;
}

export default function ListCard({ list, showOwner }: ListCardProps) {
  const showOwnerLine = showOwner && !!list.owner_name;
  const countLabel = itemCountLabel(list);
  const hasBody = !!list.description || showOwnerLine || !!countLabel;
  return (
    <Link to={`/lists/${list.id}`} className="list-card pressable">
      <div className={`list-card-band ${hasBody ? '' : 'list-card-band-only'}`} style={{ background: listColorVar(list.id) }}>
        <h3 className="list-card-title">{list.title}</h3>
        <span className="list-card-icon" aria-hidden="true">🎁</span>
      </div>
      {hasBody && (
        <div className="list-card-body">
          {list.description && (
            <p className="list-card-desc">{list.description}</p>
          )}
          <div className="list-card-meta">
            {showOwnerLine && (
              <span className="list-card-owner">By {list.owner_name}</span>
            )}
            {countLabel && <span className="list-card-count">{countLabel}</span>}
          </div>
        </div>
      )}
    </Link>
  );
}

import { Link } from 'react-router-dom';
import type { WishList } from '../../types';

interface ListCardProps {
  list: WishList;
  showOwner?: boolean;
}

export default function ListCard({ list, showOwner }: ListCardProps) {
  return (
    <Link to={`/lists/${list.id}`} className="list-card">
      <div className="list-card-body">
        <h3 className="list-card-title">{list.title}</h3>
        {list.description && (
          <p className="list-card-desc">{list.description}</p>
        )}
        <div className="list-card-meta">
          {showOwner && list.owner_name && (
            <span className="list-card-owner">By {list.owner_name}</span>
          )}
          {list.items && (
            <span className="list-card-count">
              {list.items.length} {list.items.length === 1 ? 'item' : 'items'}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}

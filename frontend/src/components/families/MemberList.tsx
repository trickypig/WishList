import type { FamilyMember } from '../../types';

interface MemberListProps {
  members: FamilyMember[];
  isAdmin: boolean;
  isParent: boolean;
  currentUserId: number;
  onRemove: (userId: number) => void;
  onToggleChild?: (userId: number, isChild: number) => void;
}

export default function MemberList({ members, isAdmin, isParent, currentUserId, onRemove, onToggleChild }: MemberListProps) {
  return (
    <div className="member-list">
      {members.map((member) => (
        <div key={member.user_id} className="member-row">
          <div className="member-info">
            <span className="member-name">
              {member.display_name}
              {member.is_child ? <span className="role-badge role-child" style={{ marginLeft: '0.5rem', fontSize: '0.75rem' }}>child</span> : null}
            </span>
            <span className="member-email">{member.email}</span>
          </div>
          <div className="member-actions">
            <span className={`role-badge role-${member.role}`}>{member.role}</span>
            {isParent && member.user_id !== currentUserId && member.role !== 'admin' && onToggleChild && (
              <button
                className="btn btn-sm btn-outline"
                onClick={() => onToggleChild(member.user_id, member.is_child ? 0 : 1)}
              >
                {member.is_child ? 'Set Adult' : 'Set Child'}
              </button>
            )}
            {isAdmin && member.user_id !== currentUserId && (
              <button
                className="btn btn-sm btn-danger"
                onClick={() => onRemove(member.user_id)}
              >
                Remove
              </button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

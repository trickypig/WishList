import type { FamilyMember } from '../../types';

interface MemberListProps {
  members: FamilyMember[];
  isAdmin: boolean;
  currentUserId: number;
  onRemove: (userId: number) => void;
}

export default function MemberList({ members, isAdmin, currentUserId, onRemove }: MemberListProps) {
  return (
    <div className="member-list">
      {members.map((member) => (
        <div key={member.user_id} className="member-row">
          <div className="member-info">
            <span className="member-name">{member.display_name}</span>
            <span className="member-email">{member.email}</span>
          </div>
          <div className="member-actions">
            <span className={`role-badge role-${member.role}`}>{member.role}</span>
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

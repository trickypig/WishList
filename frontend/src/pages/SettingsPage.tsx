import { useAuth } from '../context/AuthContext';

export default function SettingsPage() {
  const { user } = useAuth();

  return (
    <div className="page">
      <h1>Settings</h1>
      <div className="card">
        <h3>Profile</h3>
        <div className="settings-info">
          <div className="settings-row">
            <span className="settings-label">Display Name</span>
            <span className="settings-value">{user?.display_name}</span>
          </div>
          <div className="settings-row">
            <span className="settings-label">Email</span>
            <span className="settings-value">{user?.email}</span>
          </div>
        </div>
        <p className="text-muted" style={{ marginTop: '1rem' }}>
          Profile editing will be available in a future update.
        </p>
      </div>
    </div>
  );
}

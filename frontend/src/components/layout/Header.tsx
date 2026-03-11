import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function Header() {
  const { isAuthenticated, user, logout } = useAuth();

  return (
    <header className="app-header">
      <div className="header-inner">
        <Link to="/" className="app-logo">
          <span className="logo-icon">&#10024;</span>
          Wish List
        </Link>
        <nav className="header-nav">
          {isAuthenticated ? (
            <>
              <Link to="/dashboard" className="nav-link">My Lists</Link>
              <Link to="/families" className="nav-link">Families</Link>
              {user?.is_admin === 1 && (
                <Link to="/admin" className="nav-link nav-link-admin">Admin</Link>
              )}
              <div className="header-user">
                <span className="user-name">{user?.display_name}</span>
                <button onClick={logout} className="btn btn-sm btn-outline">Logout</button>
              </div>
            </>
          ) : (
            <>
              <Link to="/login" className="nav-link">Login</Link>
              <Link to="/register" className="nav-link">Register</Link>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}

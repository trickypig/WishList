import { Link, NavLink } from 'react-router-dom';
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
              <NavLink to="/dashboard" className="nav-link">My Lists</NavLink>
              <NavLink to="/families" className="nav-link">Families</NavLink>
              {user?.is_admin === 1 && (
                <NavLink to="/admin" className="nav-link nav-link-admin">Admin</NavLink>
              )}
              <div className="header-user">
                <span className="user-name">{user?.display_name}</span>
                <button onClick={logout} className="btn btn-sm btn-outline">Logout</button>
              </div>
            </>
          ) : (
            <>
              <NavLink to="/login" className="nav-link">Login</NavLink>
              <NavLink to="/register" className="nav-link">Register</NavLink>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}

import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { finishInviteAfterAuth, inviteQuery, readInviteParams } from './inviteFlow';

export default function RegisterPage() {
  const location = useLocation();
  const { invite, email: inviteEmail } = readInviteParams(location.search);
  const [email, setEmail] = useState(inviteEmail);
  const [password, setPassword] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const { register } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await register(email, password, displayName);
      if (invite) {
        await finishInviteAfterAuth(invite, navigate);
      } else {
        navigate('/dashboard');
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <h1>Create Account</h1>
        <p className="auth-subtitle">
          {invite ? 'Create your account to accept your family invite' : 'Start creating and sharing wish lists'}
        </p>
        {error && <div className="error-message">{error}</div>}
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="displayName">Display Name</label>
            <input
              id="displayName"
              type="text"
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              required
              placeholder="Your name"
              autoComplete="name"
            />
          </div>
          <div className="form-group">
            <label htmlFor="email">Email</label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              placeholder="you@example.com"
              autoComplete="email"
            />
          </div>
          <div className="form-group">
            <label htmlFor="password">Password</label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              placeholder="Choose a password"
              autoComplete="new-password"
              minLength={6}
            />
          </div>
          <button type="submit" className="btn btn-primary btn-full" disabled={submitting}>
            {submitting ? 'Creating account...' : 'Create Account'}
          </button>
        </form>
        <p className="auth-footer">
          Already have an account? <Link to={invite ? '/login' + inviteQuery(invite, inviteEmail) : '/login'}>Sign in</Link>
        </p>
      </div>
    </div>
  );
}

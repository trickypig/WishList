import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { resetPassword } from '../api/client';

export default function ResetPasswordPage() {
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const [email, setEmail] = useState<string>(location.state?.email ?? '');
  const [code, setCode] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError('');

    if (newPassword !== confirmPassword) {
      setError('New passwords do not match');
      return;
    }

    setSubmitting(true);
    try {
      const proof = token ? { token } : { email, code };
      const res = await resetPassword({ ...proof, new_password: newPassword, confirm_password: confirmPassword });
      navigate('/login', { replace: true, state: { message: res.message } });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not reset your password');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <h1>Reset Password</h1>
        <p className="auth-subtitle">
          {token ? 'Choose a new password' : 'Enter the code from your email and choose a new password'}
        </p>
        {location.state?.message && !error && <div className="success-message">{location.state.message}</div>}
        {error && <div className="error-message">{error}</div>}
        <form onSubmit={handleSubmit}>
          {!token && (
            <>
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
                <label htmlFor="code">Reset code</label>
                <input
                  id="code"
                  type="text"
                  inputMode="numeric"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  required
                  placeholder="6-digit code"
                  autoComplete="one-time-code"
                />
              </div>
            </>
          )}
          <div className="form-group">
            <label htmlFor="newPassword">New password</label>
            <input
              id="newPassword"
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
              minLength={6}
              placeholder="At least 6 characters"
              autoComplete="new-password"
            />
          </div>
          <div className="form-group">
            <label htmlFor="confirmPassword">Confirm new password</label>
            <input
              id="confirmPassword"
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              required
              minLength={6}
              placeholder="Repeat your new password"
              autoComplete="new-password"
            />
          </div>
          <button type="submit" className="btn btn-primary btn-full" disabled={submitting}>
            {submitting ? 'Resetting...' : 'Reset Password'}
          </button>
        </form>
        <p className="auth-footer">
          Need a new code? <Link to="/forgot-password">Send another</Link>
        </p>
      </div>
    </div>
  );
}

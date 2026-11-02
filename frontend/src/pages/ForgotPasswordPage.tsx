import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { forgotPassword } from '../api/client';

export default function ForgotPasswordPage() {
  const location = useLocation();
  const [email, setEmail] = useState<string>(location.state?.email ?? '');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const res = await forgotPassword(email);
      navigate('/reset-password', { state: { email, message: res.message } });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not send the reset email');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <h1>Forgot Password</h1>
        <p className="auth-subtitle">We'll email you a code to reset it</p>
        {error && <div className="error-message">{error}</div>}
        <form onSubmit={handleSubmit}>
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
          <button type="submit" className="btn btn-primary btn-full" disabled={submitting}>
            {submitting ? 'Sending...' : 'Send Reset Code'}
          </button>
        </form>
        <p className="auth-footer">
          Remembered it? <Link to="/login">Sign in</Link>
        </p>
      </div>
    </div>
  );
}

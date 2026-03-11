import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getAdminStats } from '../../api/client';
import type { AdminStats } from '../../types';

export default function AdminDashboardPage() {
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAdminStats()
      .then((res) => setStats(res.stats))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="loading-container">
        <div className="spinner" />
        <p>Loading stats...</p>
      </div>
    );
  }

  return (
    <div className="page">
      <div className="section-header">
        <h1>Admin Dashboard</h1>
      </div>

      {stats && (
        <div className="admin-stats-grid">
          <div className="admin-stat-card">
            <div className="admin-stat-value">{stats.total_users}</div>
            <div className="admin-stat-label">Users</div>
          </div>
          <div className="admin-stat-card">
            <div className="admin-stat-value">{stats.total_families}</div>
            <div className="admin-stat-label">Families</div>
          </div>
          <div className="admin-stat-card">
            <div className="admin-stat-value">{stats.total_lists}</div>
            <div className="admin-stat-label">Wish Lists</div>
          </div>
          <div className="admin-stat-card">
            <div className="admin-stat-value">{stats.total_items}</div>
            <div className="admin-stat-label">Items</div>
          </div>
          <div className="admin-stat-card">
            <div className="admin-stat-value">{stats.total_scrapes}</div>
            <div className="admin-stat-label">Total Scrapes</div>
          </div>
          <div className="admin-stat-card admin-stat-success">
            <div className="admin-stat-value">{stats.successful_scrapes}</div>
            <div className="admin-stat-label">Successful</div>
          </div>
          <div className="admin-stat-card admin-stat-error">
            <div className="admin-stat-value">{stats.failed_scrapes}</div>
            <div className="admin-stat-label">Failed</div>
          </div>
        </div>
      )}

      <div className="admin-nav-cards">
        <Link to="/admin/scrape-logs" className="card admin-nav-card">
          <h3>Scrape Logs</h3>
          <p className="text-muted">View URL scraping history, errors, and raw HTML</p>
        </Link>
      </div>
    </div>
  );
}

import { useState, useEffect, useCallback } from 'react';
import { getScrapeLogs, getScrapeLog } from '../../api/client';
import type { ScrapeLog } from '../../types';

export default function ScrapeLogsPage() {
  const [logs, setLogs] = useState<ScrapeLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [hostFilter, setHostFilter] = useState('');
  const [successFilter, setSuccessFilter] = useState('');
  const [selectedLog, setSelectedLog] = useState<ScrapeLog | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);

  const loadLogs = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, per_page: 30 };
      if (hostFilter) params.host = hostFilter;
      if (successFilter) params.success = successFilter;
      const res = await getScrapeLogs(params);
      setLogs(res.logs);
      setTotalPages(res.pagination.total_pages);
      setTotal(res.pagination.total);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  }, [page, hostFilter, successFilter]);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  async function viewDetail(id: number) {
    setLoadingDetail(true);
    try {
      const res = await getScrapeLog(id);
      setSelectedLog(res.log);
    } catch {
      // ignore
    } finally {
      setLoadingDetail(false);
    }
  }

  function handleFilter() {
    setPage(1);
    loadLogs();
  }

  return (
    <div className="page" style={{ maxWidth: 1200 }}>
      <div className="section-header">
        <h1>Scrape Logs</h1>
        <span className="text-muted">{total} total entries</span>
      </div>

      <div className="admin-filters">
        <div className="admin-filter-row">
          <input
            type="text"
            placeholder="Filter by host..."
            value={hostFilter}
            onChange={(e) => setHostFilter(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
            className="admin-filter-input"
          />
          <select
            value={successFilter}
            onChange={(e) => { setSuccessFilter(e.target.value); setPage(1); }}
            className="admin-filter-select"
          >
            <option value="">All results</option>
            <option value="1">Successful</option>
            <option value="0">Failed</option>
          </select>
          <button className="btn btn-sm btn-outline" onClick={handleFilter}>Filter</button>
        </div>
      </div>

      {loading ? (
        <div className="loading-container">
          <div className="spinner" />
        </div>
      ) : logs.length === 0 ? (
        <div className="empty-state">
          <p>No scrape logs found.</p>
        </div>
      ) : (
        <>
          <div className="admin-table-wrapper">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>User</th>
                  <th>Host</th>
                  <th>Status</th>
                  <th>Name Found</th>
                  <th>Price</th>
                  <th>Duration</th>
                  <th>Size</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => (
                  <tr key={log.id} className={log.success ? '' : 'admin-row-error'}>
                    <td className="admin-cell-nowrap">{formatDate(log.created_at)}</td>
                    <td title={log.user_email}>{log.user_display_name}</td>
                    <td>{log.host}</td>
                    <td>
                      {log.success ? (
                        <span className="admin-badge admin-badge-success">OK</span>
                      ) : (
                        <span className="admin-badge admin-badge-error" title={log.error_message}>FAIL</span>
                      )}
                      {log.http_code && <span className="admin-http-code">{log.http_code}</span>}
                    </td>
                    <td className="admin-cell-truncate" title={log.extracted_name}>
                      {log.extracted_name || <span className="text-muted">-</span>}
                    </td>
                    <td>{log.extracted_price != null ? `$${log.extracted_price}` : <span className="text-muted">-</span>}</td>
                    <td>{log.duration_ms}ms</td>
                    <td>{formatBytes(log.html_length)}</td>
                    <td>
                      <button className="btn btn-sm btn-ghost" onClick={() => viewDetail(log.id)}>View</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
            <div className="admin-pagination">
              <button className="btn btn-sm btn-outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Prev</button>
              <span className="text-muted">Page {page} of {totalPages}</span>
              <button className="btn btn-sm btn-outline" disabled={page >= totalPages} onClick={() => setPage(page + 1)}>Next</button>
            </div>
          )}
        </>
      )}

      {(selectedLog || loadingDetail) && (
        <div className="modal-overlay" onClick={() => setSelectedLog(null)}>
          <div className="modal modal-wide" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 900, maxHeight: '90vh' }}>
            <div className="modal-header">
              <h2>Scrape Log Detail</h2>
              <button className="modal-close" onClick={() => setSelectedLog(null)}>&times;</button>
            </div>
            {loadingDetail ? (
              <div className="loading-container"><div className="spinner" /></div>
            ) : selectedLog && (
              <div className="admin-log-detail">
                <div className="admin-detail-grid">
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">URL</span>
                    <a href={selectedLog.url} target="_blank" rel="noopener noreferrer" className="admin-detail-value admin-cell-break">
                      {selectedLog.url}
                    </a>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">User</span>
                    <span className="admin-detail-value">{selectedLog.user_display_name} ({selectedLog.user_email})</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Time</span>
                    <span className="admin-detail-value">{selectedLog.created_at}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">HTTP Code</span>
                    <span className="admin-detail-value">{selectedLog.http_code ?? 'N/A'}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Duration</span>
                    <span className="admin-detail-value">{selectedLog.duration_ms}ms</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">HTML Size</span>
                    <span className="admin-detail-value">{formatBytes(selectedLog.html_length)}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Success</span>
                    <span className="admin-detail-value">
                      {selectedLog.success ? (
                        <span className="admin-badge admin-badge-success">Yes</span>
                      ) : (
                        <span className="admin-badge admin-badge-error">No</span>
                      )}
                    </span>
                  </div>
                  {selectedLog.error_message && (
                    <div className="admin-detail-row">
                      <span className="admin-detail-label">Error</span>
                      <span className="admin-detail-value" style={{ color: 'var(--color-danger)' }}>{selectedLog.error_message}</span>
                    </div>
                  )}
                </div>

                <h3 style={{ marginTop: '1rem' }}>Extracted Data</h3>
                <div className="admin-detail-grid">
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Name</span>
                    <span className="admin-detail-value">{selectedLog.extracted_name || '-'}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Price</span>
                    <span className="admin-detail-value">{selectedLog.extracted_price != null ? `$${selectedLog.extracted_price}` : '-'}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Store</span>
                    <span className="admin-detail-value">{selectedLog.extracted_store_name || '-'}</span>
                  </div>
                  <div className="admin-detail-row">
                    <span className="admin-detail-label">Image</span>
                    <span className="admin-detail-value">
                      {selectedLog.extracted_image_url ? (
                        <a href={selectedLog.extracted_image_url} target="_blank" rel="noopener noreferrer">View image</a>
                      ) : '-'}
                    </span>
                  </div>
                </div>

                {selectedLog.raw_html && (
                  <>
                    <h3 style={{ marginTop: '1rem' }}>Raw HTML</h3>
                    <pre className="admin-raw-html">{selectedLog.raw_html}</pre>
                  </>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function formatDate(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleString();
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

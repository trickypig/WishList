import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import Header from './components/layout/Header';
import ProtectedRoute from './components/layout/ProtectedRoute';
import AdminRoute from './components/layout/AdminRoute';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import ListDetailPage from './pages/ListDetailPage';
import FamiliesPage from './pages/FamiliesPage';
import FamilyManagePage from './pages/FamilyManagePage';
import FamilyListsPage from './pages/FamilyListsPage';
import SettingsPage from './pages/SettingsPage';
import AdminDashboardPage from './pages/admin/AdminDashboardPage';
import ScrapeLogsPage from './pages/admin/ScrapeLogsPage';

function App() {
  return (
    <AuthProvider>
      <Header />
      <main className="app-main">
        <div className="container">
          <Routes>
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route element={<ProtectedRoute />}>
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/lists/:id" element={<ListDetailPage />} />
              <Route path="/families" element={<FamiliesPage />} />
              <Route path="/families/:id" element={<FamilyManagePage />} />
              <Route path="/families/:id/lists" element={<FamilyListsPage />} />
              <Route path="/settings" element={<SettingsPage />} />
            </Route>
            <Route element={<AdminRoute />}>
              <Route path="/admin" element={<AdminDashboardPage />} />
              <Route path="/admin/scrape-logs" element={<ScrapeLogsPage />} />
            </Route>
          </Routes>
        </div>
      </main>
    </AuthProvider>
  );
}

export default App;

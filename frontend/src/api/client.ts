import type { User, WishList, Item, Family, Purchase, ScrapeLog, AdminStats } from '../types';

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080';

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };

  const token = localStorage.getItem('token');
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${BASE_URL}/api${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401) {
    localStorage.removeItem('token');
    window.location.href = '/login';
    throw new Error('Unauthorized');
  }

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(err.message || err.error || `Request failed with status ${res.status}`);
  }

  if (res.status === 204) {
    return undefined as T;
  }

  return res.json();
}

export const apiClient = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
};

// Auth
export function login(email: string, password: string) {
  return apiClient.post<{ token: string; user: User }>('/auth/login', { email, password });
}

export function register(email: string, password: string, display_name: string) {
  return apiClient.post<{ token: string; user: User }>('/auth/register', { email, password, display_name });
}

export function getMe() {
  return apiClient.get<{ user: User }>('/auth/me');
}

// Lists
export function getLists() {
  return apiClient.get<{ lists: WishList[] }>('/lists');
}

export function createList(data: { title: string; description: string; visibility: string }) {
  return apiClient.post<{ list: WishList }>('/lists', data);
}

export function getList(id: number) {
  return apiClient.get<{ list: WishList }>(`/lists/${id}`);
}

export function updateList(id: number, data: { title: string; description: string }) {
  return apiClient.put<{ list: WishList }>(`/lists/${id}`, data);
}

export function deleteList(id: number) {
  return apiClient.delete<void>(`/lists/${id}`);
}

export function updateListSharing(id: number, data: { visibility: string; family_ids: number[] }) {
  return apiClient.put<{ list: WishList }>(`/lists/${id}/sharing`, data);
}

export function getFamilyLists(familyId: number) {
  return apiClient.get<{ lists: WishList[] }>(`/lists/family/${familyId}`);
}

// Items
export function createItem(listId: number, data: {
  name: string;
  description?: string;
  price?: number | null;
  quantity_desired?: number;
  image_url?: string;
  sort_order?: number;
  links?: { url: string; store_name: string }[];
}) {
  return apiClient.post<{ item: Item }>(`/lists/${listId}/items`, data);
}

export function updateItem(id: number, data: {
  name: string;
  description?: string;
  price?: number | null;
  quantity_desired?: number;
  image_url?: string;
  sort_order?: number;
  links?: { url: string; store_name: string }[];
}) {
  return apiClient.put<{ item: Item }>(`/items/${id}`, data);
}

export function deleteItem(id: number) {
  return apiClient.delete<void>(`/items/${id}`);
}

export function reorderItems(listId: number, item_ids: number[]) {
  return apiClient.put<void>(`/lists/${listId}/items/reorder`, { item_ids });
}

// Purchases
export function purchaseItem(itemId: number, quantity: number) {
  return apiClient.post<{ purchase: Purchase }>(`/items/${itemId}/purchase`, { quantity });
}

export function unpurchaseItem(itemId: number) {
  return apiClient.delete<void>(`/items/${itemId}/purchase`);
}

// Families
export function getFamilies() {
  return apiClient.get<{ families: Family[] }>('/families');
}

export function createFamily(name: string) {
  return apiClient.post<{ family: Family }>('/families', { name });
}

export function getFamily(id: number) {
  return apiClient.get<{ family: Family }>(`/families/${id}`);
}

export function joinFamily(invite_code: string) {
  return apiClient.post<{ family: Family }>('/families/join', { invite_code });
}

export function inviteToFamily(familyId: number, email: string) {
  return apiClient.post<{ invite: unknown }>(`/families/${familyId}/invite`, { email });
}

export function removeFamilyMember(familyId: number, userId: number) {
  return apiClient.delete<void>(`/families/${familyId}/members/${userId}`);
}

// URL Scraping
export function scrapeUrl(url: string) {
  return apiClient.post<{
    url: string;
    name: string;
    price: number | null;
    image_url: string;
    store_name: string;
  }>('/scrape/url', { url });
}

// Admin
export function getAdminStats() {
  return apiClient.get<{ stats: AdminStats }>('/admin/stats');
}

export function getScrapeLogs(params?: { page?: number; per_page?: number; host?: string; success?: string; user_id?: number; has_error?: string }) {
  const query = new URLSearchParams();
  if (params?.page) query.set('page', String(params.page));
  if (params?.per_page) query.set('per_page', String(params.per_page));
  if (params?.host) query.set('host', params.host);
  if (params?.success) query.set('success', params.success);
  if (params?.user_id) query.set('user_id', String(params.user_id));
  if (params?.has_error) query.set('has_error', params.has_error);
  const qs = query.toString();
  return apiClient.get<{ logs: ScrapeLog[]; pagination: { page: number; per_page: number; total: number; total_pages: number } }>(`/admin/scrape-logs${qs ? '?' + qs : ''}`);
}

export function getScrapeLog(id: number) {
  return apiClient.get<{ log: ScrapeLog }>(`/admin/scrape-logs/${id}`);
}

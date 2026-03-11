export interface User {
  id: number;
  email: string;
  display_name: string;
  is_admin: number;
}

export interface ScrapeLog {
  id: number;
  user_id: number;
  url: string;
  host: string;
  http_code: number | null;
  html_length: number;
  extracted_name: string;
  extracted_price: number | null;
  extracted_image_url: string;
  extracted_store_name: string;
  success: number;
  error_message: string;
  duration_ms: number;
  created_at: string;
  user_display_name?: string;
  user_email?: string;
  raw_html?: string;
}

export interface AdminStats {
  total_users: number;
  total_lists: number;
  total_items: number;
  total_families: number;
  total_scrapes: number;
  successful_scrapes: number;
  failed_scrapes: number;
}

export interface WishList {
  id: number;
  user_id: number;
  title: string;
  description: string;
  visibility: 'private' | 'all_families' | 'specific_families';
  owner_name?: string;
  created_at: string;
  items?: Item[];
  family_ids?: number[];
}

export interface ItemLink {
  id?: number;
  item_id?: number;
  url: string;
  store_name: string;
}

export interface Item {
  id: number;
  list_id: number;
  name: string;
  description: string;
  price: number | null;
  quantity_desired: number;
  image_url: string;
  sort_order: number;
  links: ItemLink[];
  total_purchased?: number;
  purchased_by_me?: number;
}

export interface Family {
  id: number;
  name: string;
  invite_code: string;
  role: string;
  member_count?: number;
  created_by: number;
  members?: FamilyMember[];
}

export interface FamilyMember {
  user_id: number;
  display_name: string;
  email: string;
  role: string;
}

export interface Purchase {
  id: number;
  item_id: number;
  purchased_by: number;
  quantity_purchased: number;
}

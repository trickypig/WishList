export interface User {
  id: number;
  email: string;
  display_name: string;
  is_admin: number;
  is_child: number;
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
  visibility: 'private' | 'all_families' | 'selected_families' | 'specific_families';
  owner_name?: string;
  created_at: string;
  items?: Item[];
  suggestions?: Item[];
  groups?: ItemGroup[];
  family_ids?: number[];
  shared_families?: Pick<Family, 'id' | 'name'>[];
  /** Sent on list summaries (your lists, a family's lists) in place of the items. */
  item_count?: number;
}

export interface ItemGroup {
  id: number;
  list_id: number;
  title: string;
  description: string | null;
  sort_order: number;
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
  group_id: number | null;
  suggested_by?: number | null;
  suggested_by_name?: string | null;
  links: ItemLink[];
  total_purchased?: number;
  purchased_by_me?: number;
  purchaser_names?: string[];
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

export interface InvitePreview {
  family_id: number;
  family_name: string;
  inviter_name: string;
  email: string;
  account_exists: boolean;
  expires_at: string;
}

export interface FamilyInviteSummary {
  id: number;
  email: string;
  invited_by_name: string;
  created_at: string;
  expires_at: string;
  accepted_at: string | null;
  accepted_by_name: string | null;
  declined_at: string | null;
  declined_by_name: string | null;
}

export interface PendingInvite {
  id: number;
  family_id: number;
  family_name: string;
  inviter_name: string;
  created_at: string;
  expires_at: string;
}

export interface FamilyMember {
  user_id: number;
  display_name: string;
  email: string;
  role: string;
  is_child?: number;
}

export interface AllowedDomain {
  domain: string;
  display_name: string;
  icon_url: string;
  source: 'default' | 'family';
}

export interface Purchase {
  id: number;
  item_id: number;
  purchased_by: number;
  quantity_purchased: number;
}

// API'den gelen veri şekilleri (backend app/Http/Resources ile aynı).

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
};

export type Wallet = {
  id: number;
  branch: {
    id: number;
    name: string;
    display_name: string;
    logo_url: string | null;
    reward_rate: string;
    reward_terms: string | null;
  };
  balance: string;
  total_earned: string;
  total_redeemed: string;
};

export type Transaction = {
  id: number;
  type: 'earn' | 'redeem' | 'correction';
  label: string;
  amount: string;
  spend_amount: string | null;
  reward_rate: string | null;
  campaign: { id: number; title: string } | null;
  balance_after: string;
  related_transaction_id: number | null;
  note: string | null;
  created_at: string;
};

export type ApprovalRequest = {
  id: number;
  type: 'redeem' | 'correction';
  title: string;
  status: string;
  branch: { id: number; display_name: string };
  amount: string;
  current_balance: string;
  balance_after: string;
  requested_by: string | null;
  reason: string | null;
  related_transaction: { id: number; type: string; amount: string; created_at: string } | null;
  failure_reason: string | null;
  created_at: string;
  responded_at: string | null;
};

export type Rating = { average: string | null; count: number; label: string };

export type BranchCard = {
  id: number;
  name: string;
  display_name: string;
  slug: string;
  business: { id: number; name: string; slug: string; logo_url: string | null };
  category: { id: number; name: string } | null;
  location: {
    city: string | null;
    district: string | null;
    latitude: string | null;
    longitude: string | null;
    distance_km: number | null;
  };
  reward_rate: string;
  reward_label: string;
  rating: Rating;
  is_sponsored: boolean;
  has_campaign: boolean;
};

export type Campaign = {
  id: number;
  title: string;
  description: string | null;
  image_url: string | null;
  terms: string | null;
  type: string;
  reward_rate: string | null;
  weekdays: number[];
  new_customers_only: boolean;
  birthday_only: boolean;
  conditions: string[];
  starts_at: string | null;
  ends_at: string | null;
  branch?: BranchCard;
};

export type Review = {
  id: number;
  score: number;
  score_label: string;
  comment: string | null;
  author: string | null;
  status?: string;
  reply: { body: string; created_at: string } | null;
  branch?: { id: number; display_name: string };
  created_at: string;
  updated_at: string;
};

export type MenuItem = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  image_url: string | null;
  is_available: boolean;
};

export type BranchDetail = BranchCard & {
  description: string | null;
  business_description: string | null;
  address: string | null;
  phone: string | null;
  email: string | null;
  reward_terms: string | null;
  hours: { weekday: number; is_closed: boolean; opens_at: string | null; closes_at: string | null }[];
  photos: string[];
  campaigns: Campaign[];
  menu: { id: number; name: string; items: MenuItem[] }[];
  other_branches: { id: number; name: string; district: string | null; reward_rate: string }[];
  rating_distribution: Record<string, number>;
  viewer: {
    is_favorite: boolean;
    wallet: { id: number; balance: string } | null;
    can_review: boolean;
    my_review: Review | null;
  } | null;
};

export type Lookup = { id: number; name: string };

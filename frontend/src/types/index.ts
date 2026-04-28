export interface User {
  id: number
  name: string
  email: string
  roles?: string[]
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

// Invoice
export interface InvoiceItem {
  id?: number
  description: string
  quantity: number
  unit_price: number
  tax_percent: number
  line_subtotal: number
  line_tax_total: number
  line_total: number
}

export interface Invoice {
  id: number
  invoice_number: string
  invoice_date: string
  due_date: string | null
  customer_name: string
  status: 'draft' | 'sent' | 'paid' | 'cancelled'
  notes: string | null
  subtotal: string
  tax_total: string
  grand_total: string
  created_by: number
  items: InvoiceItem[]
}

// Purchase Bill
export interface PurchaseBillItem {
  id?: number
  description: string
  quantity: number
  unit_price: number
  tax_percent: number
  line_subtotal: number
  line_tax_total: number
  line_total: number
}

export interface PurchaseBill {
  id: number
  purchase_number: string
  purchase_date: string
  due_date: string | null
  supplier_name: string
  status: 'draft' | 'received' | 'paid' | 'cancelled'
  notes: string | null
  subtotal: string
  tax_total: string
  grand_total: string
  created_by: number
  items: PurchaseBillItem[]
}

// Expense
export interface Expense {
  id: number
  expense_number: string
  expense_date: string
  category: string
  payee_name: string | null
  description: string
  subtotal: string
  tax_total: string
  grand_total: string
  reference: string | null
  status: 'draft' | 'paid' | 'cancelled'
  notes: string | null
  created_by: number
}

// Inventory
export interface InventoryItem {
  id: number
  sku: string
  name: string
  unit: string
  quantity_on_hand: number
  reorder_level: number
  created_by: number
}

export interface InventoryMovement {
  id: number
  inventory_item_id: number
  type: 'in' | 'out' | 'adjustment'
  quantity_change: number
  quantity_after: number
  note: string | null
  created_at: string
  creator?: { id: number; name: string }
}

// Fixed Asset
export interface FixedAsset {
  id: number
  asset_code: string
  name: string
  description: string | null
  location: string | null
  acquisition_date: string
  cost: number
  salvage_value: number | null
  useful_life_months: number
  depreciation_method: 'straight_line'
  accumulated_depreciation: number
  book_value: number
  status: 'active' | 'fully_depreciated' | 'disposed'
  created_by: number
  depreciation_entries_count?: number
}

export interface DepreciationEntry {
  id: number
  fixed_asset_id: number
  period_year: number
  period_month: number
  amount: number
  book_value_after: number
  accumulated_depreciation?: number
  depreciation_amount?: number
  status?: string
  note: string | null
  created_at: string
  creator?: { id: number; name: string }
}

// Account
export interface Account {
  id: number
  code: string
  name: string
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
  parent_id: number | null
  is_postable: boolean
  is_active: boolean
  sort_order: number
}

// Journal
export interface JournalEntryLine {
  id?: number
  account_id: number
  debit: number
  credit: number
  description: string | null
  sort_order: number
  account?: Account
}

export interface JournalEntry {
  id: number
  journal_number: string
  entry_date: string
  reference: string | null
  description: string | null
  status: 'draft' | 'posted'
  posted_at: string | null
  source_type: string | null
  source_id: number | null
  created_by: number
  lines: JournalEntryLine[]
}

// Reports
export interface AccountBalance {
  code: string
  name: string
  type: string
  total_debit: number
  total_credit: number
  balance: number
}

export interface IncomeStatement {
  period: { start_date: string; end_date: string }
  revenue: { accounts: AccountBalance[]; total_revenue: number }
  expense: { accounts: AccountBalance[]; total_expense: number }
  net_income: number
  is_profit: boolean
}

export interface BalanceSheet {
  as_of: string
  assets: { accounts: AccountBalance[]; total_assets: number }
  liabilities: { accounts: AccountBalance[]; total_liabilities: number }
  equity: { accounts: AccountBalance[]; total_equity: number }
  total_liabilities_and_equity: number
  is_balanced: boolean
}

export interface CashBookRow {
  date: string
  journal_number: string
  description: string | null
  account_code: string
  account_name: string
  pemasukan: number | null
  pengeluaran: number | null
  saldo: number
}

export interface CashBook {
  period: { start_date: string; end_date: string }
  account_codes: string[]
  opening_balance: number
  total_pemasukan: number
  total_pengeluaran: number
  closing_balance: number
  rows: CashBookRow[]
}

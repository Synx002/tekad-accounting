import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useAuthStore } from '@/store/auth'
import AppLayout from '@/components/layout/AppLayout'

// Pages
import LoginPage from '@/pages/auth/LoginPage'
import DashboardPage from '@/pages/dashboard/DashboardPage'
import InvoicesPage from '@/pages/invoices/InvoicesPage'
import InvoiceFormPage from '@/pages/invoices/InvoiceFormPage'
import PurchaseBillsPage from '@/pages/purchase-bills/PurchaseBillsPage'
import PurchaseBillFormPage from '@/pages/purchase-bills/PurchaseBillFormPage'
import ExpensesPage from '@/pages/expenses/ExpensesPage'
import ExpenseFormPage from '@/pages/expenses/ExpenseFormPage'
import InventoryPage from '@/pages/inventory/InventoryPage'
import InventoryFormPage from '@/pages/inventory/InventoryFormPage'
import InventoryMovementsPage from '@/pages/inventory/InventoryMovementsPage'
import FixedAssetsPage from '@/pages/fixed-assets/FixedAssetsPage'
import FixedAssetFormPage from '@/pages/fixed-assets/FixedAssetFormPage'
import FixedAssetDepreciationPage from '@/pages/fixed-assets/FixedAssetDepreciationPage'
import CashBookPage from '@/pages/reports/CashBookPage'
import ReportsPage from '@/pages/reports/ReportsPage'
import JournalEntriesPage from '@/pages/journal-entries/JournalEntriesPage'
import JournalEntryFormPage from '@/pages/journal-entries/JournalEntryFormPage'
import JournalEntryDetailPage from '@/pages/journal-entries/JournalEntryDetailPage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 1000 * 60,
    },
  },
})

function PrivateRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated())
  return isAuthenticated ? <AppLayout>{children}</AppLayout> : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route path="/dashboard" element={<PrivateRoute><DashboardPage /></PrivateRoute>} />

          <Route path="/invoices" element={<PrivateRoute><InvoicesPage /></PrivateRoute>} />
          <Route path="/invoices/create" element={<PrivateRoute><InvoiceFormPage /></PrivateRoute>} />
          <Route path="/invoices/:id/edit" element={<PrivateRoute><InvoiceFormPage /></PrivateRoute>} />

          <Route path="/purchase-bills" element={<PrivateRoute><PurchaseBillsPage /></PrivateRoute>} />
          <Route path="/purchase-bills/create" element={<PrivateRoute><PurchaseBillFormPage /></PrivateRoute>} />
          <Route path="/purchase-bills/:id/edit" element={<PrivateRoute><PurchaseBillFormPage /></PrivateRoute>} />

          <Route path="/expenses" element={<PrivateRoute><ExpensesPage /></PrivateRoute>} />
          <Route path="/expenses/create" element={<PrivateRoute><ExpenseFormPage /></PrivateRoute>} />
          <Route path="/expenses/:id/edit" element={<PrivateRoute><ExpenseFormPage /></PrivateRoute>} />

          <Route path="/inventory" element={<PrivateRoute><InventoryPage /></PrivateRoute>} />
          <Route path="/inventory/create" element={<PrivateRoute><InventoryFormPage /></PrivateRoute>} />
          <Route path="/inventory/:id/edit" element={<PrivateRoute><InventoryFormPage /></PrivateRoute>} />
          <Route path="/inventory/:id/movements" element={<PrivateRoute><InventoryMovementsPage /></PrivateRoute>} />

          <Route path="/fixed-assets" element={<PrivateRoute><FixedAssetsPage /></PrivateRoute>} />
          <Route path="/fixed-assets/create" element={<PrivateRoute><FixedAssetFormPage /></PrivateRoute>} />
          <Route path="/fixed-assets/:id/edit" element={<PrivateRoute><FixedAssetFormPage /></PrivateRoute>} />
          <Route path="/fixed-assets/:id/depreciation" element={<PrivateRoute><FixedAssetDepreciationPage /></PrivateRoute>} />

          <Route path="/cash-book" element={<PrivateRoute><CashBookPage /></PrivateRoute>} />
          <Route path="/reports" element={<PrivateRoute><ReportsPage /></PrivateRoute>} />

          <Route path="/journal-entries" element={<PrivateRoute><JournalEntriesPage /></PrivateRoute>} />
          <Route path="/journal-entries/create" element={<PrivateRoute><JournalEntryFormPage /></PrivateRoute>} />
          <Route path="/journal-entries/:id" element={<PrivateRoute><JournalEntryDetailPage /></PrivateRoute>} />
          <Route path="/journal-entries/:id/edit" element={<PrivateRoute><JournalEntryFormPage /></PrivateRoute>} />

          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

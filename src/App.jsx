import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import { ToastProvider } from './context/ToastContext'
import Layout from './components/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Assets from './pages/Assets'
import AssetDetail from './pages/AssetDetail'
import AddAsset from './pages/AddAsset'
import Checkout from './pages/Checkout'
import Checkin from './pages/Checkin'
import Scanner from './pages/Scanner'
import CrewCheckout from './pages/CrewCheckout'
import Requests from './pages/Requests'
import GearRequest from './pages/GearRequest'
import Maintenance from './pages/Maintenance'
import Reports from './pages/Reports'
import Users from './pages/Users'
import Settings from './pages/Settings'
import Kits from './pages/Kits'
import Calendar from './pages/Calendar'
import AuditLog from './pages/AuditLog'
import EquipmentStatus from './pages/EquipmentStatus'
import CheckoutReceipt from './pages/CheckoutReceipt'
import UsageAnalytics from './pages/UsageAnalytics'

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="min-h-screen bg-neofox-darker flex items-center justify-center">
        <div className="text-center">
          <div className="text-4xl font-bold text-neofox-yellow mb-4">FOXY</div>
          <div className="w-48 h-1 bg-neofox-gray rounded-full overflow-hidden">
            <div className="h-full bg-neofox-yellow animate-[loading_1.5s_ease-in-out_infinite]" style={{width: '30%'}}></div>
          </div>
        </div>
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  return children
}

function PublicRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return null
  }

  if (user) {
    return <Navigate to="/" replace />
  }

  return children
}

function App() {
  return (
    <Router>
      <AuthProvider>
        <ToastProvider>
          <Routes>
            <Route path="/login" element={
              <PublicRoute>
                <Login />
              </PublicRoute>
            } />
            <Route path="/request" element={<GearRequest />} />
            <Route path="/" element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }>
              <Route index element={<Dashboard />} />
              <Route path="assets" element={<Assets />} />
              <Route path="assets/add" element={<AddAsset />} />
              <Route path="assets/:id" element={<AssetDetail />} />
              <Route path="checkout" element={<Checkout />} />
              <Route path="checkout/receipt" element={<CheckoutReceipt />} />
              <Route path="checkin" element={<Checkin />} />
              <Route path="scanner" element={<Scanner />} />
              <Route path="crew-checkout" element={<CrewCheckout />} />
              <Route path="kits" element={<Kits />} />
              <Route path="calendar" element={<Calendar />} />
              <Route path="requests" element={<Requests />} />
              <Route path="status" element={<EquipmentStatus />} />
              <Route path="audit-log" element={<AuditLog />} />
              <Route path="maintenance" element={<Maintenance />} />
              <Route path="reports" element={<Reports />} />
              <Route path="analytics" element={<UsageAnalytics />} />
              <Route path="users" element={<Users />} />
              <Route path="settings" element={<Settings />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </ToastProvider>
      </AuthProvider>
    </Router>
  )
}

export default App

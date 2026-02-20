import { useState, useEffect } from 'react'
import { Outlet, NavLink, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import {
  LayoutDashboard,
  Package,
  LogOut as LogOutIcon,
  LogIn,
  QrCode,
  Users,
  Wrench,
  FileText,
  Settings,
  Menu,
  X,
  Bell,
  Search,
  ChevronDown,
  UserCircle,
  ClipboardList,
  Users2,
  Boxes,
  Calendar,
  Activity,
  History,
  BarChart3
} from 'lucide-react'

// Navigation items with role-based access
// roles: 'all' = everyone, 'manager' = admin + equipment_manager, 'admin' = admin only
const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard, roles: 'all' },
  { name: 'Equipment', href: '/assets', icon: Package, roles: 'all' },
  { name: 'Equipment Status', href: '/status', icon: Activity, roles: 'manager' },
  { name: 'Kits', href: '/kits', icon: Boxes, roles: 'manager' },
  { name: 'Calendar', href: '/calendar', icon: Calendar, roles: 'all' },
  { name: 'QR Scanner', href: '/scanner', icon: QrCode, roles: 'manager' },
  { name: 'Check Out', href: '/checkout', icon: LogOutIcon, roles: 'manager' },
  { name: 'Check In', href: '/checkin', icon: LogIn, roles: 'manager' },
  { name: 'Crew Checkout', href: '/crew-checkout', icon: Users2, roles: 'manager' },
  { name: 'Requests', href: '/requests', icon: ClipboardList, roles: 'all' },
  { name: 'Maintenance', href: '/maintenance', icon: Wrench, roles: 'manager' },
  { name: 'Reports', href: '/reports', icon: FileText, roles: 'manager' },
  { name: 'Analytics', href: '/analytics', icon: BarChart3, roles: 'manager' },
  { name: 'Audit Log', href: '/audit-log', icon: History, roles: 'manager' },
  { name: 'Users', href: '/users', icon: Users, roles: 'admin' },
  { name: 'Settings', href: '/settings', icon: Settings, roles: 'all' },
]

export default function Layout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const { user, logout, isAdmin } = useAuth()
  const location = useLocation()

  // Close sidebar on route change (mobile)
  useEffect(() => {
    setSidebarOpen(false)
  }, [location.pathname])

  // Filter navigation based on user role
  const filteredNavigation = navigation.filter(item => {
    const userRole = user?.role || 'team_member'
    if (item.roles === 'all') return true
    if (item.roles === 'admin') return userRole === 'admin'
    if (item.roles === 'manager') return userRole === 'admin' || userRole === 'equipment_manager'
    return false
  })

  return (
    <div className="min-h-screen bg-neofox-darker">
      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed top-0 left-0 z-50 h-full w-64 bg-neofox-dark border-r border-neofox-gray transform transition-transform duration-200 ease-in-out lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Logo */}
        <div className="flex items-center justify-between h-16 px-6 border-b border-neofox-gray">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-neofox-yellow rounded-lg flex items-center justify-center">
              <Package className="w-5 h-5 text-black" />
            </div>
            <span className="text-xl font-bold text-neofox-yellow">FOXY</span>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden text-gray-400 hover:text-white"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation */}
        <nav className="p-4 space-y-1 overflow-y-auto h-[calc(100%-4rem)]">
          {filteredNavigation.map((item) => (
            <NavLink
              key={item.name}
              to={item.href}
              className={({ isActive }) =>
                `nav-link ${isActive ? 'active' : ''}`
              }
            >
              <item.icon className="w-5 h-5" />
              <span>{item.name}</span>
            </NavLink>
          ))}

          {/* Logout at bottom */}
          <div className="pt-4 mt-4 border-t border-neofox-gray">
            <button
              onClick={logout}
              className="nav-link w-full text-red-400 hover:text-red-300 hover:bg-red-500/10"
            >
              <LogOutIcon className="w-5 h-5" />
              <span>Sign Out</span>
            </button>
          </div>
        </nav>
      </aside>

      {/* Main content */}
      <div className="lg:pl-64">
        {/* Top bar */}
        <header className="sticky top-0 z-30 h-16 bg-neofox-dark/95 backdrop-blur border-b border-neofox-gray">
          <div className="flex items-center justify-between h-full px-4 lg:px-6">
            {/* Left side */}
            <div className="flex items-center gap-4">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden text-gray-400 hover:text-white"
              >
                <Menu className="w-6 h-6" />
              </button>

              {/* Search */}
              <div className="hidden sm:flex items-center gap-2 bg-neofox-darker rounded-lg px-3 py-2 w-64">
                <Search className="w-4 h-4 text-gray-500" />
                <input
                  type="text"
                  placeholder="Search equipment..."
                  className="bg-transparent border-none outline-none text-sm w-full text-white placeholder-gray-500"
                />
              </div>
            </div>

            {/* Right side */}
            <div className="flex items-center gap-4">
              {/* Notifications */}
              <button className="relative p-2 text-gray-400 hover:text-white rounded-lg hover:bg-neofox-gray transition-colors">
                <Bell className="w-5 h-5" />
                <span className="absolute top-1 right-1 w-2 h-2 bg-neofox-yellow rounded-full"></span>
              </button>

              {/* User menu */}
              <div className="relative">
                <button
                  onClick={() => setUserMenuOpen(!userMenuOpen)}
                  className="flex items-center gap-2 p-2 rounded-lg hover:bg-neofox-gray transition-colors"
                >
                  <div className="w-8 h-8 bg-neofox-yellow/20 rounded-full flex items-center justify-center">
                    <UserCircle className="w-5 h-5 text-neofox-yellow" />
                  </div>
                  <span className="hidden sm:block text-sm font-medium">{user?.username}</span>
                  <ChevronDown className="w-4 h-4 text-gray-400" />
                </button>

                {userMenuOpen && (
                  <>
                    <div
                      className="fixed inset-0 z-10"
                      onClick={() => setUserMenuOpen(false)}
                    />
                    <div className="absolute right-0 mt-2 w-48 bg-neofox-dark border border-neofox-gray rounded-lg shadow-xl z-20">
                      <div className="p-3 border-b border-neofox-gray">
                        <p className="font-medium">{user?.username}</p>
                        <p className="text-sm text-gray-400">{user?.email}</p>
                        <span className="inline-block mt-1 px-2 py-0.5 bg-neofox-yellow/20 text-neofox-yellow text-xs rounded-full">
                          {user?.role}
                        </span>
                      </div>
                      <div className="p-2">
                        <NavLink
                          to="/settings"
                          className="flex items-center gap-2 px-3 py-2 text-sm text-gray-300 hover:bg-neofox-gray rounded-lg"
                          onClick={() => setUserMenuOpen(false)}
                        >
                          <Settings className="w-4 h-4" />
                          Settings
                        </NavLink>
                        <button
                          onClick={() => {
                            setUserMenuOpen(false)
                            logout()
                          }}
                          className="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-400 hover:bg-red-500/10 rounded-lg"
                        >
                          <LogOutIcon className="w-4 h-4" />
                          Sign Out
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>
        </header>

        {/* Page content */}
        <main className="p-4 lg:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

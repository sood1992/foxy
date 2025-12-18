import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { statsApi, assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  Package,
  CheckCircle,
  Clock,
  AlertTriangle,
  Wrench,
  TrendingUp,
  ArrowRight,
  Calendar,
  User,
  Camera,
  Mic,
  Lightbulb,
  HardDrive,
  MoreHorizontal
} from 'lucide-react'
import { format, formatDistanceToNow, isPast, parseISO } from 'date-fns'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts'

const categoryIcons = {
  Camera: Camera,
  Audio: Mic,
  Lighting: Lightbulb,
  Lens: Camera,
  Storage: HardDrive,
  default: Package
}

const COLORS = ['#FFD700', '#4CAF50', '#2196F3', '#FF5722', '#9C27B0', '#00BCD4']

function StatCard({ title, value, icon: Icon, color, subtext, link }) {
  const content = (
    <div className={`stat-card group ${link ? 'cursor-pointer' : ''}`}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-400 mb-1">{title}</p>
          <p className="text-3xl font-bold">{value}</p>
          {subtext && <p className="text-sm text-gray-500 mt-1">{subtext}</p>}
        </div>
        <div className={`p-3 rounded-xl ${color}`}>
          <Icon className="w-6 h-6" />
        </div>
      </div>
      {link && (
        <div className="mt-4 flex items-center gap-1 text-sm text-neofox-yellow opacity-0 group-hover:opacity-100 transition-opacity">
          View all <ArrowRight className="w-4 h-4" />
        </div>
      )}
    </div>
  )

  if (link) {
    return <Link to={link}>{content}</Link>
  }
  return content
}

function RecentActivity({ transactions }) {
  if (!transactions?.length) {
    return (
      <div className="text-center py-8 text-gray-500">
        No recent activity
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {transactions.slice(0, 5).map((tx, idx) => (
        <div key={idx} className="flex items-center gap-4 p-3 bg-neofox-darker rounded-lg">
          <div className={`p-2 rounded-lg ${tx.transaction_type === 'checkout' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-green-500/20 text-green-400'}`}>
            {tx.transaction_type === 'checkout' ? <Clock className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
          </div>
          <div className="flex-1 min-w-0">
            <p className="font-medium truncate">{tx.asset_name || tx.asset_id}</p>
            <p className="text-sm text-gray-400">
              {tx.transaction_type === 'checkout' ? 'Checked out by' : 'Returned by'} {tx.borrower_name}
            </p>
          </div>
          <div className="text-right text-sm text-gray-500">
            {formatDistanceToNow(parseISO(tx.transaction_date), { addSuffix: true })}
          </div>
        </div>
      ))}
    </div>
  )
}

function OverdueItems({ items }) {
  if (!items?.length) {
    return (
      <div className="text-center py-8 text-gray-500">
        <CheckCircle className="w-12 h-12 mx-auto mb-2 text-green-500/50" />
        <p>No overdue items</p>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {items.map((item, idx) => (
        <Link
          key={idx}
          to={`/assets/${item.asset_id}`}
          className="flex items-center gap-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg hover:bg-red-500/20 transition-colors"
        >
          <div className="p-2 rounded-lg bg-red-500/20 text-red-400">
            <AlertTriangle className="w-4 h-4" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="font-medium truncate">{item.asset_name}</p>
            <p className="text-sm text-gray-400">
              <User className="w-3 h-3 inline mr-1" />
              {item.current_borrower}
            </p>
          </div>
          <div className="text-right">
            <p className="text-sm text-red-400 font-medium">
              {formatDistanceToNow(parseISO(item.expected_return_date))} overdue
            </p>
          </div>
        </Link>
      ))}
    </div>
  )
}

export default function Dashboard() {
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const toast = useToast()

  useEffect(() => {
    loadDashboard()
  }, [])

  const loadDashboard = async () => {
    try {
      const data = await statsApi.getDashboard()
      setStats(data)
    } catch (error) {
      toast.error('Failed to load dashboard')
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="card p-6">
              <div className="skeleton h-4 w-24 mb-3" />
              <div className="skeleton h-8 w-16" />
            </div>
          ))}
        </div>
      </div>
    )
  }

  const categoryData = stats?.categoryBreakdown || []
  const recentTransactions = stats?.recentTransactions || []
  const overdueItems = stats?.overdueItems || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Dashboard</h1>
          <p className="text-gray-400">Welcome back! Here's your inventory overview.</p>
        </div>
        <div className="flex gap-3">
          <Link to="/checkout" className="btn-primary">
            Quick Checkout
          </Link>
          <Link to="/scanner" className="btn-secondary">
            Scan QR
          </Link>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="Total Equipment"
          value={stats?.totalAssets || 0}
          icon={Package}
          color="bg-blue-500/20 text-blue-400"
          link="/assets"
        />
        <StatCard
          title="Available"
          value={stats?.available || 0}
          icon={CheckCircle}
          color="bg-green-500/20 text-green-400"
          subtext={`${Math.round((stats?.available / stats?.totalAssets) * 100) || 0}% of total`}
        />
        <StatCard
          title="Checked Out"
          value={stats?.checkedOut || 0}
          icon={Clock}
          color="bg-yellow-500/20 text-yellow-400"
          link="/reports"
        />
        <StatCard
          title="Overdue"
          value={stats?.overdue || 0}
          icon={AlertTriangle}
          color="bg-red-500/20 text-red-400"
        />
      </div>

      {/* Charts and Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Category Breakdown */}
        <div className="card lg:col-span-1">
          <div className="card-header">
            <h3 className="font-semibold">Equipment by Category</h3>
          </div>
          <div className="card-body">
            {categoryData.length > 0 ? (
              <ResponsiveContainer width="100%" height={250}>
                <PieChart>
                  <Pie
                    data={categoryData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="count"
                    nameKey="category"
                  >
                    {categoryData.map((entry, index) => (
                      <Cell key={entry.category} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#1a1a1a',
                      border: '1px solid #333',
                      borderRadius: '8px'
                    }}
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[250px] flex items-center justify-center text-gray-500">
                No data available
              </div>
            )}
            <div className="grid grid-cols-2 gap-2 mt-4">
              {categoryData.slice(0, 6).map((cat, idx) => (
                <div key={cat.category} className="flex items-center gap-2 text-sm">
                  <div
                    className="w-3 h-3 rounded-full"
                    style={{ backgroundColor: COLORS[idx % COLORS.length] }}
                  />
                  <span className="text-gray-400 truncate">{cat.category}</span>
                  <span className="ml-auto font-medium">{cat.count}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Recent Activity */}
        <div className="card lg:col-span-2">
          <div className="card-header flex items-center justify-between">
            <h3 className="font-semibold">Recent Activity</h3>
            <Link to="/reports" className="text-sm text-neofox-yellow hover:underline flex items-center gap-1">
              View all <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
          <div className="card-body">
            <RecentActivity transactions={recentTransactions} />
          </div>
        </div>
      </div>

      {/* Overdue and Maintenance */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Overdue Items */}
        <div className="card">
          <div className="card-header flex items-center justify-between">
            <h3 className="font-semibold text-red-400">
              <AlertTriangle className="w-5 h-5 inline mr-2" />
              Overdue Equipment
            </h3>
            {overdueItems.length > 0 && (
              <span className="bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full text-sm">
                {overdueItems.length} items
              </span>
            )}
          </div>
          <div className="card-body">
            <OverdueItems items={overdueItems} />
          </div>
        </div>

        {/* Quick Stats */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold">Quick Actions</h3>
          </div>
          <div className="card-body grid grid-cols-2 gap-4">
            <Link
              to="/assets/add"
              className="p-4 bg-neofox-darker rounded-lg hover:bg-neofox-gray transition-colors text-center"
            >
              <Package className="w-8 h-8 mx-auto mb-2 text-neofox-yellow" />
              <p className="font-medium">Add Equipment</p>
            </Link>
            <Link
              to="/crew-checkout"
              className="p-4 bg-neofox-darker rounded-lg hover:bg-neofox-gray transition-colors text-center"
            >
              <User className="w-8 h-8 mx-auto mb-2 text-blue-400" />
              <p className="font-medium">Crew Checkout</p>
            </Link>
            <Link
              to="/maintenance"
              className="p-4 bg-neofox-darker rounded-lg hover:bg-neofox-gray transition-colors text-center"
            >
              <Wrench className="w-8 h-8 mx-auto mb-2 text-orange-400" />
              <p className="font-medium">Maintenance</p>
            </Link>
            <Link
              to="/reports"
              className="p-4 bg-neofox-darker rounded-lg hover:bg-neofox-gray transition-colors text-center"
            >
              <TrendingUp className="w-8 h-8 mx-auto mb-2 text-green-400" />
              <p className="font-medium">View Reports</p>
            </Link>
          </div>
        </div>
      </div>

      {/* Status Summary */}
      <div className="card">
        <div className="card-header">
          <h3 className="font-semibold">Equipment Status Summary</h3>
        </div>
        <div className="card-body">
          <div className="flex gap-4 mb-4">
            <div className="flex-1 bg-green-500/20 rounded-lg p-4 border border-green-500/30">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle className="w-5 h-5 text-green-400" />
                <span className="text-green-400 font-medium">Available</span>
              </div>
              <p className="text-2xl font-bold">{stats?.available || 0}</p>
            </div>
            <div className="flex-1 bg-yellow-500/20 rounded-lg p-4 border border-yellow-500/30">
              <div className="flex items-center gap-2 mb-2">
                <Clock className="w-5 h-5 text-yellow-400" />
                <span className="text-yellow-400 font-medium">Checked Out</span>
              </div>
              <p className="text-2xl font-bold">{stats?.checkedOut || 0}</p>
            </div>
            <div className="flex-1 bg-orange-500/20 rounded-lg p-4 border border-orange-500/30">
              <div className="flex items-center gap-2 mb-2">
                <Wrench className="w-5 h-5 text-orange-400" />
                <span className="text-orange-400 font-medium">Maintenance</span>
              </div>
              <p className="text-2xl font-bold">{stats?.maintenance || 0}</p>
            </div>
            <div className="flex-1 bg-red-500/20 rounded-lg p-4 border border-red-500/30">
              <div className="flex items-center gap-2 mb-2">
                <AlertTriangle className="w-5 h-5 text-red-400" />
                <span className="text-red-400 font-medium">Lost</span>
              </div>
              <p className="text-2xl font-bold">{stats?.lost || 0}</p>
            </div>
          </div>
          {/* Progress bar */}
          <div className="h-3 bg-neofox-darker rounded-full overflow-hidden flex">
            <div
              className="bg-green-500 h-full"
              style={{ width: `${(stats?.available / stats?.totalAssets) * 100}%` }}
            />
            <div
              className="bg-yellow-500 h-full"
              style={{ width: `${(stats?.checkedOut / stats?.totalAssets) * 100}%` }}
            />
            <div
              className="bg-orange-500 h-full"
              style={{ width: `${(stats?.maintenance / stats?.totalAssets) * 100}%` }}
            />
            <div
              className="bg-red-500 h-full"
              style={{ width: `${(stats?.lost / stats?.totalAssets) * 100}%` }}
            />
          </div>
        </div>
      </div>
    </div>
  )
}

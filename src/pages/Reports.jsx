import { useState, useEffect } from 'react'
import { transactionApi, statsApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  FileText,
  Download,
  Calendar,
  Filter,
  Search,
  LogOut,
  LogIn,
  TrendingUp,
  Loader2,
  ChevronDown
} from 'lucide-react'
import { format, parseISO, subDays, startOfMonth, endOfMonth } from 'date-fns'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, LineChart, Line, PieChart, Pie, Cell } from 'recharts'

const COLORS = ['#FFD700', '#4CAF50', '#2196F3', '#FF5722', '#9C27B0']

export default function Reports() {
  const [transactions, setTransactions] = useState([])
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [dateRange, setDateRange] = useState('30')
  const [typeFilter, setTypeFilter] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const toast = useToast()

  useEffect(() => {
    loadData()
  }, [dateRange])

  const loadData = async () => {
    setLoading(true)
    try {
      const [transRes, statsRes] = await Promise.all([
        transactionApi.getAll({ days: dateRange }),
        statsApi.getReports({ days: dateRange })
      ])
      setTransactions(transRes.transactions || [])
      setStats(statsRes)
    } catch (error) {
      toast.error('Failed to load reports')
    } finally {
      setLoading(false)
    }
  }

  const filteredTransactions = transactions.filter(tx => {
    const matchesType = !typeFilter || tx.transaction_type === typeFilter
    const matchesSearch = !searchQuery ||
      tx.asset_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      tx.asset_id?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      tx.borrower_name?.toLowerCase().includes(searchQuery.toLowerCase())
    return matchesType && matchesSearch
  })

  const exportCSV = () => {
    const headers = ['Date', 'Asset ID', 'Asset Name', 'Type', 'Borrower', 'Purpose']
    const rows = filteredTransactions.map(tx => [
      format(parseISO(tx.transaction_date), 'yyyy-MM-dd HH:mm'),
      tx.asset_id,
      tx.asset_name || '',
      tx.transaction_type,
      tx.borrower_name,
      tx.purpose || ''
    ])

    const csv = [headers, ...rows].map(row => row.join(',')).join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `foxy-transactions-${format(new Date(), 'yyyy-MM-dd')}.csv`
    a.click()
  }

  const checkoutCount = transactions.filter(t => t.transaction_type === 'checkout').length
  const checkinCount = transactions.filter(t => t.transaction_type === 'checkin').length

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-neofox-yellow" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Reports & Analytics</h1>
          <p className="text-gray-400">Track equipment usage and activity</p>
        </div>
        <button onClick={exportCSV} className="btn-secondary flex items-center gap-2">
          <Download className="w-5 h-5" />
          Export CSV
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div className="stat-card">
          <p className="text-sm text-gray-400">Total Transactions</p>
          <p className="text-2xl font-bold">{transactions.length}</p>
        </div>
        <div className="stat-card">
          <div className="flex items-center gap-2 mb-1">
            <LogOut className="w-4 h-4 text-yellow-400" />
            <p className="text-sm text-gray-400">Checkouts</p>
          </div>
          <p className="text-2xl font-bold text-yellow-400">{checkoutCount}</p>
        </div>
        <div className="stat-card">
          <div className="flex items-center gap-2 mb-1">
            <LogIn className="w-4 h-4 text-green-400" />
            <p className="text-sm text-gray-400">Check-ins</p>
          </div>
          <p className="text-2xl font-bold text-green-400">{checkinCount}</p>
        </div>
        <div className="stat-card">
          <p className="text-sm text-gray-400">Active Borrowers</p>
          <p className="text-2xl font-bold">{stats?.activeBorrowers || 0}</p>
        </div>
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Activity Over Time */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold">Activity Over Time</h3>
          </div>
          <div className="card-body">
            {stats?.activityByDate?.length > 0 ? (
              <ResponsiveContainer width="100%" height={250}>
                <BarChart data={stats.activityByDate}>
                  <XAxis dataKey="date" tick={{ fill: '#9CA3AF', fontSize: 12 }} />
                  <YAxis tick={{ fill: '#9CA3AF', fontSize: 12 }} />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#1a1a1a',
                      border: '1px solid #333',
                      borderRadius: '8px'
                    }}
                  />
                  <Bar dataKey="checkouts" fill="#FFD700" name="Checkouts" />
                  <Bar dataKey="checkins" fill="#4CAF50" name="Check-ins" />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[250px] flex items-center justify-center text-gray-500">
                No activity data available
              </div>
            )}
          </div>
        </div>

        {/* Top Borrowed Items */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold">Most Used Equipment</h3>
          </div>
          <div className="card-body">
            {stats?.topAssets?.length > 0 ? (
              <div className="space-y-3">
                {stats.topAssets.slice(0, 5).map((item, idx) => (
                  <div key={item.asset_id} className="flex items-center gap-3">
                    <span className="w-6 h-6 bg-neofox-yellow/20 text-neofox-yellow rounded-full flex items-center justify-center text-sm font-medium">
                      {idx + 1}
                    </span>
                    <div className="flex-1">
                      <p className="font-medium">{item.asset_name}</p>
                      <p className="text-sm text-gray-400">{item.asset_id}</p>
                    </div>
                    <span className="text-sm font-medium">{item.count} uses</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="h-[200px] flex items-center justify-center text-gray-500">
                No usage data available
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Transactions Table */}
      <div className="card">
        <div className="card-header">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 className="font-semibold">Transaction History</h3>
            <div className="flex items-center gap-3">
              <select
                value={dateRange}
                onChange={(e) => setDateRange(e.target.value)}
                className="select-field w-32 py-2"
              >
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="365">Last year</option>
              </select>
              <select
                value={typeFilter}
                onChange={(e) => setTypeFilter(e.target.value)}
                className="select-field w-32 py-2"
              >
                <option value="">All Types</option>
                <option value="checkout">Checkouts</option>
                <option value="checkin">Check-ins</option>
              </select>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
                <input
                  type="text"
                  placeholder="Search..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="input-field pl-9 py-2 w-48"
                />
              </div>
            </div>
          </div>
        </div>
        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>Date & Time</th>
                <th>Equipment</th>
                <th>Type</th>
                <th>Borrower</th>
                <th>Purpose</th>
              </tr>
            </thead>
            <tbody>
              {filteredTransactions.length > 0 ? (
                filteredTransactions.map((tx, idx) => (
                  <tr key={idx}>
                    <td className="text-sm">
                      {format(parseISO(tx.transaction_date), 'MMM d, yyyy')}
                      <br />
                      <span className="text-gray-500">{format(parseISO(tx.transaction_date), 'h:mm a')}</span>
                    </td>
                    <td>
                      <p className="font-medium">{tx.asset_name || tx.asset_id}</p>
                      <p className="text-sm text-neofox-yellow font-mono">{tx.asset_id}</p>
                    </td>
                    <td>
                      <span className={`badge ${
                        tx.transaction_type === 'checkout'
                          ? 'bg-yellow-500/20 text-yellow-400'
                          : 'bg-green-500/20 text-green-400'
                      }`}>
                        {tx.transaction_type === 'checkout' ? 'Check Out' : 'Check In'}
                      </span>
                    </td>
                    <td>{tx.borrower_name}</td>
                    <td className="text-gray-400 max-w-xs truncate">{tx.purpose || '—'}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="text-center py-8 text-gray-500">
                    No transactions found
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}

import { useState, useEffect } from 'react'
import { assetApi, transactionApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  BarChart3,
  TrendingUp,
  Users,
  Package,
  Calendar,
  ArrowDown,
  Loader2,
  Filter
} from 'lucide-react'
import { format, parseISO, subDays } from 'date-fns'

export default function UsageAnalytics() {
  const [loading, setLoading] = useState(true)
  const [assets, setAssets] = useState([])
  const [transactions, setTransactions] = useState([])
  const [dateRange, setDateRange] = useState('30') // days
  const toast = useToast()

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [assetsRes, transactionsRes] = await Promise.all([
        assetApi.getAll(),
        transactionApi.getAll()
      ])
      setAssets(assetsRes.assets || [])
      setTransactions(transactionsRes.transactions || [])
    } catch (error) {
      toast.error('Failed to load analytics data')
    } finally {
      setLoading(false)
    }
  }

  // Filter transactions by date range
  const filteredTransactions = transactions.filter(tx => {
    const txDate = parseISO(tx.transaction_date)
    const cutoffDate = subDays(new Date(), parseInt(dateRange))
    return txDate >= cutoffDate
  })

  // Calculate statistics
  const stats = {
    totalCheckouts: filteredTransactions.filter(tx => tx.transaction_type === 'checkout').length,
    totalCheckins: filteredTransactions.filter(tx => tx.transaction_type === 'checkin').length,
    activeCheckouts: assets.filter(a => a.status === 'checked_out').length,
    availableEquipment: assets.filter(a => a.status === 'available').length
  }

  // Most used equipment
  const equipmentUsage = {}
  filteredTransactions.filter(tx => tx.transaction_type === 'checkout').forEach(tx => {
    if (!equipmentUsage[tx.asset_id]) {
      equipmentUsage[tx.asset_id] = { count: 0, name: tx.asset_name || tx.asset_id }
    }
    equipmentUsage[tx.asset_id].count++
  })

  const topEquipment = Object.entries(equipmentUsage)
    .map(([id, data]) => ({ id, ...data }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 10)

  // Most active borrowers
  const borrowerUsage = {}
  filteredTransactions.filter(tx => tx.transaction_type === 'checkout').forEach(tx => {
    const borrower = tx.borrower_name || 'Unknown'
    if (!borrowerUsage[borrower]) {
      borrowerUsage[borrower] = 0
    }
    borrowerUsage[borrower]++
  })

  const topBorrowers = Object.entries(borrowerUsage)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 10)

  // Category distribution
  const categoryUsage = {}
  filteredTransactions.filter(tx => tx.transaction_type === 'checkout').forEach(tx => {
    const asset = assets.find(a => a.asset_id === tx.asset_id)
    const category = asset?.category || 'Other'
    if (!categoryUsage[category]) {
      categoryUsage[category] = 0
    }
    categoryUsage[category]++
  })

  const categoryData = Object.entries(categoryUsage)
    .map(([category, count]) => ({ category, count }))
    .sort((a, b) => b.count - a.count)

  // Daily activity for chart
  const dailyActivity = {}
  for (let i = parseInt(dateRange) - 1; i >= 0; i--) {
    const date = format(subDays(new Date(), i), 'yyyy-MM-dd')
    dailyActivity[date] = { checkouts: 0, checkins: 0 }
  }

  filteredTransactions.forEach(tx => {
    const date = format(parseISO(tx.transaction_date), 'yyyy-MM-dd')
    if (dailyActivity[date]) {
      if (tx.transaction_type === 'checkout') {
        dailyActivity[date].checkouts++
      } else {
        dailyActivity[date].checkins++
      }
    }
  })

  const maxDailyActivity = Math.max(
    ...Object.values(dailyActivity).map(d => Math.max(d.checkouts, d.checkins)),
    1
  )

  // Utilization rate
  const utilizationRate = assets.length > 0
    ? Math.round((stats.activeCheckouts / assets.length) * 100)
    : 0

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
          <h1 className="text-2xl font-bold">Usage Analytics</h1>
          <p className="text-gray-400">Equipment usage statistics and trends</p>
        </div>
        <div className="flex items-center gap-2">
          <Filter className="w-4 h-4 text-gray-400" />
          <select
            value={dateRange}
            onChange={(e) => setDateRange(e.target.value)}
            className="select-field w-auto"
          >
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last 12 months</option>
          </select>
        </div>
      </div>

      {/* Key Metrics */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="card">
          <div className="card-body">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-400">Total Checkouts</p>
                <p className="text-3xl font-bold text-neofox-yellow mt-1">{stats.totalCheckouts}</p>
              </div>
              <div className="p-3 bg-yellow-500/20 rounded-lg">
                <TrendingUp className="w-6 h-6 text-yellow-400" />
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-400">Total Check-ins</p>
                <p className="text-3xl font-bold text-green-400 mt-1">{stats.totalCheckins}</p>
              </div>
              <div className="p-3 bg-green-500/20 rounded-lg">
                <ArrowDown className="w-6 h-6 text-green-400" />
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-400">Currently Out</p>
                <p className="text-3xl font-bold text-orange-400 mt-1">{stats.activeCheckouts}</p>
              </div>
              <div className="p-3 bg-orange-500/20 rounded-lg">
                <Package className="w-6 h-6 text-orange-400" />
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-400">Utilization Rate</p>
                <p className="text-3xl font-bold text-blue-400 mt-1">{utilizationRate}%</p>
              </div>
              <div className="p-3 bg-blue-500/20 rounded-lg">
                <BarChart3 className="w-6 h-6 text-blue-400" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Activity Chart */}
      <div className="card">
        <div className="card-header">
          <h3 className="font-semibold flex items-center gap-2">
            <Calendar className="w-5 h-5 text-neofox-yellow" />
            Daily Activity
          </h3>
        </div>
        <div className="card-body">
          <div className="flex items-end gap-1 h-48 overflow-x-auto pb-6">
            {Object.entries(dailyActivity).map(([date, data], idx) => (
              <div key={date} className="flex flex-col items-center min-w-[24px] flex-1">
                <div className="flex gap-0.5 items-end h-32 w-full justify-center">
                  <div
                    className="w-2 bg-yellow-500 rounded-t transition-all"
                    style={{ height: `${(data.checkouts / maxDailyActivity) * 100}%`, minHeight: data.checkouts > 0 ? '4px' : '0' }}
                    title={`Checkouts: ${data.checkouts}`}
                  />
                  <div
                    className="w-2 bg-green-500 rounded-t transition-all"
                    style={{ height: `${(data.checkins / maxDailyActivity) * 100}%`, minHeight: data.checkins > 0 ? '4px' : '0' }}
                    title={`Check-ins: ${data.checkins}`}
                  />
                </div>
                {(idx === 0 || idx === Object.keys(dailyActivity).length - 1 || (idx + 1) % 7 === 0) && (
                  <p className="text-[10px] text-gray-500 mt-2 rotate-45 origin-left whitespace-nowrap">
                    {format(parseISO(date), 'MMM d')}
                  </p>
                )}
              </div>
            ))}
          </div>
          <div className="flex items-center justify-center gap-6 mt-4 pt-4 border-t border-neofox-gray">
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 bg-yellow-500 rounded" />
              <span className="text-sm text-gray-400">Checkouts</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 bg-green-500 rounded" />
              <span className="text-sm text-gray-400">Check-ins</span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top Equipment */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Package className="w-5 h-5 text-neofox-yellow" />
              Most Used Equipment
            </h3>
          </div>
          <div className="card-body">
            {topEquipment.length > 0 ? (
              <div className="space-y-3">
                {topEquipment.map((item, idx) => (
                  <div key={item.id} className="flex items-center gap-3">
                    <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                      idx === 0 ? 'bg-yellow-500 text-black' :
                      idx === 1 ? 'bg-gray-400 text-black' :
                      idx === 2 ? 'bg-orange-600 text-white' :
                      'bg-neofox-gray text-gray-300'
                    }`}>
                      {idx + 1}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium truncate">{item.name}</p>
                      <p className="text-xs text-gray-500 font-mono">{item.id}</p>
                    </div>
                    <span className="text-neofox-yellow font-bold">{item.count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-center py-4">No data available</p>
            )}
          </div>
        </div>

        {/* Top Borrowers */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Users className="w-5 h-5 text-neofox-yellow" />
              Most Active Borrowers
            </h3>
          </div>
          <div className="card-body">
            {topBorrowers.length > 0 ? (
              <div className="space-y-3">
                {topBorrowers.map((item, idx) => (
                  <div key={item.name} className="flex items-center gap-3">
                    <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                      idx === 0 ? 'bg-yellow-500 text-black' :
                      idx === 1 ? 'bg-gray-400 text-black' :
                      idx === 2 ? 'bg-orange-600 text-white' :
                      'bg-neofox-gray text-gray-300'
                    }`}>
                      {idx + 1}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium truncate">{item.name}</p>
                    </div>
                    <span className="text-neofox-yellow font-bold">{item.count} checkouts</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-center py-4">No data available</p>
            )}
          </div>
        </div>
      </div>

      {/* Category Distribution */}
      <div className="card">
        <div className="card-header">
          <h3 className="font-semibold flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-neofox-yellow" />
            Usage by Category
          </h3>
        </div>
        <div className="card-body">
          {categoryData.length > 0 ? (
            <div className="space-y-4">
              {categoryData.map((item) => {
                const percentage = Math.round((item.count / stats.totalCheckouts) * 100)
                return (
                  <div key={item.category} className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">{item.category}</span>
                      <span className="text-gray-400">{item.count} ({percentage}%)</span>
                    </div>
                    <div className="h-2 bg-neofox-darker rounded-full overflow-hidden">
                      <div
                        className="h-full bg-gradient-to-r from-neofox-yellow to-yellow-600 rounded-full transition-all"
                        style={{ width: `${percentage}%` }}
                      />
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No data available</p>
          )}
        </div>
      </div>
    </div>
  )
}

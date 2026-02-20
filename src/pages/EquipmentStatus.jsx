import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  Package,
  Search,
  Loader2,
  LogIn,
  LogOut,
  User,
  Calendar,
  Clock,
  AlertTriangle,
  CheckCircle,
  Filter,
  ChevronDown,
  RefreshCw,
  Phone,
  MessageCircle
} from 'lucide-react'
import { format, parseISO, differenceInDays, isPast } from 'date-fns'

export default function EquipmentStatus() {
  const navigate = useNavigate()
  const toast = useToast()

  const [assets, setAssets] = useState([])
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [filter, setFilter] = useState('all') // all, out, available, overdue
  const [sortBy, setSortBy] = useState('status') // status, name, borrower, date
  const [lastRefresh, setLastRefresh] = useState(new Date())

  useEffect(() => {
    loadData()
    // Auto-refresh every 60 seconds
    const interval = setInterval(loadData, 60000)
    return () => clearInterval(interval)
  }, [])

  const loadData = async () => {
    try {
      const [assetsRes, usersRes] = await Promise.all([
        assetApi.getAll(),
        assetApi.getUsers()
      ])
      setAssets(assetsRes.assets || [])
      setUsers(usersRes.users || [])
      setLastRefresh(new Date())
    } catch (error) {
      toast.error('Failed to load data')
    } finally {
      setLoading(false)
    }
  }

  const getUserPhone = (borrowerName) => {
    const user = users.find(u =>
      u.username?.toLowerCase() === borrowerName?.toLowerCase() ||
      u.name?.toLowerCase() === borrowerName?.toLowerCase()
    )
    return user?.phone || null
  }

  const getWhatsAppLink = (borrowerName, assets) => {
    const phone = getUserPhone(borrowerName)
    if (!phone) return null

    const cleanPhone = phone.replace(/[^0-9]/g, '')
    const assetList = assets.map(a => `- ${a.asset_name} (${a.asset_id})`).join('\n')
    const message = encodeURIComponent(
      `Hi ${borrowerName},\n\nThis is a reminder about the following equipment currently checked out to you:\n\n${assetList}\n\nPlease ensure timely return. Thank you!`
    )
    return `https://wa.me/${cleanPhone}?text=${message}`
  }

  // Filter and sort assets
  let filteredAssets = assets.filter(asset => {
    if (searchQuery) {
      const query = searchQuery.toLowerCase()
      if (!asset.asset_name.toLowerCase().includes(query) &&
          !asset.asset_id.toLowerCase().includes(query) &&
          !asset.current_borrower?.toLowerCase().includes(query) &&
          !asset.category?.toLowerCase().includes(query)) {
        return false
      }
    }

    switch (filter) {
      case 'out':
        return asset.status === 'checked_out'
      case 'available':
        return asset.status === 'available'
      case 'overdue':
        return asset.status === 'checked_out' &&
               asset.expected_return_date &&
               isPast(parseISO(asset.expected_return_date))
      default:
        return true
    }
  })

  // Sort
  filteredAssets.sort((a, b) => {
    switch (sortBy) {
      case 'name':
        return a.asset_name.localeCompare(b.asset_name)
      case 'borrower':
        return (a.current_borrower || '').localeCompare(b.current_borrower || '')
      case 'date':
        if (!a.checkout_date) return 1
        if (!b.checkout_date) return -1
        return parseISO(b.checkout_date) - parseISO(a.checkout_date)
      default: // status
        const statusOrder = { checked_out: 0, maintenance: 1, available: 2 }
        return (statusOrder[a.status] || 3) - (statusOrder[b.status] || 3)
    }
  })

  // Group by borrower for "out" view
  const groupedByBorrower = filteredAssets
    .filter(a => a.status === 'checked_out')
    .reduce((acc, asset) => {
      const borrower = asset.current_borrower || 'Unknown'
      if (!acc[borrower]) acc[borrower] = []
      acc[borrower].push(asset)
      return acc
    }, {})

  // Stats
  const stats = {
    total: assets.length,
    available: assets.filter(a => a.status === 'available').length,
    checkedOut: assets.filter(a => a.status === 'checked_out').length,
    overdue: assets.filter(a =>
      a.status === 'checked_out' &&
      a.expected_return_date &&
      isPast(parseISO(a.expected_return_date))
    ).length,
    maintenance: assets.filter(a => a.status === 'maintenance').length
  }

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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Equipment Status</h1>
          <p className="text-gray-400">
            Real-time view of all equipment • Last updated {format(lastRefresh, 'h:mm:ss a')}
          </p>
        </div>
        <button
          onClick={loadData}
          className="btn-secondary flex items-center gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          Refresh
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <button
          onClick={() => setFilter('all')}
          className={`card p-4 text-left transition-all ${filter === 'all' ? 'ring-2 ring-neofox-yellow' : ''}`}
        >
          <p className="text-3xl font-bold">{stats.total}</p>
          <p className="text-sm text-gray-400">Total Equipment</p>
        </button>
        <button
          onClick={() => setFilter('available')}
          className={`card p-4 text-left transition-all ${filter === 'available' ? 'ring-2 ring-green-500' : ''}`}
        >
          <p className="text-3xl font-bold text-green-400">{stats.available}</p>
          <p className="text-sm text-gray-400">Available</p>
        </button>
        <button
          onClick={() => setFilter('out')}
          className={`card p-4 text-left transition-all ${filter === 'out' ? 'ring-2 ring-orange-500' : ''}`}
        >
          <p className="text-3xl font-bold text-orange-400">{stats.checkedOut}</p>
          <p className="text-sm text-gray-400">Checked Out</p>
        </button>
        <button
          onClick={() => setFilter('overdue')}
          className={`card p-4 text-left transition-all ${filter === 'overdue' ? 'ring-2 ring-red-500' : ''}`}
        >
          <p className="text-3xl font-bold text-red-400">{stats.overdue}</p>
          <p className="text-sm text-gray-400">Overdue</p>
        </button>
        <div className="card p-4">
          <p className="text-3xl font-bold text-yellow-400">{stats.maintenance}</p>
          <p className="text-sm text-gray-400">Maintenance</p>
        </div>
      </div>

      {/* Search & Sort */}
      <div className="card">
        <div className="card-body">
          <div className="flex flex-col md:flex-row items-start md:items-center gap-4">
            <div className="flex-1 relative w-full">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
              <input
                type="text"
                placeholder="Search by name, ID, borrower, or category..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="input-field pl-10"
              />
            </div>
            <div className="flex items-center gap-2">
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="input-field"
              >
                <option value="status">Sort by Status</option>
                <option value="name">Sort by Name</option>
                <option value="borrower">Sort by Borrower</option>
                <option value="date">Sort by Checkout Date</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Grouped View (when showing checked out) */}
      {(filter === 'out' || filter === 'overdue') && Object.keys(groupedByBorrower).length > 0 && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold">By Team Member</h2>
          {Object.entries(groupedByBorrower)
            .sort((a, b) => b[1].length - a[1].length)
            .map(([borrower, borrowerAssets]) => {
              const hasOverdue = borrowerAssets.some(a =>
                a.expected_return_date && isPast(parseISO(a.expected_return_date))
              )
              const whatsAppLink = getWhatsAppLink(borrower, borrowerAssets)

              return (
                <div key={borrower} className="card">
                  <div className="card-header flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                        hasOverdue ? 'bg-red-500/20' : 'bg-neofox-yellow/20'
                      }`}>
                        <User className={`w-5 h-5 ${hasOverdue ? 'text-red-400' : 'text-neofox-yellow'}`} />
                      </div>
                      <div>
                        <h3 className="font-semibold">{borrower}</h3>
                        <p className="text-sm text-gray-400">{borrowerAssets.length} items checked out</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {whatsAppLink && (
                        <a
                          href={whatsAppLink}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="btn-secondary flex items-center gap-2 text-green-400 border-green-500/30 hover:bg-green-500/10"
                        >
                          <MessageCircle className="w-4 h-4" />
                          WhatsApp
                        </a>
                      )}
                      {hasOverdue && (
                        <span className="badge bg-red-500/20 text-red-400">
                          <AlertTriangle className="w-3 h-3 mr-1" />
                          Has Overdue
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="divide-y divide-neofox-gray">
                    {borrowerAssets.map(asset => {
                      const isOverdue = asset.expected_return_date && isPast(parseISO(asset.expected_return_date))
                      const daysOut = asset.checkout_date ? differenceInDays(new Date(), parseISO(asset.checkout_date)) : 0

                      return (
                        <div
                          key={asset.asset_id}
                          className="p-4 hover:bg-neofox-darker cursor-pointer transition-colors"
                          onClick={() => navigate(`/assets/${asset.asset_id}`)}
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <Package className="w-5 h-5 text-gray-500" />
                              <div>
                                <p className="font-medium">{asset.asset_name}</p>
                                <p className="text-sm text-gray-500">{asset.asset_id} • {asset.category}</p>
                              </div>
                            </div>
                            <div className="text-right">
                              <div className="flex items-center gap-2">
                                {isOverdue ? (
                                  <span className="badge bg-red-500/20 text-red-400">
                                    Overdue
                                  </span>
                                ) : (
                                  <span className="text-sm text-gray-400">
                                    {daysOut} days out
                                  </span>
                                )}
                              </div>
                              {asset.expected_return_date && (
                                <p className={`text-xs mt-1 ${isOverdue ? 'text-red-400' : 'text-gray-500'}`}>
                                  Due: {format(parseISO(asset.expected_return_date), 'MMM d, yyyy')}
                                </p>
                              )}
                            </div>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              )
            })}
        </div>
      )}

      {/* List View */}
      {filter !== 'out' && filter !== 'overdue' && (
        <div className="card">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-neofox-gray">
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Equipment</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Category</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Status</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Current Holder</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Checkout Date</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Due Date</th>
                  <th className="text-left p-4 text-sm font-medium text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredAssets.map(asset => {
                  const isOverdue = asset.status === 'checked_out' &&
                    asset.expected_return_date &&
                    isPast(parseISO(asset.expected_return_date))

                  return (
                    <tr
                      key={asset.asset_id}
                      className="border-b border-neofox-gray hover:bg-neofox-darker cursor-pointer"
                      onClick={() => navigate(`/assets/${asset.asset_id}`)}
                    >
                      <td className="p-4">
                        <div>
                          <p className="font-medium">{asset.asset_name}</p>
                          <p className="text-xs text-gray-500 font-mono">{asset.asset_id}</p>
                        </div>
                      </td>
                      <td className="p-4 text-gray-400">{asset.category}</td>
                      <td className="p-4">
                        <span className={`badge ${
                          asset.status === 'available' ? 'badge-available' :
                          asset.status === 'checked_out' ? (isOverdue ? 'bg-red-500/20 text-red-400' : 'badge-checked-out') :
                          'badge-maintenance'
                        }`}>
                          {isOverdue ? 'Overdue' : asset.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="p-4">
                        {asset.current_borrower ? (
                          <div className="flex items-center gap-2">
                            <User className="w-4 h-4 text-gray-500" />
                            <span>{asset.current_borrower}</span>
                          </div>
                        ) : (
                          <span className="text-gray-500">-</span>
                        )}
                      </td>
                      <td className="p-4 text-gray-400">
                        {asset.checkout_date ? format(parseISO(asset.checkout_date), 'MMM d, yyyy') : '-'}
                      </td>
                      <td className="p-4">
                        {asset.expected_return_date ? (
                          <span className={isOverdue ? 'text-red-400 font-medium' : 'text-gray-400'}>
                            {format(parseISO(asset.expected_return_date), 'MMM d, yyyy')}
                          </span>
                        ) : '-'}
                      </td>
                      <td className="p-4">
                        <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                          {asset.status === 'available' ? (
                            <button
                              onClick={() => navigate(`/checkout?asset=${asset.asset_id}`)}
                              className="text-xs text-neofox-yellow hover:underline flex items-center gap-1"
                            >
                              <LogOut className="w-3 h-3" />
                              Checkout
                            </button>
                          ) : asset.status === 'checked_out' ? (
                            <button
                              onClick={() => navigate(`/checkin?asset=${asset.asset_id}`)}
                              className="text-xs text-green-400 hover:underline flex items-center gap-1"
                            >
                              <LogIn className="w-3 h-3" />
                              Check In
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {filteredAssets.length === 0 && (
            <div className="p-8 text-center text-gray-500">
              <Package className="w-12 h-12 mx-auto mb-4 opacity-50" />
              <p>No equipment found</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

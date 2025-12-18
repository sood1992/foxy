import { useState, useEffect } from 'react'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  History,
  Search,
  Filter,
  Loader2,
  User,
  Package,
  LogIn,
  LogOut,
  Edit,
  Trash2,
  Plus,
  Camera,
  Calendar,
  Download,
  ChevronDown,
  RefreshCw
} from 'lucide-react'
import { format, parseISO } from 'date-fns'

const ACTION_ICONS = {
  checkout: LogOut,
  checkin: LogIn,
  create: Plus,
  update: Edit,
  delete: Trash2,
  photo_upload: Camera,
  reservation: Calendar,
  default: History
}

const ACTION_COLORS = {
  checkout: 'text-orange-400 bg-orange-500/20',
  checkin: 'text-green-400 bg-green-500/20',
  create: 'text-blue-400 bg-blue-500/20',
  update: 'text-yellow-400 bg-yellow-500/20',
  delete: 'text-red-400 bg-red-500/20',
  photo_upload: 'text-purple-400 bg-purple-500/20',
  reservation: 'text-cyan-400 bg-cyan-500/20',
  default: 'text-gray-400 bg-gray-500/20'
}

export default function AuditLog() {
  const toast = useToast()

  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [filters, setFilters] = useState({
    action: '',
    user: '',
    dateFrom: '',
    dateTo: ''
  })
  const [showFilters, setShowFilters] = useState(false)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(true)

  useEffect(() => {
    loadLogs()
  }, [filters])

  const loadLogs = async (append = false) => {
    try {
      if (!append) setLoading(true)
      const response = await assetApi.getAuditLog({
        ...filters,
        page: append ? page : 1,
        limit: 50
      })
      const newLogs = response.logs || []

      if (append) {
        setLogs(prev => [...prev, ...newLogs])
      } else {
        setLogs(newLogs)
        setPage(1)
      }
      setHasMore(newLogs.length === 50)
    } catch (error) {
      toast.error('Failed to load audit log')
    } finally {
      setLoading(false)
    }
  }

  const loadMore = () => {
    setPage(prev => prev + 1)
    loadLogs(true)
  }

  const filteredLogs = logs.filter(log =>
    !searchQuery ||
    log.description?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    log.user_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    log.asset_id?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    log.asset_name?.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const uniqueUsers = [...new Set(logs.map(l => l.user_name).filter(Boolean))]
  const uniqueActions = [...new Set(logs.map(l => l.action).filter(Boolean))]

  const exportLog = () => {
    const csv = [
      ['Timestamp', 'Action', 'User', 'Asset ID', 'Asset Name', 'Description', 'IP Address'].join(','),
      ...filteredLogs.map(log => [
        log.timestamp,
        log.action,
        log.user_name || '',
        log.asset_id || '',
        log.asset_name || '',
        `"${(log.description || '').replace(/"/g, '""')}"`,
        log.ip_address || ''
      ].join(','))
    ].join('\n')

    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `audit-log-${format(new Date(), 'yyyy-MM-dd')}.csv`
    a.click()
  }

  if (loading && logs.length === 0) {
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
          <h1 className="text-2xl font-bold">Audit Log</h1>
          <p className="text-gray-400">Complete history of all system actions</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => loadLogs()}
            className="btn-secondary flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Refresh
          </button>
          <button
            onClick={exportLog}
            className="btn-primary flex items-center gap-2"
          >
            <Download className="w-4 h-4" />
            Export CSV
          </button>
        </div>
      </div>

      {/* Search & Filters */}
      <div className="card">
        <div className="card-body space-y-4">
          <div className="flex items-center gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
              <input
                type="text"
                placeholder="Search by user, asset, or description..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="input-field pl-10"
              />
            </div>
            <button
              onClick={() => setShowFilters(!showFilters)}
              className={`btn-secondary flex items-center gap-2 ${showFilters ? 'bg-neofox-gray' : ''}`}
            >
              <Filter className="w-4 h-4" />
              Filters
              <ChevronDown className={`w-4 h-4 transition-transform ${showFilters ? 'rotate-180' : ''}`} />
            </button>
          </div>

          {showFilters && (
            <div className="grid md:grid-cols-4 gap-4 pt-4 border-t border-neofox-gray">
              <div>
                <label className="block text-sm text-gray-400 mb-1">Action Type</label>
                <select
                  value={filters.action}
                  onChange={(e) => setFilters({ ...filters, action: e.target.value })}
                  className="input-field"
                >
                  <option value="">All Actions</option>
                  {uniqueActions.map(action => (
                    <option key={action} value={action}>{action}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm text-gray-400 mb-1">User</label>
                <select
                  value={filters.user}
                  onChange={(e) => setFilters({ ...filters, user: e.target.value })}
                  className="input-field"
                >
                  <option value="">All Users</option>
                  {uniqueUsers.map(user => (
                    <option key={user} value={user}>{user}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm text-gray-400 mb-1">From Date</label>
                <input
                  type="date"
                  value={filters.dateFrom}
                  onChange={(e) => setFilters({ ...filters, dateFrom: e.target.value })}
                  className="input-field"
                />
              </div>
              <div>
                <label className="block text-sm text-gray-400 mb-1">To Date</label>
                <input
                  type="date"
                  value={filters.dateTo}
                  onChange={(e) => setFilters({ ...filters, dateTo: e.target.value })}
                  className="input-field"
                />
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Log Entries */}
      <div className="card">
        <div className="card-header flex items-center justify-between">
          <h3 className="font-semibold">Activity Log</h3>
          <span className="text-sm text-gray-400">{filteredLogs.length} entries</span>
        </div>
        <div className="divide-y divide-neofox-gray">
          {filteredLogs.map((log, idx) => {
            const Icon = ACTION_ICONS[log.action] || ACTION_ICONS.default
            const colorClass = ACTION_COLORS[log.action] || ACTION_COLORS.default

            return (
              <div key={log.id || idx} className="p-4 hover:bg-neofox-darker transition-colors">
                <div className="flex items-start gap-4">
                  <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${colorClass}`}>
                    <Icon className="w-5 h-5" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <p className="font-medium">{log.description}</p>
                        <div className="flex items-center gap-4 mt-1 text-sm text-gray-400">
                          {log.user_name && (
                            <span className="flex items-center gap-1">
                              <User className="w-3 h-3" />
                              {log.user_name}
                            </span>
                          )}
                          {log.asset_id && (
                            <span className="flex items-center gap-1">
                              <Package className="w-3 h-3" />
                              {log.asset_name || log.asset_id}
                            </span>
                          )}
                        </div>
                      </div>
                      <div className="text-right text-sm text-gray-500 whitespace-nowrap">
                        <p>{format(parseISO(log.timestamp), 'MMM d, yyyy')}</p>
                        <p>{format(parseISO(log.timestamp), 'h:mm a')}</p>
                      </div>
                    </div>
                    {log.details && (
                      <div className="mt-2 p-2 bg-neofox-darker rounded text-sm text-gray-400">
                        <pre className="whitespace-pre-wrap font-mono text-xs">
                          {typeof log.details === 'string' ? log.details : JSON.stringify(log.details, null, 2)}
                        </pre>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )
          })}
        </div>

        {filteredLogs.length === 0 && (
          <div className="p-8 text-center text-gray-500">
            <History className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No log entries found</p>
          </div>
        )}

        {hasMore && (
          <div className="p-4 text-center border-t border-neofox-gray">
            <button
              onClick={loadMore}
              className="text-neofox-yellow hover:underline"
            >
              Load more entries
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

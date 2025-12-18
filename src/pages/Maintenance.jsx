import { useState, useEffect } from 'react'
import { maintenanceApi, assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  Wrench,
  AlertTriangle,
  Calendar,
  CheckCircle,
  Clock,
  Plus,
  Search,
  Package,
  X,
  Loader2,
  Filter
} from 'lucide-react'
import { format, parseISO, isPast, addDays } from 'date-fns'

const severityConfig = {
  low: { label: 'Low', class: 'bg-blue-500/20 text-blue-400' },
  medium: { label: 'Medium', class: 'bg-yellow-500/20 text-yellow-400' },
  high: { label: 'High', class: 'bg-orange-500/20 text-orange-400' },
  critical: { label: 'Critical', class: 'bg-red-500/20 text-red-400' }
}

const statusConfig = {
  open: { label: 'Open', class: 'bg-red-500/20 text-red-400' },
  in_progress: { label: 'In Progress', class: 'bg-yellow-500/20 text-yellow-400' },
  resolved: { label: 'Resolved', class: 'bg-green-500/20 text-green-400' },
  closed: { label: 'Closed', class: 'bg-gray-500/20 text-gray-400' }
}

export default function Maintenance() {
  const [issues, setIssues] = useState([])
  const [schedule, setSchedule] = useState([])
  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState('issues')
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [selectedAsset, setSelectedAsset] = useState(null)
  const [searchQuery, setSearchQuery] = useState('')
  const [showAssetDropdown, setShowAssetDropdown] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const toast = useToast()

  const [form, setForm] = useState({
    issue_type: 'mechanical',
    severity: 'medium',
    description: '',
    reported_by: ''
  })

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [issuesRes, scheduleRes, assetsRes] = await Promise.all([
        maintenanceApi.getIssues(),
        maintenanceApi.getSchedule(),
        assetApi.getAll()
      ])
      setIssues(issuesRes.issues || [])
      setSchedule(scheduleRes.schedule || [])
      setAssets(assetsRes.assets || [])
    } catch (error) {
      toast.error('Failed to load maintenance data')
    } finally {
      setLoading(false)
    }
  }

  const filteredAssets = assets.filter(a =>
    !searchQuery ||
    a.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    a.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const handleCreateIssue = async () => {
    if (!selectedAsset) {
      toast.error('Please select equipment')
      return
    }
    if (!form.description.trim()) {
      toast.error('Please describe the issue')
      return
    }

    setSubmitting(true)
    try {
      await maintenanceApi.createIssue({
        asset_id: selectedAsset.id,
        ...form,
        reported_date: new Date().toISOString()
      })
      toast.success('Issue reported successfully')
      setShowCreateModal(false)
      setSelectedAsset(null)
      setForm({ issue_type: 'mechanical', severity: 'medium', description: '', reported_by: '' })
      loadData()
    } catch (error) {
      toast.error('Failed to report issue')
    } finally {
      setSubmitting(false)
    }
  }

  const openIssuesCount = issues.filter(i => i.status === 'open' || i.status === 'in_progress').length

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
          <h1 className="text-2xl font-bold">Maintenance</h1>
          <p className="text-gray-400">
            {openIssuesCount > 0 ? `${openIssuesCount} open issue${openIssuesCount !== 1 ? 's' : ''}` : 'No open issues'}
          </p>
        </div>
        <button onClick={() => setShowCreateModal(true)} className="btn-primary flex items-center gap-2">
          <Plus className="w-5 h-5" />
          Report Issue
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div className="stat-card">
          <p className="text-sm text-gray-400">Open Issues</p>
          <p className="text-2xl font-bold text-red-400">{issues.filter(i => i.status === 'open').length}</p>
        </div>
        <div className="stat-card">
          <p className="text-sm text-gray-400">In Progress</p>
          <p className="text-2xl font-bold text-yellow-400">{issues.filter(i => i.status === 'in_progress').length}</p>
        </div>
        <div className="stat-card">
          <p className="text-sm text-gray-400">Resolved</p>
          <p className="text-2xl font-bold text-green-400">{issues.filter(i => i.status === 'resolved').length}</p>
        </div>
        <div className="stat-card">
          <p className="text-sm text-gray-400">Scheduled Tasks</p>
          <p className="text-2xl font-bold">{schedule.length}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 bg-neofox-dark rounded-lg p-1">
        <button
          onClick={() => setActiveTab('issues')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            activeTab === 'issues'
              ? 'bg-neofox-yellow text-black'
              : 'text-gray-400 hover:text-white hover:bg-neofox-gray'
          }`}
        >
          Issues
        </button>
        <button
          onClick={() => setActiveTab('schedule')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
            activeTab === 'schedule'
              ? 'bg-neofox-yellow text-black'
              : 'text-gray-400 hover:text-white hover:bg-neofox-gray'
          }`}
        >
          Schedule
        </button>
      </div>

      {/* Content */}
      {activeTab === 'issues' ? (
        <div className="card">
          <div className="table-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Equipment</th>
                  <th>Issue Type</th>
                  <th>Severity</th>
                  <th>Status</th>
                  <th>Reported</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                {issues.length > 0 ? issues.map(issue => (
                  <tr key={issue.id}>
                    <td>
                      <p className="font-medium">{issue.asset_name || `Asset #${issue.asset_id}`}</p>
                    </td>
                    <td className="capitalize">{issue.issue_type}</td>
                    <td>
                      <span className={`badge ${severityConfig[issue.severity]?.class || ''}`}>
                        {severityConfig[issue.severity]?.label || issue.severity}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${statusConfig[issue.status]?.class || ''}`}>
                        {statusConfig[issue.status]?.label || issue.status}
                      </span>
                    </td>
                    <td className="text-sm text-gray-400">
                      {issue.reported_date ? format(parseISO(issue.reported_date), 'MMM d, yyyy') : '—'}
                    </td>
                    <td className="max-w-xs truncate text-gray-400">{issue.description}</td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={6} className="text-center py-8 text-gray-500">
                      <Wrench className="w-12 h-12 mx-auto mb-2 opacity-50" />
                      No maintenance issues reported
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="table-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Equipment</th>
                  <th>Type</th>
                  <th>Scheduled Date</th>
                  <th>Status</th>
                  <th>Assigned To</th>
                </tr>
              </thead>
              <tbody>
                {schedule.length > 0 ? schedule.map(task => (
                  <tr key={task.id}>
                    <td className="font-medium">{task.asset_name || `Asset #${task.asset_id}`}</td>
                    <td className="capitalize">{task.maintenance_type}</td>
                    <td>
                      {task.scheduled_date && format(parseISO(task.scheduled_date), 'MMM d, yyyy')}
                    </td>
                    <td>
                      <span className={`badge ${
                        task.status === 'completed' ? 'bg-green-500/20 text-green-400' :
                        task.status === 'in_progress' ? 'bg-yellow-500/20 text-yellow-400' :
                        'bg-blue-500/20 text-blue-400'
                      }`}>
                        {task.status}
                      </span>
                    </td>
                    <td className="text-gray-400">{task.assigned_to || '—'}</td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={5} className="text-center py-8 text-gray-500">
                      <Calendar className="w-12 h-12 mx-auto mb-2 opacity-50" />
                      No scheduled maintenance
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Create Issue Modal */}
      {showCreateModal && (
        <div className="modal-overlay" onClick={() => setShowCreateModal(false)}>
          <div className="modal-content" onClick={e => e.stopPropagation()}>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">Report Maintenance Issue</h3>

              {/* Equipment Selection */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-300 mb-2">Equipment</label>
                {selectedAsset ? (
                  <div className="flex items-center gap-3 p-3 bg-neofox-darker rounded-lg">
                    <Package className="w-5 h-5 text-neofox-yellow" />
                    <div className="flex-1">
                      <p className="font-medium">{selectedAsset.asset_name}</p>
                      <p className="text-sm text-gray-400">{selectedAsset.asset_id}</p>
                    </div>
                    <button onClick={() => setSelectedAsset(null)} className="text-gray-400 hover:text-white">
                      <X className="w-4 h-4" />
                    </button>
                  </div>
                ) : (
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
                    <input
                      type="text"
                      placeholder="Search equipment..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      onFocus={() => setShowAssetDropdown(true)}
                      className="input-field pl-10"
                    />
                    {showAssetDropdown && (
                      <>
                        <div className="fixed inset-0 z-10" onClick={() => setShowAssetDropdown(false)} />
                        <div className="absolute z-20 w-full mt-2 bg-neofox-dark border border-neofox-gray rounded-lg shadow-xl max-h-48 overflow-y-auto">
                          {filteredAssets.slice(0, 10).map(asset => (
                            <button
                              key={asset.asset_id}
                              onClick={() => {
                                setSelectedAsset(asset)
                                setShowAssetDropdown(false)
                                setSearchQuery('')
                              }}
                              className="flex items-center gap-3 w-full p-3 hover:bg-neofox-gray"
                            >
                              <Package className="w-5 h-5 text-gray-500" />
                              <div className="text-left">
                                <p className="font-medium">{asset.asset_name}</p>
                                <p className="text-sm text-gray-400">{asset.asset_id}</p>
                              </div>
                            </button>
                          ))}
                        </div>
                      </>
                    )}
                  </div>
                )}
              </div>

              <div className="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Issue Type</label>
                  <select
                    value={form.issue_type}
                    onChange={(e) => setForm({ ...form, issue_type: e.target.value })}
                    className="select-field"
                  >
                    <option value="mechanical">Mechanical</option>
                    <option value="electrical">Electrical</option>
                    <option value="software">Software</option>
                    <option value="cosmetic">Cosmetic</option>
                    <option value="performance">Performance</option>
                    <option value="safety">Safety</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Severity</label>
                  <select
                    value={form.severity}
                    onChange={(e) => setForm({ ...form, severity: e.target.value })}
                    className="select-field"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-300 mb-2">Description</label>
                <textarea
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  rows={3}
                  className="input-field"
                  placeholder="Describe the issue..."
                />
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-300 mb-2">Reported By</label>
                <input
                  type="text"
                  value={form.reported_by}
                  onChange={(e) => setForm({ ...form, reported_by: e.target.value })}
                  className="input-field"
                  placeholder="Your name"
                />
              </div>

              <div className="flex gap-3">
                <button onClick={() => setShowCreateModal(false)} className="btn-secondary flex-1">
                  Cancel
                </button>
                <button onClick={handleCreateIssue} disabled={submitting} className="btn-primary flex-1">
                  {submitting ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : 'Submit Issue'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

import { useState, useEffect } from 'react'
import { requestApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import { useAuth } from '../context/AuthContext'
import {
  ClipboardList,
  Clock,
  CheckCircle,
  X,
  Eye,
  Loader2,
  ChevronDown,
  User,
  Calendar,
  Package,
  Mail,
  ExternalLink
} from 'lucide-react'
import { format, parseISO } from 'date-fns'

const statusConfig = {
  pending: { label: 'Pending', class: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30', icon: Clock },
  approved: { label: 'Approved', class: 'bg-green-500/20 text-green-400 border-green-500/30', icon: CheckCircle },
  rejected: { label: 'Rejected', class: 'bg-red-500/20 text-red-400 border-red-500/30', icon: X }
}

function RequestCard({ request, onApprove, onReject, onView }) {
  const [expanded, setExpanded] = useState(false)
  const statusInfo = statusConfig[request.status]
  const StatusIcon = statusInfo.icon

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h3 className="font-semibold">{request.requester_name}</h3>
              <span className={`badge ${statusInfo.class} flex items-center gap-1`}>
                <StatusIcon className="w-3 h-3" />
                {statusInfo.label}
              </span>
            </div>
            <div className="flex items-center gap-4 text-sm text-gray-400">
              {request.requester_email && (
                <span className="flex items-center gap-1">
                  <Mail className="w-4 h-4" />
                  {request.requester_email}
                </span>
              )}
              <span className="flex items-center gap-1">
                <Calendar className="w-4 h-4" />
                {request.request_dates}
              </span>
            </div>
            {request.purpose && (
              <p className="text-sm text-gray-400 mt-2">Purpose: {request.purpose}</p>
            )}
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setExpanded(!expanded)}
              className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
            >
              <ChevronDown className={`w-5 h-5 transition-transform ${expanded ? 'rotate-180' : ''}`} />
            </button>
          </div>
        </div>

        {expanded && (
          <div className="mt-4 pt-4 border-t border-neofox-gray">
            <p className="text-sm font-medium text-gray-300 mb-2">Requested Items:</p>
            <div className="bg-neofox-darker rounded-lg p-3 text-sm">
              {request.required_items}
            </div>

            {request.admin_notes && (
              <div className="mt-3">
                <p className="text-sm font-medium text-gray-300 mb-1">Admin Notes:</p>
                <p className="text-sm text-gray-400">{request.admin_notes}</p>
              </div>
            )}

            {request.status === 'pending' && (
              <div className="flex items-center gap-3 mt-4">
                <button
                  onClick={() => onApprove(request)}
                  className="btn-primary flex items-center gap-2 text-sm py-2"
                >
                  <CheckCircle className="w-4 h-4" />
                  Approve & Checkout
                </button>
                <button
                  onClick={() => onReject(request)}
                  className="btn-danger flex items-center gap-2 text-sm py-2"
                >
                  <X className="w-4 h-4" />
                  Reject
                </button>
              </div>
            )}
          </div>
        )}

        <div className="mt-3 text-xs text-gray-500">
          Submitted {format(parseISO(request.created_at), 'MMM d, yyyy h:mm a')}
        </div>
      </div>
    </div>
  )
}

export default function Requests() {
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('all')
  const [actionModal, setActionModal] = useState(null)
  const [adminNotes, setAdminNotes] = useState('')
  const [processing, setProcessing] = useState(false)
  const toast = useToast()
  const { isAdmin } = useAuth()

  useEffect(() => {
    loadRequests()
  }, [])

  const loadRequests = async () => {
    try {
      const response = await requestApi.getAll()
      setRequests(response.requests || [])
    } catch (error) {
      toast.error('Failed to load requests')
    } finally {
      setLoading(false)
    }
  }

  const filteredRequests = requests.filter(r => {
    if (filter === 'all') return true
    return r.status === filter
  })

  const handleApprove = async () => {
    if (!actionModal) return
    setProcessing(true)
    try {
      await requestApi.approve(actionModal.id, { admin_notes: adminNotes })
      toast.success('Request approved and items checked out')
      loadRequests()
      setActionModal(null)
      setAdminNotes('')
    } catch (error) {
      toast.error(error.message || 'Failed to approve request')
    } finally {
      setProcessing(false)
    }
  }

  const handleReject = async () => {
    if (!actionModal) return
    setProcessing(true)
    try {
      await requestApi.reject(actionModal.id, { admin_notes: adminNotes })
      toast.success('Request rejected')
      loadRequests()
      setActionModal(null)
      setAdminNotes('')
    } catch (error) {
      toast.error('Failed to reject request')
    } finally {
      setProcessing(false)
    }
  }

  const pendingCount = requests.filter(r => r.status === 'pending').length

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
          <h1 className="text-2xl font-bold">Gear Requests</h1>
          <p className="text-gray-400">
            {pendingCount > 0 ? `${pendingCount} pending request${pendingCount !== 1 ? 's' : ''}` : 'No pending requests'}
          </p>
        </div>
        <a
          href="/request"
          target="_blank"
          className="btn-secondary flex items-center gap-2"
        >
          <ExternalLink className="w-4 h-4" />
          Request Form Link
        </a>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 bg-neofox-dark rounded-lg p-1">
        {[
          { value: 'all', label: 'All' },
          { value: 'pending', label: 'Pending' },
          { value: 'approved', label: 'Approved' },
          { value: 'rejected', label: 'Rejected' }
        ].map(tab => (
          <button
            key={tab.value}
            onClick={() => setFilter(tab.value)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              filter === tab.value
                ? 'bg-neofox-yellow text-black'
                : 'text-gray-400 hover:text-white hover:bg-neofox-gray'
            }`}
          >
            {tab.label}
            {tab.value === 'pending' && pendingCount > 0 && (
              <span className="ml-2 px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">
                {pendingCount}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Requests List */}
      {filteredRequests.length > 0 ? (
        <div className="space-y-4">
          {filteredRequests.map(request => (
            <RequestCard
              key={request.id}
              request={request}
              onApprove={(r) => setActionModal({ ...r, action: 'approve' })}
              onReject={(r) => setActionModal({ ...r, action: 'reject' })}
              onView={(r) => {}}
            />
          ))}
        </div>
      ) : (
        <div className="card p-12 text-center">
          <ClipboardList className="w-16 h-16 mx-auto text-gray-600 mb-4" />
          <h3 className="text-lg font-medium mb-2">No requests found</h3>
          <p className="text-gray-500">
            {filter !== 'all' ? `No ${filter} requests` : 'No gear requests have been submitted yet'}
          </p>
        </div>
      )}

      {/* Action Modal */}
      {actionModal && (
        <div className="modal-overlay" onClick={() => setActionModal(null)}>
          <div className="modal-content max-w-md" onClick={e => e.stopPropagation()}>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">
                {actionModal.action === 'approve' ? 'Approve Request' : 'Reject Request'}
              </h3>
              <p className="text-gray-400 mb-4">
                {actionModal.action === 'approve'
                  ? `This will checkout all requested items to ${actionModal.requester_name}`
                  : `Are you sure you want to reject ${actionModal.requester_name}'s request?`}
              </p>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Admin Notes (optional)
                </label>
                <textarea
                  value={adminNotes}
                  onChange={(e) => setAdminNotes(e.target.value)}
                  rows={3}
                  className="input-field"
                  placeholder="Add any notes..."
                />
              </div>
              <div className="flex gap-3">
                <button
                  onClick={() => setActionModal(null)}
                  className="btn-secondary flex-1"
                >
                  Cancel
                </button>
                <button
                  onClick={actionModal.action === 'approve' ? handleApprove : handleReject}
                  disabled={processing}
                  className={`flex-1 flex items-center justify-center gap-2 ${
                    actionModal.action === 'approve' ? 'btn-primary' : 'btn-danger'
                  }`}
                >
                  {processing ? (
                    <Loader2 className="w-5 h-5 animate-spin" />
                  ) : actionModal.action === 'approve' ? (
                    <>
                      <CheckCircle className="w-5 h-5" />
                      Approve
                    </>
                  ) : (
                    <>
                      <X className="w-5 h-5" />
                      Reject
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

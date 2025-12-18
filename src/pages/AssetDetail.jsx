import { useState, useEffect } from 'react'
import { useParams, useNavigate, useSearchParams, Link } from 'react-router-dom'
import { assetApi, transactionApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ArrowLeft,
  Edit,
  Trash2,
  QrCode,
  Package,
  Camera,
  Mic,
  Lightbulb,
  HardDrive,
  Calendar,
  User,
  Clock,
  CheckCircle,
  AlertTriangle,
  Wrench,
  FileText,
  Download,
  ExternalLink,
  Save,
  X,
  Plus,
  Upload
} from 'lucide-react'
import { format, parseISO, formatDistanceToNow } from 'date-fns'

const categoryIcons = {
  Camera: Camera,
  Audio: Mic,
  Lighting: Lightbulb,
  Lens: Camera,
  Storage: HardDrive,
  default: Package
}

const statusConfig = {
  available: { label: 'Available', class: 'bg-green-500/20 text-green-400 border-green-500/30' },
  checked_out: { label: 'Checked Out', class: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30' },
  maintenance: { label: 'Maintenance', class: 'bg-orange-500/20 text-orange-400 border-orange-500/30' },
  lost: { label: 'Lost', class: 'bg-red-500/20 text-red-400 border-red-500/30' }
}

const conditionConfig = {
  excellent: { label: 'Excellent', class: 'text-green-400' },
  good: { label: 'Good', class: 'text-blue-400' },
  needs_repair: { label: 'Needs Repair', class: 'text-orange-400' }
}

export default function AssetDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [asset, setAsset] = useState(null)
  const [transactions, setTransactions] = useState([])
  const [loading, setLoading] = useState(true)
  const [editing, setEditing] = useState(searchParams.get('edit') === 'true')
  const [editForm, setEditForm] = useState({})
  const [saving, setSaving] = useState(false)
  const toast = useToast()

  useEffect(() => {
    loadAsset()
    loadTransactions()
  }, [id])

  const loadAsset = async () => {
    try {
      const response = await assetApi.getById(id)
      setAsset(response.asset)
      setEditForm(response.asset)
    } catch (error) {
      toast.error('Failed to load equipment details')
      navigate('/assets')
    } finally {
      setLoading(false)
    }
  }

  const loadTransactions = async () => {
    try {
      const response = await transactionApi.getByAsset(id)
      setTransactions(response.transactions || [])
    } catch (error) {
      console.error('Failed to load transactions')
    }
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await assetApi.update(id, editForm)
      setAsset(editForm)
      setEditing(false)
      toast.success('Equipment updated successfully')
    } catch (error) {
      toast.error('Failed to update equipment')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!confirm('Are you sure you want to delete this equipment?')) return
    try {
      await assetApi.delete(id)
      toast.success('Equipment deleted')
      navigate('/assets')
    } catch (error) {
      toast.error('Failed to delete equipment')
    }
  }

  const printQR = () => {
    const printWindow = window.open('', '_blank')
    printWindow.document.write(`
      <html>
        <head>
          <title>QR Code - ${asset.asset_id}</title>
          <style>
            body { display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: Arial, sans-serif; }
            .qr-container { text-align: center; }
            .qr-container img { width: 200px; height: 200px; }
            .qr-container p { margin: 10px 0; font-weight: bold; }
          </style>
        </head>
        <body>
          <div class="qr-container">
            <img src="${asset.qr_code}" alt="QR Code" />
            <p>${asset.asset_id}</p>
            <p style="font-weight: normal; font-size: 12px;">${asset.asset_name}</p>
          </div>
          <script>window.onload = function() { window.print(); }</script>
        </body>
      </html>
    `)
    printWindow.document.close()
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="skeleton h-8 w-48" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 card">
            <div className="skeleton aspect-video rounded-t-xl" />
            <div className="p-6 space-y-4">
              <div className="skeleton h-6 w-3/4" />
              <div className="skeleton h-4 w-1/2" />
            </div>
          </div>
          <div className="card p-6 space-y-4">
            <div className="skeleton h-6 w-1/2" />
            <div className="skeleton h-4 w-full" />
            <div className="skeleton h-4 w-full" />
          </div>
        </div>
      </div>
    )
  }

  if (!asset) return null

  const Icon = categoryIcons[asset.category] || categoryIcons.default
  const statusInfo = statusConfig[asset.status] || statusConfig.available
  const conditionInfo = conditionConfig[asset.condition_status] || conditionConfig.excellent

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate('/assets')}
            className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <p className="text-sm text-neofox-yellow font-mono">{asset.asset_id}</p>
            <h1 className="text-2xl font-bold">{asset.asset_name}</h1>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {editing ? (
            <>
              <button onClick={() => setEditing(false)} className="btn-secondary">
                <X className="w-4 h-4 mr-2" />
                Cancel
              </button>
              <button onClick={handleSave} disabled={saving} className="btn-primary">
                <Save className="w-4 h-4 mr-2" />
                {saving ? 'Saving...' : 'Save Changes'}
              </button>
            </>
          ) : (
            <>
              <button onClick={printQR} className="btn-secondary">
                <QrCode className="w-4 h-4 mr-2" />
                Print QR
              </button>
              <button onClick={() => setEditing(true)} className="btn-secondary">
                <Edit className="w-4 h-4 mr-2" />
                Edit
              </button>
              <button onClick={handleDelete} className="btn-danger">
                <Trash2 className="w-4 h-4 mr-2" />
                Delete
              </button>
            </>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Photo */}
          <div className="card overflow-hidden">
            <div className="aspect-video bg-neofox-darker flex items-center justify-center">
              {asset.photo ? (
                <img src={asset.photo} alt={asset.asset_name} className="w-full h-full object-contain" />
              ) : (
                <div className="text-center">
                  <Icon className="w-24 h-24 text-neofox-gray mx-auto" />
                  <p className="text-gray-500 mt-2">No photo available</p>
                </div>
              )}
            </div>
          </div>

          {/* Details Form/View */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">Equipment Details</h3>
            </div>
            <div className="card-body">
              {editing ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Name</label>
                    <input
                      type="text"
                      value={editForm.asset_name || ''}
                      onChange={(e) => setEditForm({ ...editForm, asset_name: e.target.value })}
                      className="input-field"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Category</label>
                    <select
                      value={editForm.category || ''}
                      onChange={(e) => setEditForm({ ...editForm, category: e.target.value })}
                      className="select-field"
                    >
                      <option value="Camera">Camera</option>
                      <option value="Lens">Lens</option>
                      <option value="Audio">Audio</option>
                      <option value="Lighting">Lighting</option>
                      <option value="Storage">Storage</option>
                      <option value="Monitor">Monitor</option>
                      <option value="Tripod">Tripod</option>
                      <option value="Cables">Cables</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Serial Number</label>
                    <input
                      type="text"
                      value={editForm.serial_number || ''}
                      onChange={(e) => setEditForm({ ...editForm, serial_number: e.target.value })}
                      className="input-field"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Condition</label>
                    <select
                      value={editForm.condition_status || 'excellent'}
                      onChange={(e) => setEditForm({ ...editForm, condition_status: e.target.value })}
                      className="select-field"
                    >
                      <option value="excellent">Excellent</option>
                      <option value="good">Good</option>
                      <option value="needs_repair">Needs Repair</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Storage Location</label>
                    <input
                      type="text"
                      value={editForm.storage_location || ''}
                      onChange={(e) => setEditForm({ ...editForm, storage_location: e.target.value })}
                      className="input-field"
                      placeholder="e.g., Room A, Warehouse 1"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Shelf / Area</label>
                    <input
                      type="text"
                      value={editForm.shelf || ''}
                      onChange={(e) => setEditForm({ ...editForm, shelf: e.target.value })}
                      className="input-field"
                      placeholder="e.g., Shelf 3, Cabinet B"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea
                      value={editForm.description || ''}
                      onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
                      rows={3}
                      className="input-field"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                    <textarea
                      value={editForm.notes || ''}
                      onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
                      rows={2}
                      className="input-field"
                    />
                  </div>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <p className="text-sm text-gray-500">Category</p>
                    <p className="font-medium flex items-center gap-2 mt-1">
                      <Icon className="w-4 h-4 text-neofox-yellow" />
                      {asset.category}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Serial Number</p>
                    <p className="font-medium font-mono mt-1">{asset.serial_number || '—'}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Condition</p>
                    <p className={`font-medium mt-1 ${conditionInfo.class}`}>{conditionInfo.label}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Total Checkouts</p>
                    <p className="font-medium mt-1">{asset.total_checkouts || 0}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Storage Location</p>
                    <p className="font-medium mt-1">{asset.storage_location || '—'}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Shelf / Area</p>
                    <p className="font-medium mt-1">{asset.shelf || '—'}</p>
                  </div>
                  {asset.description && (
                    <div className="md:col-span-2">
                      <p className="text-sm text-gray-500">Description</p>
                      <p className="mt-1">{asset.description}</p>
                    </div>
                  )}
                  {asset.notes && (
                    <div className="md:col-span-2">
                      <p className="text-sm text-gray-500">Notes</p>
                      <p className="mt-1 text-yellow-400">{asset.notes}</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Transaction History */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">Activity History</h3>
            </div>
            <div className="card-body">
              {transactions.length > 0 ? (
                <div className="space-y-3">
                  {transactions.slice(0, 10).map((tx, idx) => (
                    <div key={idx} className="flex items-start gap-4 p-3 bg-neofox-darker rounded-lg">
                      <div className={`p-2 rounded-lg ${tx.transaction_type === 'checkout' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-green-500/20 text-green-400'}`}>
                        {tx.transaction_type === 'checkout' ? <Clock className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
                      </div>
                      <div className="flex-1">
                        <p className="font-medium">
                          {tx.transaction_type === 'checkout' ? 'Checked out to' : 'Returned by'} {tx.borrower_name}
                        </p>
                        {tx.purpose && <p className="text-sm text-gray-400 mt-1">Purpose: {tx.purpose}</p>}
                        <p className="text-sm text-gray-500 mt-1">
                          {format(parseISO(tx.transaction_date), 'MMM d, yyyy h:mm a')}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-500 text-center py-4">No activity recorded</p>
              )}
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Status Card */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">Current Status</h3>
            </div>
            <div className="card-body">
              <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg border ${statusInfo.class}`}>
                {asset.status === 'available' && <CheckCircle className="w-5 h-5" />}
                {asset.status === 'checked_out' && <Clock className="w-5 h-5" />}
                {asset.status === 'maintenance' && <Wrench className="w-5 h-5" />}
                {asset.status === 'lost' && <AlertTriangle className="w-5 h-5" />}
                <span className="font-medium">{statusInfo.label}</span>
              </div>

              {asset.status === 'checked_out' && (
                <div className="mt-4 space-y-3">
                  <div>
                    <p className="text-sm text-gray-500">Current Holder</p>
                    <p className="font-medium flex items-center gap-2 mt-1">
                      <User className="w-4 h-4" />
                      {asset.current_borrower}
                    </p>
                  </div>
                  {asset.checkout_date && (
                    <div>
                      <p className="text-sm text-gray-500">Checked Out</p>
                      <p className="font-medium flex items-center gap-2 mt-1">
                        <Calendar className="w-4 h-4" />
                        {format(parseISO(asset.checkout_date), 'MMM d, yyyy')}
                      </p>
                    </div>
                  )}
                  {asset.expected_return_date && (
                    <div>
                      <p className="text-sm text-gray-500">Expected Return</p>
                      <p className="font-medium flex items-center gap-2 mt-1">
                        <Calendar className="w-4 h-4" />
                        {format(parseISO(asset.expected_return_date), 'MMM d, yyyy')}
                      </p>
                    </div>
                  )}
                </div>
              )}

              <div className="mt-4 pt-4 border-t border-neofox-gray">
                {asset.status === 'available' ? (
                  <Link to={`/checkout?asset=${asset.asset_id}`} className="btn-primary w-full text-center">
                    Check Out
                  </Link>
                ) : asset.status === 'checked_out' ? (
                  <Link to={`/checkin?asset=${asset.asset_id}`} className="btn-primary w-full text-center">
                    Check In
                  </Link>
                ) : null}
              </div>
            </div>
          </div>

          {/* QR Code */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">QR Code</h3>
            </div>
            <div className="card-body text-center">
              {asset.qr_code ? (
                <>
                  <img
                    src={asset.qr_code}
                    alt="QR Code"
                    className="w-40 h-40 mx-auto bg-white p-2 rounded-lg"
                  />
                  <button onClick={printQR} className="btn-secondary mt-4 w-full">
                    <Download className="w-4 h-4 mr-2" />
                    Download / Print
                  </button>
                </>
              ) : (
                <p className="text-gray-500">No QR code generated</p>
              )}
            </div>
          </div>

          {/* Quick Info */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">Quick Info</h3>
            </div>
            <div className="card-body space-y-3 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Created</span>
                <span>{asset.created_at ? format(parseISO(asset.created_at), 'MMM d, yyyy') : '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Last Updated</span>
                <span>{asset.updated_at ? format(parseISO(asset.updated_at), 'MMM d, yyyy') : '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Last Returned</span>
                <span>{asset.last_returned_date ? format(parseISO(asset.last_returned_date), 'MMM d, yyyy') : '—'}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

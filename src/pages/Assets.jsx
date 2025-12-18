import { useState, useEffect, useMemo } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import { useAuth } from '../context/AuthContext'
import {
  Package,
  Search,
  Filter,
  Plus,
  MoreVertical,
  Eye,
  Edit,
  Trash2,
  QrCode,
  Camera,
  Mic,
  Lightbulb,
  HardDrive,
  Grid,
  List,
  ChevronDown,
  X,
  Download,
  CheckCircle,
  Clock,
  Wrench,
  AlertTriangle,
  SlidersHorizontal
} from 'lucide-react'
import { format, parseISO } from 'date-fns'

const categoryIcons = {
  Camera: Camera,
  Audio: Mic,
  Lighting: Lightbulb,
  Lens: Camera,
  Storage: HardDrive,
  Monitor: Camera,
  Tripod: Package,
  Cables: Package,
  Other: Package,
  default: Package
}

const statusConfig = {
  available: { label: 'Available', class: 'badge-available', icon: CheckCircle },
  checked_out: { label: 'Checked Out', class: 'badge-checked-out', icon: Clock },
  maintenance: { label: 'Maintenance', class: 'badge-maintenance', icon: Wrench },
  lost: { label: 'Lost', class: 'badge-lost', icon: AlertTriangle }
}

function StatusBadge({ status }) {
  const config = statusConfig[status] || statusConfig.available
  const Icon = config.icon
  return (
    <span className={`badge ${config.class} flex items-center gap-1`}>
      <Icon className="w-3 h-3" />
      {config.label}
    </span>
  )
}

function AssetCard({ asset, onView, onEdit, onDelete }) {
  const [menuOpen, setMenuOpen] = useState(false)
  const Icon = categoryIcons[asset.category] || categoryIcons.default

  return (
    <div className="card group hover:border-neofox-yellow/30 transition-all duration-300">
      <div className="relative aspect-video bg-neofox-darker rounded-t-xl overflow-hidden">
        {asset.photo ? (
          <img
            src={asset.photo}
            alt={asset.asset_name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <Icon className="w-16 h-16 text-neofox-gray" />
          </div>
        )}
        <div className="absolute top-3 left-3">
          <StatusBadge status={asset.status} />
        </div>
        <div className="absolute top-3 right-3">
          <div className="relative">
            <button
              onClick={() => setMenuOpen(!menuOpen)}
              className="p-2 bg-black/50 rounded-lg text-white hover:bg-black/70 transition-colors backdrop-blur-sm"
            >
              <MoreVertical className="w-4 h-4" />
            </button>
            {menuOpen && (
              <>
                <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                <div className="absolute right-0 mt-2 w-40 bg-neofox-dark border border-neofox-gray rounded-lg shadow-xl z-20 overflow-hidden">
                  <button
                    onClick={() => { setMenuOpen(false); onView(asset) }}
                    className="flex items-center gap-2 w-full px-4 py-2.5 text-sm hover:bg-neofox-gray transition-colors"
                  >
                    <Eye className="w-4 h-4" /> View Details
                  </button>
                  <button
                    onClick={() => { setMenuOpen(false); onEdit(asset) }}
                    className="flex items-center gap-2 w-full px-4 py-2.5 text-sm hover:bg-neofox-gray transition-colors"
                  >
                    <Edit className="w-4 h-4" /> Edit
                  </button>
                  <button
                    onClick={() => { setMenuOpen(false); onDelete(asset) }}
                    className="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors"
                  >
                    <Trash2 className="w-4 h-4" /> Delete
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>
      <div className="p-4">
        <div className="flex items-start justify-between mb-2">
          <div>
            <p className="text-xs text-neofox-yellow font-mono tracking-wide">{asset.asset_id}</p>
            <h3 className="font-semibold mt-1 line-clamp-1">{asset.asset_name}</h3>
          </div>
          <span className="text-xs text-gray-500 bg-neofox-darker px-2 py-1 rounded-full">
            {asset.category}
          </span>
        </div>
        {asset.current_borrower && (
          <p className="text-sm text-gray-400 mt-2">
            <span className="text-yellow-400">With:</span> {asset.current_borrower}
          </p>
        )}
        {asset.serial_number && (
          <p className="text-xs text-gray-500 mt-1 font-mono">
            S/N: {asset.serial_number}
          </p>
        )}
      </div>
    </div>
  )
}

function AssetRow({ asset, onView, onEdit, onDelete, selected, onSelect }) {
  const Icon = categoryIcons[asset.category] || categoryIcons.default

  return (
    <tr className="group">
      <td className="px-4 py-3">
        <input
          type="checkbox"
          checked={selected}
          onChange={(e) => onSelect(e.target.checked)}
          className="w-4 h-4 rounded border-neofox-gray bg-neofox-darker text-neofox-yellow focus:ring-neofox-yellow"
        />
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-neofox-darker flex items-center justify-center overflow-hidden flex-shrink-0">
            {asset.photo ? (
              <img src={asset.photo} alt="" className="w-full h-full object-cover" />
            ) : (
              <Icon className="w-5 h-5 text-gray-500" />
            )}
          </div>
          <div>
            <p className="font-medium group-hover:text-neofox-yellow transition-colors">{asset.asset_name}</p>
            <p className="text-xs text-neofox-yellow font-mono">{asset.asset_id}</p>
          </div>
        </div>
      </td>
      <td className="px-4 py-3 text-sm text-gray-400">{asset.category}</td>
      <td className="px-4 py-3">
        <StatusBadge status={asset.status} />
      </td>
      <td className="px-4 py-3 text-sm">
        {asset.current_borrower || <span className="text-gray-500">—</span>}
      </td>
      <td className="px-4 py-3 text-sm text-gray-400 font-mono">
        {asset.serial_number || <span className="text-gray-500">—</span>}
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            onClick={() => onView(asset)}
            className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
            title="View"
          >
            <Eye className="w-4 h-4" />
          </button>
          <button
            onClick={() => onEdit(asset)}
            className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
            title="Edit"
          >
            <Edit className="w-4 h-4" />
          </button>
          <button
            onClick={() => onDelete(asset)}
            className="p-2 hover:bg-red-500/20 text-red-400 rounded-lg transition-colors"
            title="Delete"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      </td>
    </tr>
  )
}

export default function Assets() {
  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [viewMode, setViewMode] = useState('grid')
  const [selectedAssets, setSelectedAssets] = useState(new Set())
  const [filterPanelOpen, setFilterPanelOpen] = useState(false)
  const [deleteModalOpen, setDeleteModalOpen] = useState(false)
  const [assetToDelete, setAssetToDelete] = useState(null)
  const [searchParams, setSearchParams] = useSearchParams()
  const toast = useToast()
  const { isAdmin } = useAuth()

  useEffect(() => {
    loadAssets()
  }, [])

  const loadAssets = async () => {
    try {
      setLoading(true)
      const response = await assetApi.getAll()
      setAssets(response.assets || [])
    } catch (error) {
      toast.error('Failed to load equipment')
    } finally {
      setLoading(false)
    }
  }

  const categories = useMemo(() => {
    const cats = new Set(assets.map(a => a.category))
    return Array.from(cats).sort()
  }, [assets])

  const filteredAssets = useMemo(() => {
    return assets.filter(asset => {
      const matchesSearch = !searchQuery ||
        asset.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        asset.asset_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
        asset.serial_number?.toLowerCase().includes(searchQuery.toLowerCase())

      const matchesCategory = !categoryFilter || asset.category === categoryFilter
      const matchesStatus = !statusFilter || asset.status === statusFilter

      return matchesSearch && matchesCategory && matchesStatus
    })
  }, [assets, searchQuery, categoryFilter, statusFilter])

  const handleView = (asset) => {
    window.location.href = `/assets/${asset.asset_id}`
  }

  const handleEdit = (asset) => {
    window.location.href = `/assets/${asset.asset_id}?edit=true`
  }

  const handleDelete = (asset) => {
    setAssetToDelete(asset)
    setDeleteModalOpen(true)
  }

  const confirmDelete = async () => {
    if (!assetToDelete) return
    try {
      await assetApi.delete(assetToDelete.asset_id)
      setAssets(prev => prev.filter(a => a.asset_id !== assetToDelete.asset_id))
      toast.success('Equipment deleted successfully')
    } catch (error) {
      toast.error('Failed to delete equipment')
    } finally {
      setDeleteModalOpen(false)
      setAssetToDelete(null)
    }
  }

  const toggleSelectAll = () => {
    if (selectedAssets.size === filteredAssets.length) {
      setSelectedAssets(new Set())
    } else {
      setSelectedAssets(new Set(filteredAssets.map(a => a.asset_id)))
    }
  }

  const clearFilters = () => {
    setSearchQuery('')
    setCategoryFilter('')
    setStatusFilter('')
  }

  const hasFilters = searchQuery || categoryFilter || statusFilter

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Equipment</h1>
          <p className="text-gray-400">
            {filteredAssets.length} of {assets.length} items
            {hasFilters && ' (filtered)'}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Link to="/assets/add" className="btn-primary flex items-center gap-2">
            <Plus className="w-5 h-5" />
            Add Equipment
          </Link>
        </div>
      </div>

      {/* Search and Filters */}
      <div className="card p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
            <input
              type="text"
              placeholder="Search by name, ID, or serial number..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="input-field pl-10"
            />
          </div>

          {/* Filter Dropdowns */}
          <div className="flex items-center gap-3">
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="select-field w-40"
            >
              <option value="">All Categories</option>
              {categories.map(cat => (
                <option key={cat} value={cat}>{cat}</option>
              ))}
            </select>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="select-field w-40"
            >
              <option value="">All Status</option>
              <option value="available">Available</option>
              <option value="checked_out">Checked Out</option>
              <option value="maintenance">Maintenance</option>
              <option value="lost">Lost</option>
            </select>

            {hasFilters && (
              <button
                onClick={clearFilters}
                className="p-2 text-gray-400 hover:text-white hover:bg-neofox-gray rounded-lg transition-colors"
                title="Clear filters"
              >
                <X className="w-5 h-5" />
              </button>
            )}

            {/* View Toggle */}
            <div className="flex items-center bg-neofox-darker rounded-lg p-1">
              <button
                onClick={() => setViewMode('grid')}
                className={`p-2 rounded-lg transition-colors ${viewMode === 'grid' ? 'bg-neofox-gray text-white' : 'text-gray-500 hover:text-white'}`}
              >
                <Grid className="w-5 h-5" />
              </button>
              <button
                onClick={() => setViewMode('list')}
                className={`p-2 rounded-lg transition-colors ${viewMode === 'list' ? 'bg-neofox-gray text-white' : 'text-gray-500 hover:text-white'}`}
              >
                <List className="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Loading State */}
      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {[1, 2, 3, 4, 5, 6, 7, 8].map(i => (
            <div key={i} className="card">
              <div className="skeleton aspect-video rounded-t-xl" />
              <div className="p-4 space-y-3">
                <div className="skeleton h-4 w-20" />
                <div className="skeleton h-5 w-3/4" />
              </div>
            </div>
          ))}
        </div>
      ) : filteredAssets.length === 0 ? (
        <div className="card p-12 text-center">
          <Package className="w-16 h-16 mx-auto text-gray-600 mb-4" />
          <h3 className="text-lg font-medium mb-2">No equipment found</h3>
          <p className="text-gray-500 mb-4">
            {hasFilters ? 'Try adjusting your filters' : 'Add your first piece of equipment to get started'}
          </p>
          {hasFilters ? (
            <button onClick={clearFilters} className="btn-secondary">
              Clear Filters
            </button>
          ) : (
            <Link to="/assets/add" className="btn-primary inline-flex items-center gap-2">
              <Plus className="w-5 h-5" />
              Add Equipment
            </Link>
          )}
        </div>
      ) : viewMode === 'grid' ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredAssets.map(asset => (
            <AssetCard
              key={asset.asset_id}
              asset={asset}
              onView={handleView}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          ))}
        </div>
      ) : (
        <div className="card overflow-hidden">
          <div className="table-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th className="w-12">
                    <input
                      type="checkbox"
                      checked={selectedAssets.size === filteredAssets.length && filteredAssets.length > 0}
                      onChange={toggleSelectAll}
                      className="w-4 h-4 rounded border-neofox-gray bg-neofox-darker text-neofox-yellow focus:ring-neofox-yellow"
                    />
                  </th>
                  <th>Equipment</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Current Holder</th>
                  <th>Serial Number</th>
                  <th className="w-32">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredAssets.map(asset => (
                  <AssetRow
                    key={asset.asset_id}
                    asset={asset}
                    onView={handleView}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                    selected={selectedAssets.has(asset.asset_id)}
                    onSelect={(checked) => {
                      const newSelected = new Set(selectedAssets)
                      if (checked) {
                        newSelected.add(asset.asset_id)
                      } else {
                        newSelected.delete(asset.asset_id)
                      }
                      setSelectedAssets(newSelected)
                    }}
                  />
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteModalOpen && (
        <div className="modal-overlay" onClick={() => setDeleteModalOpen(false)}>
          <div className="modal-content max-w-md" onClick={e => e.stopPropagation()}>
            <div className="p-6">
              <div className="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Trash2 className="w-6 h-6 text-red-400" />
              </div>
              <h3 className="text-lg font-semibold text-center mb-2">Delete Equipment?</h3>
              <p className="text-gray-400 text-center mb-6">
                Are you sure you want to delete <strong>{assetToDelete?.asset_name}</strong>?
                This action cannot be undone.
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => setDeleteModalOpen(false)}
                  className="btn-secondary flex-1"
                >
                  Cancel
                </button>
                <button
                  onClick={confirmDelete}
                  className="btn-danger flex-1"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

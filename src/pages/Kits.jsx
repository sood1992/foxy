import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  Package,
  Plus,
  Search,
  Edit2,
  Trash2,
  Loader2,
  Boxes,
  ChevronDown,
  ChevronUp,
  Check,
  X,
  Copy
} from 'lucide-react'

export default function Kits() {
  const navigate = useNavigate()
  const toast = useToast()

  const [kits, setKits] = useState([])
  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [editingKit, setEditingKit] = useState(null)
  const [expandedKit, setExpandedKit] = useState(null)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [kitsRes, assetsRes] = await Promise.all([
        assetApi.getKits(),
        assetApi.getAll()
      ])
      setKits(kitsRes.kits || [])
      setAssets(assetsRes.assets || [])
    } catch (error) {
      toast.error('Failed to load data')
    } finally {
      setLoading(false)
    }
  }

  const filteredKits = kits.filter(kit =>
    !searchQuery ||
    kit.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    kit.description?.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const handleDeleteKit = async (kitId) => {
    if (!confirm('Are you sure you want to delete this kit?')) return

    try {
      await assetApi.deleteKit(kitId)
      toast.success('Kit deleted successfully')
      loadData()
    } catch (error) {
      toast.error('Failed to delete kit')
    }
  }

  const getKitAssets = (kit) => {
    return kit.asset_ids?.map(id => assets.find(a => a.asset_id === id)).filter(Boolean) || []
  }

  const getKitAvailability = (kit) => {
    const kitAssets = getKitAssets(kit)
    const available = kitAssets.filter(a => a.status === 'available').length
    return { available, total: kitAssets.length }
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
          <h1 className="text-2xl font-bold">Equipment Kits</h1>
          <p className="text-gray-400">Group equipment into bundles for quick checkout</p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="btn-primary flex items-center gap-2"
        >
          <Plus className="w-5 h-5" />
          Create Kit
        </button>
      </div>

      {/* Search */}
      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
        <input
          type="text"
          placeholder="Search kits..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="input-field pl-10"
        />
      </div>

      {/* Kits Grid */}
      {filteredKits.length > 0 ? (
        <div className="grid gap-4">
          {filteredKits.map(kit => {
            const kitAssets = getKitAssets(kit)
            const { available, total } = getKitAvailability(kit)
            const isExpanded = expandedKit === kit.id
            const allAvailable = available === total

            return (
              <div key={kit.id} className="card">
                <div className="card-body">
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                      <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${
                        allAvailable ? 'bg-green-500/20' : 'bg-orange-500/20'
                      }`}>
                        <Boxes className={`w-6 h-6 ${allAvailable ? 'text-green-400' : 'text-orange-400'}`} />
                      </div>
                      <div>
                        <h3 className="font-semibold text-lg">{kit.name}</h3>
                        <p className="text-sm text-gray-400">{kit.description}</p>
                        <div className="flex items-center gap-4 mt-2">
                          <span className="text-sm text-gray-500">
                            {total} items
                          </span>
                          <span className={`text-sm ${allAvailable ? 'text-green-400' : 'text-orange-400'}`}>
                            {available}/{total} available
                          </span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => navigate(`/checkout?kit=${kit.id}`)}
                        disabled={!allAvailable}
                        className={`btn-primary text-sm ${!allAvailable ? 'opacity-50 cursor-not-allowed' : ''}`}
                      >
                        Checkout Kit
                      </button>
                      <button
                        onClick={() => setEditingKit(kit)}
                        className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
                      >
                        <Edit2 className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDeleteKit(kit.id)}
                        className="p-2 hover:bg-red-500/20 rounded-lg transition-colors text-red-400"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => setExpandedKit(isExpanded ? null : kit.id)}
                        className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
                      >
                        {isExpanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>

                  {/* Expanded Items List */}
                  {isExpanded && (
                    <div className="mt-4 pt-4 border-t border-neofox-gray">
                      <h4 className="text-sm font-medium text-gray-400 mb-3">Kit Contents</h4>
                      <div className="grid gap-2">
                        {kitAssets.map(asset => (
                          <div
                            key={asset.asset_id}
                            className="flex items-center justify-between p-3 bg-neofox-darker rounded-lg"
                          >
                            <div className="flex items-center gap-3">
                              <Package className="w-4 h-4 text-gray-500" />
                              <div>
                                <p className="font-medium text-sm">{asset.asset_name}</p>
                                <p className="text-xs text-gray-500">{asset.asset_id}</p>
                              </div>
                            </div>
                            <span className={`badge ${
                              asset.status === 'available' ? 'badge-available' :
                              asset.status === 'checked_out' ? 'badge-checked-out' :
                              'badge-maintenance'
                            }`}>
                              {asset.status.replace('_', ' ')}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      ) : (
        <div className="card">
          <div className="card-body text-center py-12">
            <Boxes className="w-12 h-12 text-gray-600 mx-auto mb-4" />
            <h3 className="font-medium text-lg mb-2">No kits created</h3>
            <p className="text-gray-400 mb-4">Create kits to group equipment for quick checkout</p>
            <button
              onClick={() => setShowCreateModal(true)}
              className="btn-primary"
            >
              Create Your First Kit
            </button>
          </div>
        </div>
      )}

      {/* Create/Edit Kit Modal */}
      {(showCreateModal || editingKit) && (
        <KitModal
          kit={editingKit}
          assets={assets}
          onClose={() => {
            setShowCreateModal(false)
            setEditingKit(null)
          }}
          onSave={() => {
            setShowCreateModal(false)
            setEditingKit(null)
            loadData()
          }}
        />
      )}
    </div>
  )
}

function KitModal({ kit, assets, onClose, onSave }) {
  const toast = useToast()
  const [saving, setSaving] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [form, setForm] = useState({
    name: kit?.name || '',
    description: kit?.description || '',
    asset_ids: kit?.asset_ids || []
  })

  const availableAssets = assets.filter(a =>
    !searchQuery ||
    a.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    a.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const toggleAsset = (assetId) => {
    setForm(prev => ({
      ...prev,
      asset_ids: prev.asset_ids.includes(assetId)
        ? prev.asset_ids.filter(id => id !== assetId)
        : [...prev.asset_ids, assetId]
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.name.trim()) {
      toast.error('Please enter a kit name')
      return
    }
    if (form.asset_ids.length === 0) {
      toast.error('Please select at least one item')
      return
    }

    setSaving(true)
    try {
      if (kit) {
        await assetApi.updateKit(kit.id, form)
        toast.success('Kit updated successfully')
      } else {
        await assetApi.createKit(form)
        toast.success('Kit created successfully')
      }
      onSave()
    } catch (error) {
      toast.error(error.message || 'Failed to save kit')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-neofox-dark border border-neofox-gray rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
        <div className="p-6 border-b border-neofox-gray">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">{kit ? 'Edit Kit' : 'Create Kit'}</h2>
            <button onClick={onClose} className="p-2 hover:bg-neofox-gray rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6 overflow-y-auto max-h-[calc(90vh-180px)]">
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">Kit Name *</label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="input-field"
              placeholder="e.g., Interview Kit, Studio A Setup"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">Description</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="input-field"
              rows={2}
              placeholder="What is this kit used for?"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              Select Equipment ({form.asset_ids.length} selected)
            </label>

            <div className="relative mb-3">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
              <input
                type="text"
                placeholder="Search equipment..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="input-field pl-10 text-sm"
              />
            </div>

            <div className="border border-neofox-gray rounded-lg max-h-64 overflow-y-auto">
              {availableAssets.map(asset => (
                <label
                  key={asset.asset_id}
                  className="flex items-center gap-3 p-3 hover:bg-neofox-gray cursor-pointer border-b border-neofox-gray last:border-0"
                >
                  <input
                    type="checkbox"
                    checked={form.asset_ids.includes(asset.asset_id)}
                    onChange={() => toggleAsset(asset.asset_id)}
                    className="w-4 h-4 rounded border-neofox-gray text-neofox-yellow focus:ring-neofox-yellow"
                  />
                  <div className="flex-1">
                    <p className="font-medium text-sm">{asset.asset_name}</p>
                    <p className="text-xs text-gray-500">{asset.asset_id} • {asset.category}</p>
                  </div>
                  <span className={`badge text-xs ${
                    asset.status === 'available' ? 'badge-available' : 'badge-checked-out'
                  }`}>
                    {asset.status.replace('_', ' ')}
                  </span>
                </label>
              ))}
            </div>
          </div>
        </form>

        <div className="p-6 border-t border-neofox-gray flex justify-end gap-3">
          <button type="button" onClick={onClose} className="btn-secondary">
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className="btn-primary flex items-center gap-2"
          >
            {saving ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Saving...
              </>
            ) : (
              <>
                <Check className="w-4 h-4" />
                {kit ? 'Update Kit' : 'Create Kit'}
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  )
}

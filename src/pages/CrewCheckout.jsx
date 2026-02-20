import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { assetApi, userApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ArrowLeft,
  Users,
  Package,
  Calendar,
  FileText,
  Loader2,
  CheckCircle,
  X,
  Plus,
  Search,
  ChevronDown
} from 'lucide-react'
import { format, addDays } from 'date-fns'

export default function CrewCheckout() {
  const navigate = useNavigate()
  const toast = useToast()

  const [assets, setAssets] = useState([])
  const [borrowers, setBorrowers] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedAssets, setSelectedAssets] = useState([])
  const [showAssetDropdown, setShowAssetDropdown] = useState(false)
  const [categoryFilter, setCategoryFilter] = useState('')

  const [form, setForm] = useState({
    borrower_name: '',
    expected_return_date: format(addDays(new Date(), 7), 'yyyy-MM-dd'),
    purpose: '',
    notes: ''
  })

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [assetsRes, borrowersRes] = await Promise.all([
        assetApi.getAll({ status: 'available' }),
        userApi.getBorrowers()
      ])
      setAssets(assetsRes.assets?.filter(a => a.status === 'available') || [])
      setBorrowers(borrowersRes.borrowers || [])
    } catch (error) {
      toast.error('Failed to load data')
    } finally {
      setLoading(false)
    }
  }

  const categories = [...new Set(assets.map(a => a.category))].sort()

  const filteredAssets = assets.filter(asset => {
    const matchesSearch = !searchQuery ||
      asset.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      asset.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
    const matchesCategory = !categoryFilter || asset.category === categoryFilter
    const notSelected = !selectedAssets.find(a => a.asset_id === asset.asset_id)
    return matchesSearch && matchesCategory && notSelected
  })

  const addAsset = (asset) => {
    setSelectedAssets(prev => [...prev, asset])
    setShowAssetDropdown(false)
    setSearchQuery('')
  }

  const removeAsset = (assetId) => {
    setSelectedAssets(prev => prev.filter(a => a.asset_id !== assetId))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (selectedAssets.length === 0) {
      toast.error('Please select at least one equipment')
      return
    }

    if (!form.borrower_name.trim()) {
      toast.error('Please enter borrower name')
      return
    }

    setSubmitting(true)
    try {
      await assetApi.bulkCheckout({
        asset_ids: selectedAssets.map(a => a.asset_id),
        borrower_name: form.borrower_name,
        expected_return_date: form.expected_return_date,
        purpose: form.purpose,
        notes: form.notes
      })

      toast.success(`${selectedAssets.length} items checked out successfully`)
      navigate('/assets')
    } catch (error) {
      toast.error(error.message || 'Failed to checkout')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-neofox-yellow" />
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4 mb-6">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div>
          <h1 className="text-2xl font-bold">Crew Checkout</h1>
          <p className="text-gray-400">Assign multiple equipment to a team member</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Equipment Selection */}
        <div className="card">
          <div className="card-header flex items-center justify-between">
            <h3 className="font-semibold flex items-center gap-2">
              <Package className="w-5 h-5 text-neofox-yellow" />
              Select Equipment
            </h3>
            <span className="text-sm text-gray-400">
              {selectedAssets.length} item{selectedAssets.length !== 1 ? 's' : ''} selected
            </span>
          </div>
          <div className="card-body">
            {/* Selected Assets */}
            {selectedAssets.length > 0 && (
              <div className="mb-4 space-y-2">
                {selectedAssets.map(asset => (
                  <div
                    key={asset.asset_id}
                    className="flex items-center gap-3 p-3 bg-neofox-darker rounded-lg"
                  >
                    <div className="w-12 h-12 bg-neofox-gray rounded-lg flex items-center justify-center">
                      <Package className="w-6 h-6 text-gray-500" />
                    </div>
                    <div className="flex-1">
                      <p className="font-medium">{asset.asset_name}</p>
                      <p className="text-sm text-gray-400">{asset.asset_id} • {asset.category}</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => removeAsset(asset.asset_id)}
                      className="p-2 hover:bg-neofox-gray rounded-lg text-red-400 hover:text-red-300 transition-colors"
                    >
                      <X className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>
            )}

            {/* Search and Add */}
            <div className="relative">
              <div className="flex gap-3">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
                  <input
                    type="text"
                    placeholder="Search equipment to add..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    onFocus={() => setShowAssetDropdown(true)}
                    className="input-field pl-10"
                  />
                </div>
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
              </div>
              {showAssetDropdown && (
                <>
                  <div
                    className="fixed inset-0 z-10"
                    onClick={() => setShowAssetDropdown(false)}
                  />
                  <div className="absolute z-20 w-full mt-2 bg-neofox-dark border border-neofox-gray rounded-lg shadow-xl max-h-64 overflow-y-auto">
                    {filteredAssets.length > 0 ? (
                      filteredAssets.slice(0, 20).map(asset => (
                        <button
                          key={asset.asset_id}
                          type="button"
                          onClick={() => addAsset(asset)}
                          className="flex items-center gap-3 w-full p-3 hover:bg-neofox-gray transition-colors"
                        >
                          <div className="w-10 h-10 bg-neofox-darker rounded-lg flex items-center justify-center">
                            <Package className="w-5 h-5 text-gray-500" />
                          </div>
                          <div className="text-left flex-1">
                            <p className="font-medium">{asset.asset_name}</p>
                            <p className="text-sm text-gray-400">{asset.asset_id} • {asset.category}</p>
                          </div>
                          <Plus className="w-5 h-5 text-neofox-yellow" />
                        </button>
                      ))
                    ) : (
                      <div className="p-4 text-center text-gray-500">
                        No available equipment found
                      </div>
                    )}
                  </div>
                </>
              )}
            </div>
          </div>
        </div>

        {/* Borrower Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Users className="w-5 h-5 text-neofox-yellow" />
              Assignment Details
            </h3>
          </div>
          <div className="card-body">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Crew Member / Borrower <span className="text-red-400">*</span>
                </label>
                <input
                  type="text"
                  value={form.borrower_name}
                  onChange={(e) => setForm({ ...form, borrower_name: e.target.value })}
                  className="input-field"
                  placeholder="Enter name"
                  list="borrowers"
                  required
                />
                <datalist id="borrowers">
                  {borrowers.map(b => (
                    <option key={b} value={b} />
                  ))}
                </datalist>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Expected Return Date <span className="text-red-400">*</span>
                </label>
                <input
                  type="date"
                  value={form.expected_return_date}
                  onChange={(e) => setForm({ ...form, expected_return_date: e.target.value })}
                  min={format(new Date(), 'yyyy-MM-dd')}
                  className="input-field"
                  required
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Project / Purpose
                </label>
                <input
                  type="text"
                  value={form.purpose}
                  onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                  className="input-field"
                  placeholder="e.g., Wedding shoot, Commercial project, etc."
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                <textarea
                  value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                  rows={2}
                  className="input-field"
                  placeholder="Any additional notes"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Summary */}
        {selectedAssets.length > 0 && (
          <div className="card bg-neofox-yellow/10 border-neofox-yellow/30">
            <div className="card-body">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-400">Ready to checkout</p>
                  <p className="text-xl font-bold text-neofox-yellow">
                    {selectedAssets.length} item{selectedAssets.length !== 1 ? 's' : ''}
                  </p>
                </div>
                {form.borrower_name && (
                  <div className="text-right">
                    <p className="text-sm text-gray-400">Assigned to</p>
                    <p className="font-medium">{form.borrower_name}</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="btn-secondary"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting || selectedAssets.length === 0}
            className="btn-primary flex items-center gap-2"
          >
            {submitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                Processing...
              </>
            ) : (
              <>
                <CheckCircle className="w-5 h-5" />
                Check Out {selectedAssets.length} Item{selectedAssets.length !== 1 ? 's' : ''}
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  )
}

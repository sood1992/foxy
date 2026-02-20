import { useState, useEffect } from 'react'
import { assetApi, requestApi } from '../api/client'
import {
  Package,
  Send,
  CheckCircle,
  Loader2,
  Search,
  X,
  Plus,
  Calendar,
  User,
  Mail,
  FileText
} from 'lucide-react'

export default function GearRequest() {
  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [submitted, setSubmitted] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedAssets, setSelectedAssets] = useState([])
  const [showAssetDropdown, setShowAssetDropdown] = useState(false)

  const [form, setForm] = useState({
    requester_name: '',
    requester_email: '',
    request_dates: '',
    purpose: ''
  })

  const [errors, setErrors] = useState({})

  useEffect(() => {
    loadAssets()
  }, [])

  const loadAssets = async () => {
    try {
      const response = await assetApi.getAll({ status: 'available' })
      setAssets(response.assets?.filter(a => a.status === 'available') || [])
    } catch (error) {
      console.error('Failed to load assets')
    } finally {
      setLoading(false)
    }
  }

  const filteredAssets = assets.filter(asset => {
    const matchesSearch = !searchQuery ||
      asset.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      asset.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
    const notSelected = !selectedAssets.find(a => a.asset_id === asset.asset_id)
    return matchesSearch && notSelected
  })

  const addAsset = (asset) => {
    setSelectedAssets(prev => [...prev, asset])
    setShowAssetDropdown(false)
    setSearchQuery('')
  }

  const removeAsset = (assetId) => {
    setSelectedAssets(prev => prev.filter(a => a.asset_id !== assetId))
  }

  const validate = () => {
    const newErrors = {}
    if (!form.requester_name.trim()) newErrors.requester_name = 'Name is required'
    if (!form.requester_email.trim()) newErrors.requester_email = 'Email is required'
    if (form.requester_email && !/\S+@\S+\.\S+/.test(form.requester_email)) {
      newErrors.requester_email = 'Valid email is required'
    }
    if (!form.request_dates.trim()) newErrors.request_dates = 'Dates are required'
    if (selectedAssets.length === 0) newErrors.items = 'Please select at least one item'
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!validate()) return

    setSubmitting(true)
    try {
      await requestApi.create({
        requester_name: form.requester_name,
        requester_email: form.requester_email,
        request_dates: form.request_dates,
        purpose: form.purpose,
        required_items: selectedAssets.map(a => `${a.asset_name} (${a.asset_id})`).join(', ')
      })
      setSubmitted(true)
    } catch (error) {
      setErrors({ submit: 'Failed to submit request. Please try again.' })
    } finally {
      setSubmitting(false)
    }
  }

  if (submitted) {
    return (
      <div className="min-h-screen bg-neofox-darker flex items-center justify-center p-4">
        <div className="max-w-md w-full text-center">
          <div className="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
            <CheckCircle className="w-10 h-10 text-green-400" />
          </div>
          <h1 className="text-2xl font-bold mb-2">Request Submitted!</h1>
          <p className="text-gray-400 mb-6">
            Your gear request has been submitted successfully. You'll receive an email confirmation once it's been reviewed.
          </p>
          <button
            onClick={() => {
              setSubmitted(false)
              setForm({ requester_name: '', requester_email: '', request_dates: '', purpose: '' })
              setSelectedAssets([])
            }}
            className="btn-primary"
          >
            Submit Another Request
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-neofox-darker">
      {/* Header */}
      <div className="bg-neofox-dark border-b border-neofox-gray">
        <div className="max-w-3xl mx-auto px-4 py-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-neofox-yellow rounded-lg flex items-center justify-center">
              <Package className="w-6 h-6 text-black" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-neofox-yellow">NeoFox Media</h1>
              <p className="text-sm text-gray-400">Gear Request Form</p>
            </div>
          </div>
        </div>
      </div>

      {/* Form */}
      <div className="max-w-3xl mx-auto px-4 py-8">
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Personal Info */}
          <div className="card">
            <div className="card-header">
              <h2 className="font-semibold flex items-center gap-2">
                <User className="w-5 h-5 text-neofox-yellow" />
                Your Information
              </h2>
            </div>
            <div className="card-body">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">
                    Your Name <span className="text-red-400">*</span>
                  </label>
                  <input
                    type="text"
                    value={form.requester_name}
                    onChange={(e) => setForm({ ...form, requester_name: e.target.value })}
                    className={`input-field ${errors.requester_name ? 'border-red-500' : ''}`}
                    placeholder="Enter your name"
                  />
                  {errors.requester_name && (
                    <p className="text-sm text-red-400 mt-1">{errors.requester_name}</p>
                  )}
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">
                    Email <span className="text-red-400">*</span>
                  </label>
                  <input
                    type="email"
                    value={form.requester_email}
                    onChange={(e) => setForm({ ...form, requester_email: e.target.value })}
                    className={`input-field ${errors.requester_email ? 'border-red-500' : ''}`}
                    placeholder="your@email.com"
                  />
                  {errors.requester_email && (
                    <p className="text-sm text-red-400 mt-1">{errors.requester_email}</p>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Request Details */}
          <div className="card">
            <div className="card-header">
              <h2 className="font-semibold flex items-center gap-2">
                <Calendar className="w-5 h-5 text-neofox-yellow" />
                Request Details
              </h2>
            </div>
            <div className="card-body space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Request Dates <span className="text-red-400">*</span>
                </label>
                <input
                  type="text"
                  value={form.request_dates}
                  onChange={(e) => setForm({ ...form, request_dates: e.target.value })}
                  className={`input-field ${errors.request_dates ? 'border-red-500' : ''}`}
                  placeholder="e.g., Dec 20-25, 2025"
                />
                {errors.request_dates && (
                  <p className="text-sm text-red-400 mt-1">{errors.request_dates}</p>
                )}
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Purpose / Project
                </label>
                <textarea
                  value={form.purpose}
                  onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                  rows={2}
                  className="input-field"
                  placeholder="Describe what you'll be using the equipment for"
                />
              </div>
            </div>
          </div>

          {/* Equipment Selection */}
          <div className="card">
            <div className="card-header flex items-center justify-between">
              <h2 className="font-semibold flex items-center gap-2">
                <Package className="w-5 h-5 text-neofox-yellow" />
                Equipment Needed
              </h2>
              {selectedAssets.length > 0 && (
                <span className="text-sm text-gray-400">{selectedAssets.length} selected</span>
              )}
            </div>
            <div className="card-body">
              {errors.items && (
                <p className="text-sm text-red-400 mb-3">{errors.items}</p>
              )}

              {/* Selected Items */}
              {selectedAssets.length > 0 && (
                <div className="mb-4 space-y-2">
                  {selectedAssets.map(asset => (
                    <div
                      key={asset.asset_id}
                      className="flex items-center gap-3 p-3 bg-neofox-darker rounded-lg"
                    >
                      <Package className="w-5 h-5 text-neofox-yellow" />
                      <div className="flex-1">
                        <p className="font-medium">{asset.asset_name}</p>
                        <p className="text-sm text-gray-400">{asset.category}</p>
                      </div>
                      <button
                        type="button"
                        onClick={() => removeAsset(asset.asset_id)}
                        className="p-1 hover:bg-neofox-gray rounded text-red-400"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}

              {/* Search */}
              <div className="relative">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
                  <input
                    type="text"
                    placeholder="Search available equipment..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    onFocus={() => setShowAssetDropdown(true)}
                    className="input-field pl-10"
                  />
                </div>
                {showAssetDropdown && (
                  <>
                    <div
                      className="fixed inset-0 z-10"
                      onClick={() => setShowAssetDropdown(false)}
                    />
                    <div className="absolute z-20 w-full mt-2 bg-neofox-dark border border-neofox-gray rounded-lg shadow-xl max-h-64 overflow-y-auto">
                      {loading ? (
                        <div className="p-4 text-center text-gray-500">
                          <Loader2 className="w-5 h-5 animate-spin mx-auto" />
                        </div>
                      ) : filteredAssets.length > 0 ? (
                        filteredAssets.slice(0, 15).map(asset => (
                          <button
                            key={asset.asset_id}
                            type="button"
                            onClick={() => addAsset(asset)}
                            className="flex items-center gap-3 w-full p-3 hover:bg-neofox-gray transition-colors text-left"
                          >
                            <Package className="w-5 h-5 text-gray-500" />
                            <div className="flex-1">
                              <p className="font-medium">{asset.asset_name}</p>
                              <p className="text-sm text-gray-400">{asset.category}</p>
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

          {/* Submit */}
          {errors.submit && (
            <div className="p-4 bg-red-500/20 border border-red-500/30 rounded-lg text-red-400">
              {errors.submit}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="btn-primary w-full flex items-center justify-center gap-2 py-3"
          >
            {submitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <Send className="w-5 h-5" />
                Submit Request
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  )
}

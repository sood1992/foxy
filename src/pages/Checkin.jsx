import { useState, useEffect, useRef } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ArrowLeft,
  LogIn,
  Search,
  Package,
  User,
  Calendar,
  Loader2,
  CheckCircle,
  X,
  QrCode,
  AlertCircle,
  Camera,
  Upload
} from 'lucide-react'
import { format, parseISO, isPast } from 'date-fns'

export default function Checkin() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const toast = useToast()
  const fileInputRef = useRef(null)

  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedAsset, setSelectedAsset] = useState(null)
  const [showAssetDropdown, setShowAssetDropdown] = useState(false)
  const [photos, setPhotos] = useState([])
  const [photosPreviews, setPhotosPreviews] = useState([])

  const [form, setForm] = useState({
    condition_on_return: 'excellent',
    notes: ''
  })

  useEffect(() => {
    loadData()
  }, [])

  useEffect(() => {
    const assetId = searchParams.get('asset')
    if (assetId && assets.length > 0) {
      const asset = assets.find(a => a.asset_id === assetId)
      if (asset && asset.status === 'checked_out') {
        setSelectedAsset(asset)
      }
    }
  }, [searchParams, assets])

  const loadData = async () => {
    try {
      const response = await assetApi.getAll({ status: 'checked_out' })
      setAssets(response.assets?.filter(a => a.status === 'checked_out') || [])
    } catch (error) {
      toast.error('Failed to load data')
    } finally {
      setLoading(false)
    }
  }

  const filteredAssets = assets.filter(asset =>
    !searchQuery ||
    asset.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    asset.asset_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
    asset.current_borrower?.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const handlePhotoAdd = (e) => {
    const files = Array.from(e.target.files)
    if (files.length === 0) return

    // Limit to 5 photos
    const remaining = 5 - photos.length
    const newFiles = files.slice(0, remaining)

    setPhotos(prev => [...prev, ...newFiles])

    // Create previews
    newFiles.forEach(file => {
      const reader = new FileReader()
      reader.onloadend = () => {
        setPhotosPreviews(prev => [...prev, reader.result])
      }
      reader.readAsDataURL(file)
    })

    // Reset input
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const removePhoto = (index) => {
    setPhotos(prev => prev.filter((_, i) => i !== index))
    setPhotosPreviews(prev => prev.filter((_, i) => i !== index))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!selectedAsset) {
      toast.error('Please select an equipment')
      return
    }

    setSubmitting(true)
    try {
      // Upload photos first if any
      const uploadedPhotoPaths = []
      for (const photo of photos) {
        const formData = new FormData()
        formData.append('photo', photo)
        formData.append('photo_type', 'checkin')
        formData.append('transaction_type', 'checkin')
        formData.append('borrower_name', selectedAsset.current_borrower || 'Unknown')

        try {
          const uploadRes = await assetApi.uploadPhoto(selectedAsset.asset_id, formData)
          if (uploadRes.photo_path) {
            uploadedPhotoPaths.push(uploadRes.photo_path)
          }
        } catch (err) {
          console.error('Photo upload failed:', err)
        }
      }

      // Now checkin with photo paths
      await assetApi.checkin(selectedAsset.asset_id, {
        condition_on_return: form.condition_on_return,
        notes: form.notes,
        photos: uploadedPhotoPaths
      })

      toast.success(`${selectedAsset.asset_name} checked in successfully`)
      navigate('/assets')
    } catch (error) {
      toast.error(error.message || 'Failed to check in')
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

  const isOverdue = selectedAsset?.expected_return_date && isPast(parseISO(selectedAsset.expected_return_date))

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4 mb-6">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div>
          <h1 className="text-2xl font-bold">Check In Equipment</h1>
          <p className="text-gray-400">Return equipment to inventory</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Equipment Selection */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Package className="w-5 h-5 text-neofox-yellow" />
              Select Equipment
            </h3>
          </div>
          <div className="card-body">
            {selectedAsset ? (
              <div className="space-y-4">
                <div className="flex items-center gap-4 p-4 bg-neofox-darker rounded-lg">
                  <div className="w-16 h-16 bg-neofox-gray rounded-lg flex items-center justify-center">
                    {selectedAsset.photo ? (
                      <img src={selectedAsset.photo} alt="" className="w-full h-full object-cover rounded-lg" />
                    ) : (
                      <Package className="w-8 h-8 text-gray-500" />
                    )}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium">{selectedAsset.asset_name}</p>
                    <p className="text-sm text-neofox-yellow font-mono">{selectedAsset.asset_id}</p>
                    <p className="text-sm text-gray-400">{selectedAsset.category}</p>
                  </div>
                  <button
                    type="button"
                    onClick={() => setSelectedAsset(null)}
                    className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
                  >
                    <X className="w-5 h-5" />
                  </button>
                </div>

                {/* Borrower Info */}
                <div className="p-4 bg-neofox-darker rounded-lg space-y-2">
                  <div className="flex items-center gap-2 text-sm">
                    <User className="w-4 h-4 text-gray-500" />
                    <span className="text-gray-400">Current Holder:</span>
                    <span className="font-medium">{selectedAsset.current_borrower}</span>
                  </div>
                  {selectedAsset.checkout_date && (
                    <div className="flex items-center gap-2 text-sm">
                      <Calendar className="w-4 h-4 text-gray-500" />
                      <span className="text-gray-400">Checked Out:</span>
                      <span>{format(parseISO(selectedAsset.checkout_date), 'MMM d, yyyy')}</span>
                    </div>
                  )}
                  {selectedAsset.expected_return_date && (
                    <div className="flex items-center gap-2 text-sm">
                      <Calendar className="w-4 h-4 text-gray-500" />
                      <span className="text-gray-400">Expected Return:</span>
                      <span className={isOverdue ? 'text-red-400 font-medium' : ''}>
                        {format(parseISO(selectedAsset.expected_return_date), 'MMM d, yyyy')}
                        {isOverdue && ' (Overdue)'}
                      </span>
                    </div>
                  )}
                </div>

                {isOverdue && (
                  <div className="flex items-center gap-3 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                    <AlertCircle className="w-5 h-5 text-red-400" />
                    <div>
                      <p className="font-medium text-red-400">Overdue Return</p>
                      <p className="text-sm text-gray-400">This equipment was due back on {format(parseISO(selectedAsset.expected_return_date), 'MMM d, yyyy')}</p>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <div className="relative">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
                  <input
                    type="text"
                    placeholder="Search by name, ID, or borrower..."
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
                      {filteredAssets.length > 0 ? (
                        filteredAssets.slice(0, 10).map(asset => (
                          <button
                            key={asset.asset_id}
                            type="button"
                            onClick={() => {
                              setSelectedAsset(asset)
                              setShowAssetDropdown(false)
                              setSearchQuery('')
                            }}
                            className="flex items-center gap-3 w-full p-3 hover:bg-neofox-gray transition-colors"
                          >
                            <div className="w-10 h-10 bg-neofox-darker rounded-lg flex items-center justify-center">
                              <Package className="w-5 h-5 text-gray-500" />
                            </div>
                            <div className="text-left flex-1">
                              <p className="font-medium">{asset.asset_name}</p>
                              <p className="text-sm text-gray-400">
                                {asset.asset_id} • With: {asset.current_borrower}
                              </p>
                            </div>
                            {asset.expected_return_date && isPast(parseISO(asset.expected_return_date)) && (
                              <span className="badge badge-lost">Overdue</span>
                            )}
                          </button>
                        ))
                      ) : (
                        <div className="p-4 text-center text-gray-500">
                          No checked out equipment found
                        </div>
                      )}
                    </div>
                  </>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Condition Assessment */}
        {selectedAsset && (
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold">Condition Assessment</h3>
            </div>
            <div className="card-body space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Condition on Return
                </label>
                <div className="grid grid-cols-3 gap-3">
                  {[
                    { value: 'excellent', label: 'Excellent', class: 'border-green-500 bg-green-500/20 text-green-400' },
                    { value: 'good', label: 'Good', class: 'border-blue-500 bg-blue-500/20 text-blue-400' },
                    { value: 'needs_repair', label: 'Needs Repair', class: 'border-orange-500 bg-orange-500/20 text-orange-400' }
                  ].map(opt => (
                    <button
                      key={opt.value}
                      type="button"
                      onClick={() => setForm({ ...form, condition_on_return: opt.value })}
                      className={`p-3 rounded-lg border-2 transition-all ${
                        form.condition_on_return === opt.value
                          ? opt.class
                          : 'border-neofox-gray hover:border-neofox-yellow/50'
                      }`}
                    >
                      {opt.label}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                <textarea
                  value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                  rows={3}
                  className="input-field"
                  placeholder="Any issues, damage, or notes about the equipment"
                />
              </div>
            </div>
          </div>
        )}

        {/* Photo Documentation */}
        {selectedAsset && (
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold flex items-center gap-2">
                <Camera className="w-5 h-5 text-neofox-yellow" />
                Equipment Photos (QC)
              </h3>
            </div>
            <div className="card-body">
              <p className="text-sm text-gray-400 mb-4">
                Take photos of the equipment condition on return for quality control records.
              </p>

              {/* Photo previews */}
              {photosPreviews.length > 0 && (
                <div className="flex flex-wrap gap-3 mb-4">
                  {photosPreviews.map((preview, idx) => (
                    <div key={idx} className="relative group">
                      <img
                        src={preview}
                        alt={`Photo ${idx + 1}`}
                        className="w-24 h-24 object-cover rounded-lg border border-neofox-gray"
                      />
                      <button
                        type="button"
                        onClick={() => removePhoto(idx)}
                        className="absolute -top-2 -right-2 p-1 bg-red-500 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    </div>
                  ))}
                </div>
              )}

              {/* Upload button */}
              {photos.length < 5 && (
                <div>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={handlePhotoAdd}
                    className="hidden"
                    id="photo-upload"
                  />
                  <label
                    htmlFor="photo-upload"
                    className="flex items-center justify-center gap-2 p-4 border-2 border-dashed border-neofox-gray rounded-lg cursor-pointer hover:border-neofox-yellow transition-colors"
                  >
                    <Upload className="w-5 h-5 text-gray-400" />
                    <span className="text-gray-400">Click to upload photos ({5 - photos.length} remaining)</span>
                  </label>
                </div>
              )}

              <p className="text-xs text-gray-500 mt-2">
                Photos are emailed to admin and stored for damage tracking purposes.
              </p>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex items-center justify-between gap-4">
          <button
            type="button"
            onClick={() => navigate('/scanner')}
            className="btn-secondary flex items-center gap-2"
          >
            <QrCode className="w-5 h-5" />
            Scan QR Code
          </button>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => navigate(-1)}
              className="btn-secondary"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting || !selectedAsset}
              className="btn-primary flex items-center gap-2"
            >
              {submitting ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  Processing...
                </>
              ) : (
                <>
                  <LogIn className="w-5 h-5" />
                  Check In
                </>
              )}
            </button>
          </div>
        </div>
      </form>
    </div>
  )
}

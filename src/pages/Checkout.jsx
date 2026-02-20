import { useState, useEffect, useRef } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { assetApi, userApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import { useAuth } from '../context/AuthContext'
import {
  ArrowLeft,
  LogOut,
  Search,
  Package,
  User,
  Calendar,
  FileText,
  Loader2,
  CheckCircle,
  X,
  QrCode,
  Camera,
  Upload,
  Image,
  Trash2,
  MessageCircle,
  Send,
  Briefcase
} from 'lucide-react'
import { format, addDays } from 'date-fns'

export default function Checkout() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const toast = useToast()
  const { user } = useAuth()
  const fileInputRef = useRef(null)

  const [assets, setAssets] = useState([])
  const [borrowers, setBorrowers] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedAsset, setSelectedAsset] = useState(null)
  const [showAssetDropdown, setShowAssetDropdown] = useState(false)
  const [photos, setPhotos] = useState([])
  const [photosPreviews, setPhotosPreviews] = useState([])

  const [form, setForm] = useState({
    borrower_name: '',
    borrower_phone: '',
    expected_return_date: format(addDays(new Date(), 7), 'yyyy-MM-dd'),
    project: '',
    purpose: '',
    notes: ''
  })
  const [sendWhatsApp, setSendWhatsApp] = useState(true)
  const [generateReceipt, setGenerateReceipt] = useState(true)

  useEffect(() => {
    loadData()
  }, [])

  useEffect(() => {
    const assetId = searchParams.get('asset')
    if (assetId && assets.length > 0) {
      const asset = assets.find(a => a.asset_id === assetId)
      if (asset && asset.status === 'available') {
        setSelectedAsset(asset)
      }
    }
  }, [searchParams, assets])

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

  const filteredAssets = assets.filter(asset =>
    !searchQuery ||
    asset.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    asset.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
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

    if (!form.borrower_name.trim()) {
      toast.error('Please enter borrower name')
      return
    }

    if (!form.expected_return_date) {
      toast.error('Please select expected return date')
      return
    }

    setSubmitting(true)
    try {
      // Upload photos first if any
      const uploadedPhotoPaths = []
      for (const photo of photos) {
        const formData = new FormData()
        formData.append('photo', photo)
        formData.append('photo_type', 'checkout')
        formData.append('transaction_type', 'checkout')
        formData.append('borrower_name', form.borrower_name)

        try {
          const uploadRes = await assetApi.uploadPhoto(selectedAsset.asset_id, formData)
          if (uploadRes.photo_path) {
            uploadedPhotoPaths.push(uploadRes.photo_path)
          }
        } catch (err) {
          console.error('Photo upload failed:', err)
        }
      }

      // Now checkout with photo paths
      const result = await assetApi.checkout(selectedAsset.asset_id, {
        borrower_name: form.borrower_name,
        expected_return_date: form.expected_return_date,
        project: form.project,
        purpose: form.purpose,
        notes: form.notes,
        photos: uploadedPhotoPaths
      })

      toast.success(`${selectedAsset.asset_name} checked out successfully`)

      // Send WhatsApp message if enabled and phone number provided
      if (sendWhatsApp && form.borrower_phone) {
        const cleanPhone = form.borrower_phone.replace(/[^0-9]/g, '')
        const message = `Hi ${form.borrower_name},%0A%0AThe following equipment has been checked out to you:%0A%0A*${selectedAsset.asset_name}*%0AID: ${selectedAsset.asset_id}%0ACategory: ${selectedAsset.category}%0AProject: ${form.project || 'Not specified'}%0A%0AExpected Return: ${format(new Date(form.expected_return_date), 'MMMM d, yyyy')}%0APurpose: ${form.purpose || 'Not specified'}%0A%0APlease take care of the equipment and return it on time.%0A%0A- NeoFox Media Equipment Team`

        window.open(`https://wa.me/${cleanPhone}?text=${message}`, '_blank')
      }

      // Navigate to receipt page or assets
      if (generateReceipt) {
        navigate('/checkout/receipt', {
          state: {
            checkoutData: {
              asset: selectedAsset,
              borrower: form.borrower_name,
              returnDate: form.expected_return_date,
              project: form.project,
              purpose: form.purpose,
              notes: form.notes,
              transactionId: result.transaction_id
            }
          }
        })
      } else {
        navigate('/assets')
      }
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
          <h1 className="text-2xl font-bold">Check Out Equipment</h1>
          <p className="text-gray-400">Assign equipment to a team member</p>
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
              <div className="flex items-center gap-4 p-4 bg-neofox-darker rounded-lg">
                <div className="w-16 h-16 bg-neofox-gray rounded-lg flex items-center justify-center overflow-hidden">
                  {selectedAsset.photo ? (
                    <img src={selectedAsset.photo} alt="" className="w-full h-full object-cover" />
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
            ) : (
              <div className="relative">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
                  <input
                    type="text"
                    placeholder="Search equipment by name or ID..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    onFocus={() => setShowAssetDropdown(true)}
                    className="input-field pl-10"
                  />
                </div>
                {showAssetDropdown && (
                  <>
                    <div className="fixed inset-0 z-10" onClick={() => setShowAssetDropdown(false)} />
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
                            <div className="text-left">
                              <p className="font-medium">{asset.asset_name}</p>
                              <p className="text-sm text-gray-400">{asset.asset_id} • {asset.category}</p>
                            </div>
                            <CheckCircle className="w-5 h-5 text-green-400 ml-auto" />
                          </button>
                        ))
                      ) : (
                        <div className="p-4 text-center text-gray-500">No available equipment found</div>
                      )}
                    </div>
                  </>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Borrower Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <User className="w-5 h-5 text-neofox-yellow" />
              Borrower Information
            </h3>
          </div>
          <div className="card-body space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Borrower Name <span className="text-red-400">*</span>
              </label>
              <input
                type="text"
                value={form.borrower_name}
                onChange={(e) => setForm({ ...form, borrower_name: e.target.value })}
                className="input-field"
                placeholder="Enter name or select from team"
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
                Phone Number (WhatsApp)
              </label>
              <div className="flex gap-2">
                <input
                  type="tel"
                  value={form.borrower_phone}
                  onChange={(e) => setForm({ ...form, borrower_phone: e.target.value })}
                  className="input-field flex-1"
                  placeholder="+91 98765 43210"
                />
                <label className="flex items-center gap-2 px-3 py-2 bg-neofox-darker rounded-lg cursor-pointer">
                  <input
                    type="checkbox"
                    checked={sendWhatsApp}
                    onChange={(e) => setSendWhatsApp(e.target.checked)}
                    className="w-4 h-4 rounded border-neofox-gray text-green-500 focus:ring-green-500"
                  />
                  <MessageCircle className="w-4 h-4 text-green-400" />
                  <span className="text-sm text-gray-300">Send</span>
                </label>
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Equipment list will be sent via WhatsApp to this number
              </p>
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

            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                <Briefcase className="w-4 h-4 inline mr-1" />
                Project Name <span className="text-red-400">*</span>
              </label>
              <input
                type="text"
                value={form.project}
                onChange={(e) => setForm({ ...form, project: e.target.value })}
                className="input-field"
                placeholder="e.g., Wedding - Sharma, Corporate Video - TechCorp"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">Purpose / Use</label>
              <input
                type="text"
                value={form.purpose}
                onChange={(e) => setForm({ ...form, purpose: e.target.value })}
                className="input-field"
                placeholder="e.g., Main camera, B-roll, Audio recording"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">Notes</label>
              <textarea
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                rows={2}
                className="input-field"
                placeholder="Any additional notes"
              />
            </div>

            {/* Receipt Option */}
            <div className="pt-2 border-t border-neofox-gray">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={generateReceipt}
                  onChange={(e) => setGenerateReceipt(e.target.checked)}
                  className="w-4 h-4 rounded border-neofox-gray text-neofox-yellow focus:ring-neofox-yellow"
                />
                <div>
                  <span className="text-sm font-medium text-gray-300">Generate Receipt</span>
                  <p className="text-xs text-gray-500">Create a printable PDF receipt with signatures</p>
                </div>
              </label>
            </div>
          </div>
        </div>

        {/* Photo Documentation */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Camera className="w-5 h-5 text-neofox-yellow" />
              Equipment Photos (QC)
            </h3>
          </div>
          <div className="card-body">
            <p className="text-sm text-gray-400 mb-4">
              Take photos of the equipment condition before checkout for quality control records.
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
            <button type="button" onClick={() => navigate(-1)} className="btn-secondary">
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
                  <LogOut className="w-5 h-5" />
                  Check Out
                </>
              )}
            </button>
          </div>
        </div>
      </form>
    </div>
  )
}

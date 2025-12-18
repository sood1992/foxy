import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ArrowLeft,
  Save,
  Upload,
  Package,
  Camera,
  Mic,
  Lightbulb,
  HardDrive,
  X,
  Plus,
  Loader2
} from 'lucide-react'

const categories = [
  { value: 'Camera', icon: Camera },
  { value: 'Lens', icon: Camera },
  { value: 'Audio', icon: Mic },
  { value: 'Lighting', icon: Lightbulb },
  { value: 'Storage', icon: HardDrive },
  { value: 'Monitor', icon: Package },
  { value: 'Tripod', icon: Package },
  { value: 'Cables', icon: Package },
  { value: 'Other', icon: Package }
]

export default function AddAsset() {
  const navigate = useNavigate()
  const toast = useToast()
  const [loading, setLoading] = useState(false)
  const [form, setForm] = useState({
    asset_name: '',
    category: 'Camera',
    description: '',
    serial_number: '',
    condition_status: 'excellent',
    storage_location: '',
    shelf: '',
    notes: ''
  })
  const [photos, setPhotos] = useState([])
  const [photoPreview, setPhotoPreview] = useState(null)

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
  }

  const handlePhotoChange = (e) => {
    const file = e.target.files[0]
    if (file) {
      setPhotos([file])
      const reader = new FileReader()
      reader.onloadend = () => {
        setPhotoPreview(reader.result)
      }
      reader.readAsDataURL(file)
    }
  }

  const removePhoto = () => {
    setPhotos([])
    setPhotoPreview(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!form.asset_name.trim()) {
      toast.error('Please enter an equipment name')
      return
    }

    setLoading(true)
    try {
      const response = await assetApi.create(form)

      // Upload photo if exists
      if (photos.length > 0 && response.asset_id) {
        const formData = new FormData()
        formData.append('photo', photos[0])
        formData.append('photo_type', 'main')
        await assetApi.uploadPhoto(response.asset_id, formData)
      }

      toast.success('Equipment added successfully')
      navigate(`/assets/${response.asset_id}`)
    } catch (error) {
      toast.error(error.message || 'Failed to add equipment')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4 mb-6">
        <button
          onClick={() => navigate('/assets')}
          className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div>
          <h1 className="text-2xl font-bold">Add Equipment</h1>
          <p className="text-gray-400">Add new equipment to your inventory</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Photo Upload */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold">Equipment Photo</h3>
          </div>
          <div className="card-body">
            {photoPreview ? (
              <div className="relative inline-block">
                <img
                  src={photoPreview}
                  alt="Preview"
                  className="w-64 h-48 object-cover rounded-lg"
                />
                <button
                  type="button"
                  onClick={removePhoto}
                  className="absolute -top-2 -right-2 p-1 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            ) : (
              <label className="flex flex-col items-center justify-center w-64 h-48 border-2 border-dashed border-neofox-gray rounded-lg cursor-pointer hover:border-neofox-yellow transition-colors">
                <Upload className="w-10 h-10 text-gray-500 mb-2" />
                <span className="text-sm text-gray-400">Click to upload photo</span>
                <span className="text-xs text-gray-500 mt-1">PNG, JPG up to 10MB</span>
                <input
                  type="file"
                  accept="image/*"
                  onChange={handlePhotoChange}
                  className="hidden"
                />
              </label>
            )}
          </div>
        </div>

        {/* Basic Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold">Basic Information</h3>
          </div>
          <div className="card-body">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Equipment Name <span className="text-red-400">*</span>
                </label>
                <input
                  type="text"
                  name="asset_name"
                  value={form.asset_name}
                  onChange={handleChange}
                  className="input-field"
                  placeholder="e.g., Sony A7 III"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Category</label>
                <select
                  name="category"
                  value={form.category}
                  onChange={handleChange}
                  className="select-field"
                >
                  {categories.map(cat => (
                    <option key={cat.value} value={cat.value}>{cat.value}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Serial Number</label>
                <input
                  type="text"
                  name="serial_number"
                  value={form.serial_number}
                  onChange={handleChange}
                  className="input-field"
                  placeholder="Enter serial number"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Condition</label>
                <select
                  name="condition_status"
                  value={form.condition_status}
                  onChange={handleChange}
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
                  name="storage_location"
                  value={form.storage_location}
                  onChange={handleChange}
                  className="input-field"
                  placeholder="e.g., Room A, Warehouse 1"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Shelf / Area</label>
                <input
                  type="text"
                  name="shelf"
                  value={form.shelf}
                  onChange={handleChange}
                  className="input-field"
                  placeholder="e.g., Shelf 3, Cabinet B"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-300 mb-2">Description</label>
                <textarea
                  name="description"
                  value={form.description}
                  onChange={handleChange}
                  rows={3}
                  className="input-field"
                  placeholder="Optional description or specifications"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                <textarea
                  name="notes"
                  value={form.notes}
                  onChange={handleChange}
                  rows={2}
                  className="input-field"
                  placeholder="Any additional notes"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Submit */}
        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate('/assets')}
            className="btn-secondary"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className="btn-primary flex items-center gap-2"
          >
            {loading ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                Adding...
              </>
            ) : (
              <>
                <Plus className="w-5 h-5" />
                Add Equipment
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  )
}

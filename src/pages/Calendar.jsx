import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ChevronLeft,
  ChevronRight,
  Calendar as CalendarIcon,
  Plus,
  Loader2,
  Package,
  User,
  Clock,
  X,
  Check,
  AlertCircle
} from 'lucide-react'
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isSameMonth, isSameDay, addMonths, subMonths, parseISO, isWithinInterval, startOfDay, addDays } from 'date-fns'

export default function Calendar() {
  const navigate = useNavigate()
  const toast = useToast()

  const [currentMonth, setCurrentMonth] = useState(new Date())
  const [reservations, setReservations] = useState([])
  const [assets, setAssets] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedDate, setSelectedDate] = useState(null)
  const [showCreateModal, setShowCreateModal] = useState(false)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [reservationsRes, assetsRes] = await Promise.all([
        assetApi.getReservations(),
        assetApi.getAll()
      ])
      setReservations(reservationsRes.reservations || [])
      setAssets(assetsRes.assets || [])
    } catch (error) {
      toast.error('Failed to load data')
    } finally {
      setLoading(false)
    }
  }

  const monthStart = startOfMonth(currentMonth)
  const monthEnd = endOfMonth(currentMonth)
  const days = eachDayOfInterval({ start: monthStart, end: monthEnd })

  // Pad days to start from Sunday
  const startDay = monthStart.getDay()
  const paddedDays = [...Array(startDay).fill(null), ...days]

  const getReservationsForDate = (date) => {
    if (!date) return []
    return reservations.filter(res => {
      const start = parseISO(res.start_date)
      const end = parseISO(res.end_date)
      return isWithinInterval(date, { start: startOfDay(start), end: startOfDay(end) })
    })
  }

  const handleCancelReservation = async (reservationId) => {
    if (!confirm('Cancel this reservation?')) return

    try {
      await assetApi.cancelReservation(reservationId)
      toast.success('Reservation cancelled')
      loadData()
    } catch (error) {
      toast.error('Failed to cancel reservation')
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
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Equipment Calendar</h1>
          <p className="text-gray-400">Reserve equipment in advance</p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="btn-primary flex items-center gap-2"
        >
          <Plus className="w-5 h-5" />
          New Reservation
        </button>
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Calendar */}
        <div className="lg:col-span-2 card">
          <div className="card-header flex items-center justify-between">
            <div className="flex items-center gap-4">
              <button
                onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}
                className="p-2 hover:bg-neofox-gray rounded-lg"
              >
                <ChevronLeft className="w-5 h-5" />
              </button>
              <h2 className="text-lg font-semibold">
                {format(currentMonth, 'MMMM yyyy')}
              </h2>
              <button
                onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}
                className="p-2 hover:bg-neofox-gray rounded-lg"
              >
                <ChevronRight className="w-5 h-5" />
              </button>
            </div>
            <button
              onClick={() => setCurrentMonth(new Date())}
              className="text-sm text-neofox-yellow hover:underline"
            >
              Today
            </button>
          </div>

          <div className="card-body">
            {/* Day Headers */}
            <div className="grid grid-cols-7 gap-1 mb-2">
              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                <div key={day} className="text-center text-sm text-gray-500 py-2">
                  {day}
                </div>
              ))}
            </div>

            {/* Calendar Grid */}
            <div className="grid grid-cols-7 gap-1">
              {paddedDays.map((day, idx) => {
                if (!day) {
                  return <div key={`empty-${idx}`} className="h-24 bg-neofox-darker/50 rounded-lg" />
                }

                const dayReservations = getReservationsForDate(day)
                const isToday = isSameDay(day, new Date())
                const isSelected = selectedDate && isSameDay(day, selectedDate)

                return (
                  <button
                    key={day.toISOString()}
                    onClick={() => setSelectedDate(day)}
                    className={`h-24 p-2 rounded-lg text-left transition-colors ${
                      isSelected ? 'ring-2 ring-neofox-yellow' :
                      isToday ? 'bg-neofox-yellow/10' :
                      'bg-neofox-darker hover:bg-neofox-gray'
                    }`}
                  >
                    <span className={`text-sm font-medium ${
                      isToday ? 'text-neofox-yellow' : 'text-gray-300'
                    }`}>
                      {format(day, 'd')}
                    </span>
                    <div className="mt-1 space-y-1">
                      {dayReservations.slice(0, 2).map(res => (
                        <div
                          key={res.id}
                          className={`text-xs px-1 py-0.5 rounded truncate ${
                            res.status === 'confirmed' ? 'bg-green-500/20 text-green-400' :
                            res.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                            'bg-gray-500/20 text-gray-400'
                          }`}
                        >
                          {res.asset_name || 'Reserved'}
                        </div>
                      ))}
                      {dayReservations.length > 2 && (
                        <div className="text-xs text-gray-500">
                          +{dayReservations.length - 2} more
                        </div>
                      )}
                    </div>
                  </button>
                )
              })}
            </div>
          </div>
        </div>

        {/* Selected Date Details */}
        <div className="card">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <CalendarIcon className="w-5 h-5 text-neofox-yellow" />
              {selectedDate ? format(selectedDate, 'EEEE, MMMM d') : 'Select a Date'}
            </h3>
          </div>
          <div className="card-body">
            {selectedDate ? (
              <>
                {getReservationsForDate(selectedDate).length > 0 ? (
                  <div className="space-y-3">
                    {getReservationsForDate(selectedDate).map(res => {
                      const asset = assets.find(a => a.asset_id === res.asset_id)
                      return (
                        <div key={res.id} className="p-3 bg-neofox-darker rounded-lg">
                          <div className="flex items-start justify-between">
                            <div>
                              <p className="font-medium">{asset?.asset_name || res.asset_id}</p>
                              <div className="flex items-center gap-2 mt-1 text-sm text-gray-400">
                                <User className="w-3 h-3" />
                                {res.reserved_by}
                              </div>
                              <div className="flex items-center gap-2 mt-1 text-sm text-gray-400">
                                <Clock className="w-3 h-3" />
                                {format(parseISO(res.start_date), 'MMM d')} - {format(parseISO(res.end_date), 'MMM d')}
                              </div>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className={`badge text-xs ${
                                res.status === 'confirmed' ? 'badge-available' :
                                res.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                                'badge-maintenance'
                              }`}>
                                {res.status}
                              </span>
                              {res.status !== 'cancelled' && (
                                <button
                                  onClick={() => handleCancelReservation(res.id)}
                                  className="p-1 hover:bg-red-500/20 rounded text-red-400"
                                >
                                  <X className="w-4 h-4" />
                                </button>
                              )}
                            </div>
                          </div>
                          {res.purpose && (
                            <p className="mt-2 text-sm text-gray-400 border-t border-neofox-gray pt-2">
                              {res.purpose}
                            </p>
                          )}
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <CalendarIcon className="w-8 h-8 mx-auto mb-2 opacity-50" />
                    <p>No reservations for this date</p>
                    <button
                      onClick={() => setShowCreateModal(true)}
                      className="mt-3 text-sm text-neofox-yellow hover:underline"
                    >
                      Create reservation
                    </button>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-8 text-gray-500">
                <CalendarIcon className="w-8 h-8 mx-auto mb-2 opacity-50" />
                <p>Click on a date to see reservations</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Upcoming Reservations */}
      <div className="card">
        <div className="card-header">
          <h3 className="font-semibold">Upcoming Reservations</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-neofox-gray">
                <th className="text-left p-4 text-sm font-medium text-gray-400">Equipment</th>
                <th className="text-left p-4 text-sm font-medium text-gray-400">Reserved By</th>
                <th className="text-left p-4 text-sm font-medium text-gray-400">Dates</th>
                <th className="text-left p-4 text-sm font-medium text-gray-400">Purpose</th>
                <th className="text-left p-4 text-sm font-medium text-gray-400">Status</th>
                <th className="text-left p-4 text-sm font-medium text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody>
              {reservations
                .filter(r => r.status !== 'cancelled' && parseISO(r.end_date) >= new Date())
                .sort((a, b) => parseISO(a.start_date) - parseISO(b.start_date))
                .slice(0, 10)
                .map(res => {
                  const asset = assets.find(a => a.asset_id === res.asset_id)
                  return (
                    <tr key={res.id} className="border-b border-neofox-gray hover:bg-neofox-darker">
                      <td className="p-4">
                        <div className="flex items-center gap-3">
                          <Package className="w-4 h-4 text-gray-500" />
                          <div>
                            <p className="font-medium">{asset?.asset_name || res.asset_id}</p>
                            <p className="text-xs text-gray-500">{res.asset_id}</p>
                          </div>
                        </div>
                      </td>
                      <td className="p-4 text-gray-300">{res.reserved_by}</td>
                      <td className="p-4 text-gray-300">
                        {format(parseISO(res.start_date), 'MMM d')} - {format(parseISO(res.end_date), 'MMM d, yyyy')}
                      </td>
                      <td className="p-4 text-gray-400 max-w-xs truncate">{res.purpose || '-'}</td>
                      <td className="p-4">
                        <span className={`badge ${
                          res.status === 'confirmed' ? 'badge-available' :
                          res.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                          'badge-maintenance'
                        }`}>
                          {res.status}
                        </span>
                      </td>
                      <td className="p-4">
                        <div className="flex items-center gap-2">
                          {res.status === 'confirmed' && (
                            <button
                              onClick={() => navigate(`/checkout?asset=${res.asset_id}`)}
                              className="text-xs text-neofox-yellow hover:underline"
                            >
                              Checkout
                            </button>
                          )}
                          <button
                            onClick={() => handleCancelReservation(res.id)}
                            className="text-xs text-red-400 hover:underline"
                          >
                            Cancel
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
            </tbody>
          </table>
          {reservations.filter(r => r.status !== 'cancelled').length === 0 && (
            <div className="text-center py-8 text-gray-500">
              No upcoming reservations
            </div>
          )}
        </div>
      </div>

      {/* Create Reservation Modal */}
      {showCreateModal && (
        <ReservationModal
          assets={assets}
          reservations={reservations}
          selectedDate={selectedDate}
          onClose={() => setShowCreateModal(false)}
          onSave={() => {
            setShowCreateModal(false)
            loadData()
          }}
        />
      )}
    </div>
  )
}

function ReservationModal({ assets, reservations, selectedDate, onClose, onSave }) {
  const toast = useToast()
  const [saving, setSaving] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [form, setForm] = useState({
    asset_id: '',
    reserved_by: '',
    start_date: selectedDate ? format(selectedDate, 'yyyy-MM-dd') : format(new Date(), 'yyyy-MM-dd'),
    end_date: selectedDate ? format(addDays(selectedDate, 1), 'yyyy-MM-dd') : format(addDays(new Date(), 1), 'yyyy-MM-dd'),
    purpose: ''
  })

  const availableAssets = assets.filter(a =>
    a.status === 'available' && (
      !searchQuery ||
      a.asset_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      a.asset_id.toLowerCase().includes(searchQuery.toLowerCase())
    )
  )

  const checkConflict = () => {
    if (!form.asset_id || !form.start_date || !form.end_date) return false
    const start = parseISO(form.start_date)
    const end = parseISO(form.end_date)

    return reservations.some(res => {
      if (res.asset_id !== form.asset_id || res.status === 'cancelled') return false
      const resStart = parseISO(res.start_date)
      const resEnd = parseISO(res.end_date)
      return (start <= resEnd && end >= resStart)
    })
  }

  const hasConflict = checkConflict()

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.asset_id) {
      toast.error('Please select equipment')
      return
    }
    if (!form.reserved_by.trim()) {
      toast.error('Please enter who is reserving')
      return
    }
    if (hasConflict) {
      toast.error('This equipment is already reserved for these dates')
      return
    }

    setSaving(true)
    try {
      await assetApi.createReservation(form)
      toast.success('Reservation created successfully')
      onSave()
    } catch (error) {
      toast.error(error.message || 'Failed to create reservation')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-neofox-dark border border-neofox-gray rounded-xl w-full max-w-lg">
        <div className="p-6 border-b border-neofox-gray">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">New Reservation</h2>
            <button onClick={onClose} className="p-2 hover:bg-neofox-gray rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">Equipment *</label>
            <select
              value={form.asset_id}
              onChange={(e) => setForm({ ...form, asset_id: e.target.value })}
              className="input-field"
            >
              <option value="">Select equipment...</option>
              {availableAssets.map(asset => (
                <option key={asset.asset_id} value={asset.asset_id}>
                  {asset.asset_name} ({asset.asset_id})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">Reserved By *</label>
            <input
              type="text"
              value={form.reserved_by}
              onChange={(e) => setForm({ ...form, reserved_by: e.target.value })}
              className="input-field"
              placeholder="Team member name"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">Start Date *</label>
              <input
                type="date"
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                className="input-field"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">End Date *</label>
              <input
                type="date"
                value={form.end_date}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                className="input-field"
                min={form.start_date}
              />
            </div>
          </div>

          {hasConflict && (
            <div className="flex items-center gap-2 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
              <AlertCircle className="w-4 h-4" />
              This equipment is already reserved for these dates
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">Purpose</label>
            <textarea
              value={form.purpose}
              onChange={(e) => setForm({ ...form, purpose: e.target.value })}
              className="input-field"
              rows={2}
              placeholder="What will this be used for?"
            />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={onClose} className="btn-secondary">
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving || hasConflict}
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
                  Create Reservation
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

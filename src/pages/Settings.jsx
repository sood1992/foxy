import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import {
  Settings as SettingsIcon,
  User,
  Mail,
  Lock,
  Eye,
  EyeOff,
  Save,
  Bell,
  Palette,
  Database,
  Download,
  Upload,
  RefreshCw
} from 'lucide-react'

export default function Settings() {
  const { user } = useAuth()
  const toast = useToast()
  const [showPassword, setShowPassword] = useState(false)
  const [saving, setSaving] = useState(false)

  const [profile, setProfile] = useState({
    email: user?.email || '',
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  })

  const handleSaveProfile = async (e) => {
    e.preventDefault()
    if (profile.newPassword && profile.newPassword !== profile.confirmPassword) {
      toast.error('Passwords do not match')
      return
    }

    setSaving(true)
    try {
      // API call would go here
      await new Promise(resolve => setTimeout(resolve, 500))
      toast.success('Profile updated successfully')
      setProfile(prev => ({ ...prev, currentPassword: '', newPassword: '', confirmPassword: '' }))
    } catch (error) {
      toast.error('Failed to update profile')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold">Settings</h1>
        <p className="text-gray-400">Manage your account and preferences</p>
      </div>

      {/* Profile Settings */}
      <div className="card">
        <div className="card-header">
          <h2 className="font-semibold flex items-center gap-2">
            <User className="w-5 h-5 text-neofox-yellow" />
            Profile
          </h2>
        </div>
        <div className="card-body">
          <form onSubmit={handleSaveProfile} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Username</label>
                <input
                  type="text"
                  value={user?.username || ''}
                  disabled
                  className="input-field bg-neofox-darker/50 cursor-not-allowed"
                />
                <p className="text-xs text-gray-500 mt-1">Username cannot be changed</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Email</label>
                <input
                  type="email"
                  value={profile.email}
                  onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                  className="input-field"
                  placeholder="your@email.com"
                />
              </div>
            </div>

            <div className="border-t border-neofox-gray pt-4 mt-4">
              <h3 className="font-medium mb-4 flex items-center gap-2">
                <Lock className="w-4 h-4" />
                Change Password
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Current Password</label>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={profile.currentPassword}
                    onChange={(e) => setProfile({ ...profile, currentPassword: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={profile.newPassword}
                    onChange={(e) => setProfile({ ...profile, newPassword: e.target.value })}
                    className="input-field"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={profile.confirmPassword}
                    onChange={(e) => setProfile({ ...profile, confirmPassword: e.target.value })}
                    className="input-field"
                  />
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="text-sm text-gray-400 hover:text-white mt-2 flex items-center gap-1"
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                {showPassword ? 'Hide passwords' : 'Show passwords'}
              </button>
            </div>

            <div className="flex justify-end pt-4">
              <button type="submit" disabled={saving} className="btn-primary flex items-center gap-2">
                <Save className="w-5 h-5" />
                {saving ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
          </form>
        </div>
      </div>

      {/* Notifications */}
      <div className="card">
        <div className="card-header">
          <h2 className="font-semibold flex items-center gap-2">
            <Bell className="w-5 h-5 text-neofox-yellow" />
            Notifications
          </h2>
        </div>
        <div className="card-body space-y-4">
          {[
            { label: 'Checkout Notifications', desc: 'Get notified when equipment is checked out' },
            { label: 'Check-in Notifications', desc: 'Get notified when equipment is returned' },
            { label: 'Overdue Alerts', desc: 'Receive alerts for overdue equipment' },
            { label: 'Maintenance Reminders', desc: 'Get reminders for scheduled maintenance' }
          ].map((item, idx) => (
            <div key={idx} className="flex items-center justify-between p-4 bg-neofox-darker rounded-lg">
              <div>
                <p className="font-medium">{item.label}</p>
                <p className="text-sm text-gray-400">{item.desc}</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" defaultChecked className="sr-only peer" />
                <div className="w-11 h-6 bg-neofox-gray peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-neofox-yellow"></div>
              </label>
            </div>
          ))}
        </div>
      </div>

      {/* Data Management */}
      <div className="card">
        <div className="card-header">
          <h2 className="font-semibold flex items-center gap-2">
            <Database className="w-5 h-5 text-neofox-yellow" />
            Data Management
          </h2>
        </div>
        <div className="card-body">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button className="btn-secondary flex items-center justify-center gap-2 py-4">
              <Download className="w-5 h-5" />
              Export All Data
            </button>
            <button className="btn-secondary flex items-center justify-center gap-2 py-4">
              <Upload className="w-5 h-5" />
              Import Data
            </button>
          </div>
          <p className="text-sm text-gray-500 mt-4">
            Export your inventory data for backup or import data from a previous backup.
          </p>
        </div>
      </div>

      {/* About */}
      <div className="card">
        <div className="card-header">
          <h2 className="font-semibold">About FOXY</h2>
        </div>
        <div className="card-body">
          <div className="flex items-center gap-4 mb-4">
            <div className="w-16 h-16 bg-neofox-yellow rounded-xl flex items-center justify-center">
              <span className="text-2xl font-bold text-black">F</span>
            </div>
            <div>
              <h3 className="text-xl font-bold text-neofox-yellow">FOXY</h3>
              <p className="text-gray-400">NeoFox Media Inventory System</p>
              <p className="text-sm text-gray-500">Version 2.0.0</p>
            </div>
          </div>
          <p className="text-sm text-gray-400">
            A modern equipment inventory management system built for media production teams.
            Manage your cameras, lenses, audio gear, and more with ease.
          </p>
          <div className="mt-4 pt-4 border-t border-neofox-gray">
            <p className="text-sm text-gray-500">
              Built with React + TailwindCSS + PHP
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

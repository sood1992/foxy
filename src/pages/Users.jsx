import { useState, useEffect } from 'react'
import { userApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import { useAuth } from '../context/AuthContext'
import {
  Users as UsersIcon,
  Plus,
  Edit,
  Trash2,
  Shield,
  User,
  Mail,
  Loader2,
  X,
  Eye,
  EyeOff
} from 'lucide-react'
import { format, parseISO } from 'date-fns'

const roleConfig = {
  admin: { label: 'Admin', class: 'bg-neofox-yellow/20 text-neofox-yellow' },
  team_member: { label: 'Team Member', class: 'bg-blue-500/20 text-blue-400' },
  guest: { label: 'Guest', class: 'bg-gray-500/20 text-gray-400' }
}

export default function Users() {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editingUser, setEditingUser] = useState(null)
  const [showPassword, setShowPassword] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [deleteConfirm, setDeleteConfirm] = useState(null)
  const toast = useToast()
  const { user: currentUser, isAdmin } = useAuth()

  const [form, setForm] = useState({
    username: '',
    email: '',
    password: '',
    role: 'team_member'
  })

  useEffect(() => {
    loadUsers()
  }, [])

  const loadUsers = async () => {
    try {
      const response = await userApi.getAll()
      setUsers(response.users || [])
    } catch (error) {
      toast.error('Failed to load users')
    } finally {
      setLoading(false)
    }
  }

  const openCreateModal = () => {
    setEditingUser(null)
    setForm({ username: '', email: '', password: '', role: 'team_member' })
    setShowModal(true)
  }

  const openEditModal = (user) => {
    setEditingUser(user)
    setForm({
      username: user.username,
      email: user.email || '',
      password: '',
      role: user.role
    })
    setShowModal(true)
  }

  const handleSubmit = async () => {
    if (!form.username.trim()) {
      toast.error('Username is required')
      return
    }
    if (!editingUser && !form.password) {
      toast.error('Password is required for new users')
      return
    }

    setSubmitting(true)
    try {
      if (editingUser) {
        await userApi.update(editingUser.id, form)
        toast.success('User updated successfully')
      } else {
        await userApi.create(form)
        toast.success('User created successfully')
      }
      setShowModal(false)
      loadUsers()
    } catch (error) {
      toast.error(error.message || 'Failed to save user')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (user) => {
    if (user.id === currentUser?.id) {
      toast.error('Cannot delete your own account')
      return
    }

    try {
      await userApi.delete(user.id)
      toast.success('User deleted')
      setDeleteConfirm(null)
      loadUsers()
    } catch (error) {
      toast.error('Failed to delete user')
    }
  }

  if (!isAdmin()) {
    return (
      <div className="card p-12 text-center">
        <Shield className="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 className="text-lg font-medium mb-2">Access Denied</h3>
        <p className="text-gray-500">You need admin privileges to access this page.</p>
      </div>
    )
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
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Users</h1>
          <p className="text-gray-400">{users.length} team member{users.length !== 1 ? 's' : ''}</p>
        </div>
        <button onClick={openCreateModal} className="btn-primary flex items-center gap-2">
          <Plus className="w-5 h-5" />
          Add User
        </button>
      </div>

      {/* Users Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {users.map(user => (
          <div key={user.id} className="card p-5 hover:border-neofox-yellow/30 transition-colors">
            <div className="flex items-start justify-between mb-4">
              <div className="w-12 h-12 bg-neofox-yellow/20 rounded-full flex items-center justify-center">
                <User className="w-6 h-6 text-neofox-yellow" />
              </div>
              <span className={`badge ${roleConfig[user.role]?.class || ''}`}>
                {roleConfig[user.role]?.label || user.role}
              </span>
            </div>
            <h3 className="font-semibold text-lg">{user.username}</h3>
            {user.email && (
              <p className="text-sm text-gray-400 flex items-center gap-1 mt-1">
                <Mail className="w-4 h-4" />
                {user.email}
              </p>
            )}
            <p className="text-xs text-gray-500 mt-2">
              Created {user.created_at ? format(parseISO(user.created_at), 'MMM d, yyyy') : '—'}
            </p>
            <div className="flex items-center gap-2 mt-4 pt-4 border-t border-neofox-gray">
              <button
                onClick={() => openEditModal(user)}
                className="flex-1 btn-secondary py-2 text-sm flex items-center justify-center gap-1"
              >
                <Edit className="w-4 h-4" />
                Edit
              </button>
              {user.id !== currentUser?.id && (
                <button
                  onClick={() => setDeleteConfirm(user)}
                  className="p-2 text-red-400 hover:bg-red-500/20 rounded-lg transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Create/Edit Modal */}
      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div className="modal-content max-w-md" onClick={e => e.stopPropagation()}>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">
                {editingUser ? 'Edit User' : 'Create User'}
              </h3>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Username</label>
                  <input
                    type="text"
                    value={form.username}
                    onChange={(e) => setForm({ ...form, username: e.target.value })}
                    className="input-field"
                    placeholder="Enter username"
                    disabled={editingUser}
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Email</label>
                  <input
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                    className="input-field"
                    placeholder="user@example.com"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">
                    Password {editingUser && '(leave blank to keep current)'}
                  </label>
                  <div className="relative">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      value={form.password}
                      onChange={(e) => setForm({ ...form, password: e.target.value })}
                      className="input-field pr-10"
                      placeholder={editingUser ? 'Leave blank to keep current' : 'Enter password'}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                    >
                      {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                    </button>
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Role</label>
                  <select
                    value={form.role}
                    onChange={(e) => setForm({ ...form, role: e.target.value })}
                    className="select-field"
                  >
                    <option value="team_member">Team Member</option>
                    <option value="admin">Admin</option>
                    <option value="guest">Guest</option>
                  </select>
                </div>
              </div>

              <div className="flex gap-3 mt-6">
                <button onClick={() => setShowModal(false)} className="btn-secondary flex-1">
                  Cancel
                </button>
                <button onClick={handleSubmit} disabled={submitting} className="btn-primary flex-1">
                  {submitting ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : editingUser ? 'Update' : 'Create'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation */}
      {deleteConfirm && (
        <div className="modal-overlay" onClick={() => setDeleteConfirm(null)}>
          <div className="modal-content max-w-md" onClick={e => e.stopPropagation()}>
            <div className="p-6 text-center">
              <div className="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Trash2 className="w-6 h-6 text-red-400" />
              </div>
              <h3 className="text-lg font-semibold mb-2">Delete User?</h3>
              <p className="text-gray-400 mb-6">
                Are you sure you want to delete <strong>{deleteConfirm.username}</strong>?
              </p>
              <div className="flex gap-3">
                <button onClick={() => setDeleteConfirm(null)} className="btn-secondary flex-1">
                  Cancel
                </button>
                <button onClick={() => handleDelete(deleteConfirm)} className="btn-danger flex-1">
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

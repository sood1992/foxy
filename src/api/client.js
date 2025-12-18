const API_BASE = import.meta.env.PROD ? './api' : '/api'

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl
  }

  getHeaders() {
    const headers = {
      'Content-Type': 'application/json',
    }
    const token = localStorage.getItem('foxy_token')
    if (token) {
      headers['Authorization'] = `Bearer ${token}`
    }
    return headers
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`
    const config = {
      ...options,
      headers: {
        ...this.getHeaders(),
        ...options.headers,
      },
    }

    try {
      const response = await fetch(url, config)
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error || 'API request failed')
      }

      return data
    } catch (error) {
      console.error('API Error:', error)
      throw error
    }
  }

  get(endpoint) {
    return this.request(endpoint, { method: 'GET' })
  }

  post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  }

  put(endpoint, data) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
  }

  delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' })
  }

  async upload(endpoint, formData) {
    const url = `${this.baseUrl}${endpoint}`
    const headers = {}
    const token = localStorage.getItem('foxy_token')
    if (token) {
      headers['Authorization'] = `Bearer ${token}`
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: formData,
      })
      return await response.json()
    } catch (error) {
      console.error('Upload Error:', error)
      throw error
    }
  }
}

export const api = new ApiClient(API_BASE)

// Asset API
export const assetApi = {
  getAll: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return api.get(`/assets${query ? `?${query}` : ''}`)
  },
  getById: (id) => api.get(`/assets/${id}`),
  create: (data) => api.post('/assets', data),
  update: (id, data) => api.put(`/assets/${id}`, data),
  delete: (id) => api.delete(`/assets/${id}`),
  checkout: (id, data) => api.post(`/assets/${id}/checkout`, data),
  checkin: (id, data) => api.post(`/assets/${id}/checkin`, data),
  bulkCheckout: (data) => api.post('/assets/bulk-checkout', data),
  uploadPhoto: (id, formData) => api.upload(`/assets/${id}/photos`, formData),
  getCategories: () => api.get('/assets/categories'),
  search: (query) => api.get(`/assets/search?q=${encodeURIComponent(query)}`),
  getUsers: () => api.get('/users'),

  // Kits
  getKits: () => api.get('/kits'),
  getKit: (id) => api.get(`/kits/${id}`),
  createKit: (data) => api.post('/kits', data),
  updateKit: (id, data) => api.put(`/kits/${id}`, data),
  deleteKit: (id) => api.delete(`/kits/${id}`),

  // Reservations
  getReservations: () => api.get('/reservations'),
  createReservation: (data) => api.post('/reservations', data),
  approveReservation: (id) => api.post(`/reservations/${id}/approve`),
  rejectReservation: (id, data) => api.post(`/reservations/${id}/reject`, data),
  cancelReservation: (id) => api.delete(`/reservations/${id}`),

  // Audit Log
  getAuditLog: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return api.get(`/audit${query ? `?${query}` : ''}`)
  },
}

// Transaction API
export const transactionApi = {
  getAll: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return api.get(`/transactions${query ? `?${query}` : ''}`)
  },
  getByAsset: (assetId) => api.get(`/transactions/asset/${assetId}`),
}

// User API
export const userApi = {
  getAll: () => api.get('/users'),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  getBorrowers: () => api.get('/users/borrowers'),
}

// Request API
export const requestApi = {
  getAll: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return api.get(`/requests${query ? `?${query}` : ''}`)
  },
  create: (data) => api.post('/requests', data),
  approve: (id, data) => api.post(`/requests/${id}/approve`, data),
  reject: (id, data) => api.post(`/requests/${id}/reject`, data),
}

// Maintenance API
export const maintenanceApi = {
  getSchedule: () => api.get('/maintenance/schedule'),
  getIssues: () => api.get('/maintenance/issues'),
  createIssue: (data) => api.post('/maintenance/issues', data),
  updateIssue: (id, data) => api.put(`/maintenance/issues/${id}`, data),
  scheduleTask: (data) => api.post('/maintenance/schedule', data),
  completeTask: (id, data) => api.post(`/maintenance/schedule/${id}/complete`, data),
}

// Dashboard/Stats API
export const statsApi = {
  getDashboard: () => api.get('/stats/dashboard'),
  getReports: (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return api.get(`/stats/reports${query ? `?${query}` : ''}`)
  },
}

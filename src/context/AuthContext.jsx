import { createContext, useContext, useState, useEffect } from 'react'
import { api } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    checkAuth()
  }, [])

  const checkAuth = async () => {
    try {
      const storedUser = localStorage.getItem('foxy_user')
      if (storedUser) {
        const userData = JSON.parse(storedUser)
        const response = await api.get('/auth/verify')
        if (response.success) {
          setUser(userData)
        } else {
          localStorage.removeItem('foxy_user')
          localStorage.removeItem('foxy_token')
        }
      }
    } catch (error) {
      localStorage.removeItem('foxy_user')
      localStorage.removeItem('foxy_token')
    } finally {
      setLoading(false)
    }
  }

  const login = async (username, password) => {
    try {
      const response = await api.post('/auth/login', { username, password })
      if (response.success) {
        setUser(response.user)
        localStorage.setItem('foxy_user', JSON.stringify(response.user))
        localStorage.setItem('foxy_token', response.token)
        return { success: true }
      }
      return { success: false, error: response.error || 'Login failed' }
    } catch (error) {
      return { success: false, error: 'Login failed. Please try again.' }
    }
  }

  const logout = () => {
    setUser(null)
    localStorage.removeItem('foxy_user')
    localStorage.removeItem('foxy_token')
  }

  const isAdmin = () => user?.role === 'admin'

  return (
    <AuthContext.Provider value={{ user, login, logout, loading, isAdmin }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { Html5QrcodeScanner, Html5QrcodeScanType } from 'html5-qrcode'
import { assetApi } from '../api/client'
import { useToast } from '../context/ToastContext'
import {
  ArrowLeft,
  QrCode,
  Camera,
  Package,
  LogOut,
  LogIn,
  Eye,
  RefreshCw,
  AlertCircle,
  CheckCircle,
  Clock,
  Wrench
} from 'lucide-react'
import { format, parseISO } from 'date-fns'

const statusConfig = {
  available: { label: 'Available', class: 'bg-green-500/20 text-green-400', icon: CheckCircle, action: 'checkout' },
  checked_out: { label: 'Checked Out', class: 'bg-yellow-500/20 text-yellow-400', icon: Clock, action: 'checkin' },
  maintenance: { label: 'Maintenance', class: 'bg-orange-500/20 text-orange-400', icon: Wrench, action: 'view' },
  lost: { label: 'Lost', class: 'bg-red-500/20 text-red-400', icon: AlertCircle, action: 'view' }
}

export default function Scanner() {
  const navigate = useNavigate()
  const toast = useToast()
  const scannerRef = useRef(null)
  const [scanning, setScanning] = useState(true)
  const [scannedAsset, setScannedAsset] = useState(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (scanning && !scannedAsset) {
      initScanner()
    }

    return () => {
      if (scannerRef.current) {
        scannerRef.current.clear().catch(() => {})
      }
    }
  }, [scanning, scannedAsset])

  const initScanner = () => {
    const scanner = new Html5QrcodeScanner(
      'qr-reader',
      {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
        rememberLastUsedCamera: true,
        aspectRatio: 1
      },
      false
    )

    scanner.render(onScanSuccess, onScanError)
    scannerRef.current = scanner
  }

  const onScanSuccess = async (decodedText) => {
    // Extract asset ID from QR code (could be just ID or URL containing ID)
    let assetId = decodedText

    // If it's a URL, extract the asset ID
    if (decodedText.includes('asset=')) {
      const url = new URL(decodedText)
      assetId = url.searchParams.get('asset')
    } else if (decodedText.includes('/')) {
      // Might be a path like /assets/CAM001
      const parts = decodedText.split('/')
      assetId = parts[parts.length - 1]
    }

    // Clear scanner
    if (scannerRef.current) {
      await scannerRef.current.clear().catch(() => {})
    }

    setScanning(false)
    setLoading(true)

    try {
      const response = await assetApi.getById(assetId)
      setScannedAsset(response.asset)
    } catch (error) {
      toast.error('Equipment not found')
      resetScanner()
    } finally {
      setLoading(false)
    }
  }

  const onScanError = (error) => {
    // Silently ignore scan errors (usually just "no QR found in frame")
  }

  const resetScanner = () => {
    setScannedAsset(null)
    setScanning(true)
  }

  const handleAction = (action) => {
    if (!scannedAsset) return

    switch (action) {
      case 'checkout':
        navigate(`/checkout?asset=${scannedAsset.asset_id}`)
        break
      case 'checkin':
        navigate(`/checkin?asset=${scannedAsset.asset_id}`)
        break
      case 'view':
        navigate(`/assets/${scannedAsset.asset_id}`)
        break
    }
  }

  const statusInfo = scannedAsset ? statusConfig[scannedAsset.status] : null
  const StatusIcon = statusInfo?.icon

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
          <h1 className="text-2xl font-bold">QR Scanner</h1>
          <p className="text-gray-400">Scan equipment QR codes</p>
        </div>
      </div>

      {scannedAsset ? (
        /* Scanned Asset View */
        <div className="space-y-6">
          <div className="card">
            <div className="card-header flex items-center justify-between">
              <h3 className="font-semibold">Scanned Equipment</h3>
              <button
                onClick={resetScanner}
                className="btn-secondary text-sm py-1.5 px-3 flex items-center gap-2"
              >
                <RefreshCw className="w-4 h-4" />
                Scan Another
              </button>
            </div>
            <div className="card-body">
              <div className="flex items-start gap-4">
                <div className="w-24 h-24 bg-neofox-darker rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                  {scannedAsset.photo ? (
                    <img src={scannedAsset.photo} alt="" className="w-full h-full object-cover" />
                  ) : (
                    <Package className="w-12 h-12 text-gray-500" />
                  )}
                </div>
                <div className="flex-1">
                  <p className="text-sm text-neofox-yellow font-mono">{scannedAsset.asset_id}</p>
                  <h2 className="text-xl font-bold mt-1">{scannedAsset.asset_name}</h2>
                  <p className="text-gray-400">{scannedAsset.category}</p>
                  <div className={`inline-flex items-center gap-2 mt-3 px-3 py-1.5 rounded-lg ${statusInfo?.class}`}>
                    {StatusIcon && <StatusIcon className="w-4 h-4" />}
                    <span className="font-medium">{statusInfo?.label}</span>
                  </div>
                </div>
              </div>

              {scannedAsset.status === 'checked_out' && (
                <div className="mt-4 p-4 bg-neofox-darker rounded-lg space-y-2">
                  <p className="text-sm">
                    <span className="text-gray-500">Current Holder:</span>{' '}
                    <span className="font-medium">{scannedAsset.current_borrower}</span>
                  </p>
                  {scannedAsset.expected_return_date && (
                    <p className="text-sm">
                      <span className="text-gray-500">Expected Return:</span>{' '}
                      <span className="font-medium">{format(parseISO(scannedAsset.expected_return_date), 'MMM d, yyyy')}</span>
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Actions */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {scannedAsset.status === 'available' && (
              <button
                onClick={() => handleAction('checkout')}
                className="btn-primary flex items-center justify-center gap-2 py-4"
              >
                <LogOut className="w-5 h-5" />
                Check Out
              </button>
            )}
            {scannedAsset.status === 'checked_out' && (
              <button
                onClick={() => handleAction('checkin')}
                className="btn-primary flex items-center justify-center gap-2 py-4"
              >
                <LogIn className="w-5 h-5" />
                Check In
              </button>
            )}
            <button
              onClick={() => handleAction('view')}
              className="btn-secondary flex items-center justify-center gap-2 py-4"
            >
              <Eye className="w-5 h-5" />
              View Details
            </button>
          </div>
        </div>
      ) : (
        /* Scanner View */
        <div className="card overflow-hidden">
          <div className="card-header">
            <h3 className="font-semibold flex items-center gap-2">
              <Camera className="w-5 h-5 text-neofox-yellow" />
              Point camera at QR code
            </h3>
          </div>
          <div className="card-body p-0">
            {loading ? (
              <div className="aspect-square flex items-center justify-center bg-neofox-darker">
                <div className="text-center">
                  <div className="w-12 h-12 border-4 border-neofox-yellow border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                  <p className="text-gray-400">Loading equipment...</p>
                </div>
              </div>
            ) : (
              <div id="qr-reader" className="w-full"></div>
            )}
          </div>
          <div className="p-4 bg-neofox-darker border-t border-neofox-gray">
            <p className="text-sm text-gray-400 text-center">
              Position the QR code within the frame. The scanner will automatically detect it.
            </p>
          </div>
        </div>
      )}

      {/* Instructions */}
      <div className="mt-6 p-4 bg-neofox-dark rounded-lg">
        <h4 className="font-medium mb-2">Quick Tips</h4>
        <ul className="text-sm text-gray-400 space-y-1">
          <li>• Ensure good lighting for best results</li>
          <li>• Hold the device steady</li>
          <li>• QR code should fill most of the frame</li>
          <li>• Works with all FOXY-generated QR codes</li>
        </ul>
      </div>
    </div>
  )
}

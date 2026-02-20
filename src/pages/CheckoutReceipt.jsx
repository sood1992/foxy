import { useState, useRef, useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useToast } from '../context/ToastContext'
import {
  FileText,
  Download,
  Printer,
  Check,
  X,
  Eraser,
  Package,
  User,
  Calendar,
  MapPin,
  Briefcase,
  ArrowLeft
} from 'lucide-react'
import { format } from 'date-fns'

export default function CheckoutReceipt() {
  const location = useLocation()
  const navigate = useNavigate()
  const toast = useToast()

  const checkoutData = location.state?.checkoutData
  const [signatures, setSignatures] = useState({
    manager: null,
    borrower: null
  })
  const [activeSignature, setActiveSignature] = useState(null)

  useEffect(() => {
    if (!checkoutData) {
      navigate('/checkout')
    }
  }, [checkoutData, navigate])

  const handlePrint = () => {
    window.print()
  }

  const handleDownloadPDF = () => {
    // Trigger print dialog which can save as PDF
    window.print()
    toast.success('Use "Save as PDF" in the print dialog')
  }

  const handleComplete = () => {
    toast.success('Checkout completed successfully!')
    navigate('/assets')
  }

  if (!checkoutData) {
    return null
  }

  const { asset, borrower, returnDate, purpose, project, notes, transactionId } = checkoutData

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header - Hidden in print */}
      <div className="flex items-center justify-between mb-6 print:hidden">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate(-1)}
            className="p-2 hover:bg-neofox-gray rounded-lg transition-colors"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className="text-2xl font-bold">Checkout Receipt</h1>
            <p className="text-gray-400">Review and sign to complete checkout</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={handlePrint} className="btn-secondary flex items-center gap-2">
            <Printer className="w-4 h-4" />
            Print
          </button>
          <button onClick={handleDownloadPDF} className="btn-primary flex items-center gap-2">
            <Download className="w-4 h-4" />
            Save PDF
          </button>
        </div>
      </div>

      {/* Receipt Document */}
      <div className="bg-white text-black rounded-xl overflow-hidden print:rounded-none print:shadow-none" id="receipt">
        {/* Receipt Header */}
        <div className="bg-gradient-to-r from-gray-900 to-gray-800 text-white p-8 print:bg-black">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-yellow-400">FOXY</h1>
              <p className="text-gray-300 text-sm">NeoFox Media Equipment Management</p>
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold">EQUIPMENT CHECKOUT</p>
              <p className="text-gray-300">Receipt #{transactionId || 'TXN-' + Date.now()}</p>
            </div>
          </div>
        </div>

        {/* Receipt Body */}
        <div className="p-8 space-y-8">
          {/* Transaction Info */}
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Transaction Details</h3>
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Calendar className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-600">Date:</span>
                  <span className="font-medium">{format(new Date(), 'MMMM d, yyyy h:mm a')}</span>
                </div>
                <div className="flex items-center gap-2">
                  <Calendar className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-600">Expected Return:</span>
                  <span className="font-medium">{format(new Date(returnDate), 'MMMM d, yyyy')}</span>
                </div>
                {project && (
                  <div className="flex items-center gap-2">
                    <Briefcase className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-600">Project:</span>
                    <span className="font-medium">{project}</span>
                  </div>
                )}
              </div>
            </div>
            <div>
              <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Borrower Information</h3>
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <User className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-600">Name:</span>
                  <span className="font-medium">{borrower}</span>
                </div>
                {purpose && (
                  <div className="flex items-center gap-2">
                    <FileText className="w-4 h-4 text-gray-400" />
                    <span className="text-gray-600">Purpose:</span>
                    <span className="font-medium">{purpose}</span>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Equipment Table */}
          <div>
            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Equipment Details</h3>
            <table className="w-full border border-gray-200 rounded-lg overflow-hidden">
              <thead className="bg-gray-100">
                <tr>
                  <th className="text-left p-3 text-sm font-semibold text-gray-600">Asset ID</th>
                  <th className="text-left p-3 text-sm font-semibold text-gray-600">Equipment Name</th>
                  <th className="text-left p-3 text-sm font-semibold text-gray-600">Category</th>
                  <th className="text-left p-3 text-sm font-semibold text-gray-600">Serial Number</th>
                  <th className="text-left p-3 text-sm font-semibold text-gray-600">Location</th>
                </tr>
              </thead>
              <tbody>
                {(Array.isArray(asset) ? asset : [asset]).map((item, idx) => (
                  <tr key={idx} className="border-t border-gray-200">
                    <td className="p-3 font-mono text-sm">{item.asset_id}</td>
                    <td className="p-3 font-medium">{item.asset_name}</td>
                    <td className="p-3 text-gray-600">{item.category}</td>
                    <td className="p-3 font-mono text-sm text-gray-600">{item.serial_number || '-'}</td>
                    <td className="p-3 text-gray-600">{item.location || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Notes */}
          {notes && (
            <div>
              <h3 className="text-sm font-semibold text-gray-500 uppercase mb-2">Notes</h3>
              <p className="text-gray-600 bg-gray-50 p-3 rounded-lg">{notes}</p>
            </div>
          )}

          {/* Terms */}
          <div className="bg-gray-50 p-4 rounded-lg text-sm text-gray-600">
            <h4 className="font-semibold text-gray-700 mb-2">Terms & Conditions</h4>
            <ul className="list-disc list-inside space-y-1">
              <li>Equipment must be returned by the expected return date</li>
              <li>Any damage must be reported immediately</li>
              <li>Equipment is for authorized use only</li>
              <li>Borrower is responsible for the equipment until returned</li>
            </ul>
          </div>

          {/* Signatures */}
          <div className="grid md:grid-cols-2 gap-8 pt-4">
            <div>
              <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Equipment Manager Signature</h3>
              <SignaturePad
                signature={signatures.manager}
                onSave={(sig) => setSignatures({ ...signatures, manager: sig })}
                onClear={() => setSignatures({ ...signatures, manager: null })}
                label="Equipment Manager"
              />
            </div>
            <div>
              <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Borrower Signature</h3>
              <SignaturePad
                signature={signatures.borrower}
                onSave={(sig) => setSignatures({ ...signatures, borrower: sig })}
                onClear={() => setSignatures({ ...signatures, borrower: null })}
                label="Borrower"
              />
            </div>
          </div>

          {/* Footer */}
          <div className="text-center text-sm text-gray-500 pt-4 border-t border-gray-200">
            <p>NeoFox Media • Equipment Management System</p>
            <p>Generated on {format(new Date(), 'MMMM d, yyyy h:mm:ss a')}</p>
          </div>
        </div>
      </div>

      {/* Complete Button - Hidden in print */}
      <div className="mt-6 flex justify-end gap-3 print:hidden">
        <button onClick={() => navigate('/checkout')} className="btn-secondary">
          New Checkout
        </button>
        <button
          onClick={handleComplete}
          disabled={!signatures.manager || !signatures.borrower}
          className="btn-primary flex items-center gap-2 disabled:opacity-50"
        >
          <Check className="w-4 h-4" />
          Complete & Save
        </button>
      </div>

      {/* Print Styles */}
      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #receipt, #receipt * {
            visibility: visible;
          }
          #receipt {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
          }
          .print\\:hidden {
            display: none !important;
          }
        }
      `}</style>
    </div>
  )
}

function SignaturePad({ signature, onSave, onClear, label }) {
  const canvasRef = useRef(null)
  const [isDrawing, setIsDrawing] = useState(false)
  const [hasDrawn, setHasDrawn] = useState(false)

  useEffect(() => {
    const canvas = canvasRef.current
    if (canvas && signature) {
      const ctx = canvas.getContext('2d')
      const img = new Image()
      img.onload = () => {
        ctx.drawImage(img, 0, 0)
      }
      img.src = signature
    }
  }, [signature])

  const startDrawing = (e) => {
    const canvas = canvasRef.current
    const ctx = canvas.getContext('2d')
    const rect = canvas.getBoundingClientRect()
    const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left
    const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top

    ctx.beginPath()
    ctx.moveTo(x, y)
    setIsDrawing(true)
    setHasDrawn(true)
  }

  const draw = (e) => {
    if (!isDrawing) return
    const canvas = canvasRef.current
    const ctx = canvas.getContext('2d')
    const rect = canvas.getBoundingClientRect()
    const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left
    const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top

    ctx.lineWidth = 2
    ctx.lineCap = 'round'
    ctx.strokeStyle = '#000'
    ctx.lineTo(x, y)
    ctx.stroke()
  }

  const stopDrawing = () => {
    setIsDrawing(false)
  }

  const clear = () => {
    const canvas = canvasRef.current
    const ctx = canvas.getContext('2d')
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    setHasDrawn(false)
    onClear()
  }

  const save = () => {
    const canvas = canvasRef.current
    const dataUrl = canvas.toDataURL('image/png')
    onSave(dataUrl)
  }

  return (
    <div className="print:border-0">
      {signature ? (
        <div className="relative">
          <img src={signature} alt={label} className="w-full h-32 border border-gray-300 rounded-lg bg-white" />
          <button
            onClick={onClear}
            className="absolute top-2 right-2 p-1 bg-red-100 text-red-600 rounded hover:bg-red-200 print:hidden"
          >
            <X className="w-4 h-4" />
          </button>
          <p className="text-center text-sm text-gray-500 mt-2">{label}</p>
        </div>
      ) : (
        <div className="space-y-2 print:hidden">
          <canvas
            ref={canvasRef}
            width={300}
            height={120}
            className="w-full h-32 border-2 border-dashed border-gray-300 rounded-lg bg-white cursor-crosshair touch-none"
            onMouseDown={startDrawing}
            onMouseMove={draw}
            onMouseUp={stopDrawing}
            onMouseLeave={stopDrawing}
            onTouchStart={startDrawing}
            onTouchMove={draw}
            onTouchEnd={stopDrawing}
          />
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-500">Sign above</p>
            <div className="flex gap-2">
              <button
                onClick={clear}
                className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
              >
                <Eraser className="w-3 h-3" />
                Clear
              </button>
              <button
                onClick={save}
                disabled={!hasDrawn}
                className="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1 disabled:opacity-50"
              >
                <Check className="w-3 h-3" />
                Confirm
              </button>
            </div>
          </div>
        </div>
      )}
      {!signature && (
        <div className="hidden print:block border-b-2 border-black h-16 mt-4">
          <p className="text-sm text-gray-500 mt-2">{label}</p>
        </div>
      )}
    </div>
  )
}

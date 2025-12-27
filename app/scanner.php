<?php
// scanner.php - QR Code Scanner Interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-qrcode"></i> QR Code Scanner</h4>
            </div>
            <div class="card-body">
                <div id="qr-reader" style="width: 100%; max-width: 600px; margin: 0 auto;"></div>
                <div id="qr-reader-results" class="mt-3"></div>
                
                <div class="mt-3">
                    <button id="start-scan" class="btn btn-success me-2">Start Scanner</button>
                    <button id="stop-scan" class="btn btn-danger" style="display: none;">Stop Scanner</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;

        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById('qr-reader-results').innerHTML = `
                <div class="alert alert-success">
                    <strong>QR Code Detected:</strong><br>
                    <a href="${decodedText}" class="btn btn-primary mt-2" target="_blank">Open Link</a>
                </div>`;
            
            // Auto-redirect if it's a checkout/checkin URL
            if (decodedText.includes('checkout.php') || decodedText.includes('checkin.php')) {
                window.location.href = decodedText;
            }
        }

        function onScanFailure(error) {
            // Handle scan failure - usually silent
        }

        document.getElementById('start-scan').addEventListener('click', function() {
            if (!isScanning) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader", 
                    { 
                        fps: 10, 
                        qrbox: {width: 250, height: 250},
                        rememberLastUsedCamera: true
                    }
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                
                document.getElementById('start-scan').style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
                isScanning = true;
            }
        });

        document.getElementById('stop-scan').addEventListener('click', function() {
            if (isScanning && html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                document.getElementById('start-scan').style.display = 'inline-block';
                document.getElementById('stop-scan').style.display = 'none';
                isScanning = false;
            }
        });
    </script>
</body>
</html>
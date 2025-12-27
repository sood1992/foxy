// Ultra-compact 15mm QR code functions - optimized for maximum scannability

// Get current domain for short URLs
function getCurrentDomain() {
    return window.location.hostname;
}

// Generate ultra-compact QR codes using shortest possible URLs
function generateUltraCompactQR(assetId, strategy = 'minimal') {
    const baseUrl = "https://quickchart.io/qr";
    
    let text;
    switch (strategy) {
        case 'asset_only':
            // Strategy 1: Just asset ID (shortest possible)
            text = assetId;
            break;
        case 'minimal':
        default:
            // Strategy 2: Minimal URL (domain + asset ID)
            text = `https://${getCurrentDomain()}/${assetId}`;
            break;
    }
    
    // Optimized parameters for 15mm labels
    const params = new URLSearchParams({
        text: text,
        size: 350,        // High resolution for small print
        format: 'png',
        ecc: 'M',         // Medium error correction (optimal for short URLs)
        margin: 1,        // Minimal margin
        qzone: 0         // No quiet zone padding
    });
    
    return `${baseUrl}?${params.toString()}`;
}

// Extract asset ID from existing table data
function getAssetIdFromRow(row) {
    const assetIdElement = row.querySelector('.asset-id');
    return assetIdElement ? assetIdElement.textContent.trim() : null;
}

// Show strategy selection modal
function showQRStrategyModal(callback) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); z-index: 10000;
        display: flex; align-items: center; justify-content: center;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%;">
            <h3 style="margin: 0 0 1rem 0; color: #333;">📱 Choose QR Code Strategy for 15mm Labels</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">Select the simplest QR code type for best scanning:</p>
            
            <div style="display: grid; gap: 1rem; margin-bottom: 2rem;">
                <button class="strategy-option" data-strategy="asset_only" style="
                    padding: 1rem; border: 2px solid #FFD700; border-radius: 8px;
                    background: #FFF9C4; cursor: pointer; text-align: left;
                ">
                    <div style="font-weight: bold; color: #333;">🎯 Asset ID Only (Simplest)</div>
                    <div style="font-size: 0.9rem; color: #666;">QR contains just: CAM-001</div>
                    <div style="font-size: 0.8rem; color: #4CAF50; margin-top: 4px;">✅ Highest scan success rate</div>
                </button>
                
                <button class="strategy-option" data-strategy="minimal" style="
                    padding: 1rem; border: 2px solid #ddd; border-radius: 8px;
                    background: white; cursor: pointer; text-align: left;
                ">
                    <div style="font-weight: bold; color: #333;">🔗 Minimal URL</div>
                    <div style="font-size: 0.9rem; color: #666;">QR contains: ${getCurrentDomain()}/CAM-001</div>
                    <div style="font-size: 0.8rem; color: #2196F3; margin-top: 4px;">✅ Works with any QR scanner</div>
                </button>
            </div>
            
            <div style="background: #e8f5e8; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                <div style="font-size: 0.9rem; color: #2e7d32;">
                    <strong>💡 Recommendation:</strong> Start with "Asset ID Only" for maximum scannability. 
                    Your scanning app will handle adding the domain automatically.
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button onclick="this.closest('div').parentElement.remove()" style="
                    padding: 0.75rem 1.5rem; border: 1px solid #ddd;
                    border-radius: 6px; background: white; cursor: pointer;
                ">Cancel</button>
            </div>
        </div>
    `;
    
    // Add click handlers
    modal.querySelectorAll('.strategy-option').forEach(button => {
        button.addEventListener('mouseenter', () => {
            if (!button.style.background.includes('#FFF9C4')) {
                button.style.background = '#f8f9fa';
                button.style.borderColor = '#ccc';
            }
        });
        
        button.addEventListener('mouseleave', () => {
            if (!button.style.background.includes('#FFF9C4')) {
                button.style.background = 'white';
                button.style.borderColor = '#ddd';
            }
        });
        
        button.addEventListener('click', () => {
            const strategy = button.dataset.strategy;
            modal.remove();
            callback(strategy);
        });
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
    
    document.body.appendChild(modal);
}

// Updated bulk download for 15mm labels
function bulkDownloadQR() {
    console.log('Starting ultra-compact 15mm QR download');
    
    const table = $('#assetsTable').DataTable();
    
    if (table.rows({ search: 'applied' }).count() === 0) {
        alert('No assets found to download QR codes for.');
        return;
    }
    
    showQRStrategyModal((strategy) => {
        const assetData = [];
        table.rows({ search: 'applied' }).every(function() {
            const row = this.node();
            const assetName = row.querySelector('.asset-name')?.textContent.trim();
            const assetId = getAssetIdFromRow(row);
            const category = row.querySelector('td:nth-child(3) span')?.textContent.trim();
            
            if (assetName && assetId) {
                assetData.push({
                    name: assetName,
                    id: assetId,
                    category: category || 'N/A',
                    qrUrl: generateUltraCompactQR(assetId, strategy),
                    strategy: strategy
                });
            }
        });
        
        if (assetData.length === 0) {
            alert('No valid assets found for QR generation.');
            return;
        }
        
        generate15mmQRPDF(assetData, `15mm-${strategy}-labels`);
    });
}

// Updated selected assets download
function bulkDownloadSelectedQR() {
    console.log('Starting selected 15mm QR download');
    
    const selectedCheckboxes = document.querySelectorAll('.asset-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select assets to download QR codes for.');
        return;
    }
    
    showQRStrategyModal((strategy) => {
        const assetData = [];
        selectedCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const assetName = row.querySelector('.asset-name')?.textContent.trim();
            const assetId = getAssetIdFromRow(row);
            const category = row.querySelector('td:nth-child(3) span')?.textContent.trim();
            
            if (assetName && assetId) {
                assetData.push({
                    name: assetName,
                    id: assetId,
                    category: category || 'N/A',
                    qrUrl: generateUltraCompactQR(assetId, strategy),
                    strategy: strategy
                });
            }
        });
        
        if (assetData.length === 0) {
            alert('No valid selected assets found for QR generation.');
            return;
        }
        
        generate15mmQRPDF(assetData, `15mm-${strategy}-selected`);
    });
}

// Generate optimized 15mm PDF layout
function generate15mmQRPDF(assetData, filename) {
    const strategy = assetData[0]?.strategy || 'minimal';
    
    console.log(`Generating 15mm ${strategy} labels for ${assetData.length} assets`);
    
    // Show loading
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'qr-loading';
    loadingDiv.style.cssText = `
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: white; padding: 2rem; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10001;
        text-align: center; min-width: 350px;
    `;
    loadingDiv.innerHTML = `
        <div style="margin-bottom: 1rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #FFD700;"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem 0; color: #333;">Generating Ultra-Compact 15mm Labels</h3>
        <p style="margin: 0; color: #666;">Creating ${strategy === 'asset_only' ? 'simplest possible' : 'minimal URL'} QR codes...</p>
        <div style="margin-top: 1rem; background: #f0f0f0; border-radius: 4px; height: 8px; overflow: hidden;">
            <div id="progress-bar" style="background: #FFD700; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
        </div>
    `;
    document.body.appendChild(loadingDiv);
    
    // Create print window with 15mm optimized layout
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    
    printWindow.document.open();
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ultra-Compact 15mm QR Labels - ${filename}</title>
            <meta charset="UTF-8">
            <style>
                @media print {
                    @page { margin: 0.2in; size: A4; }
                    .print-button { display: none !important; }
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 8px;
                    background: white;
                }
                .print-button {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background: #FFD700;
                    color: #333;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-weight: bold;
                    cursor: pointer;
                    z-index: 1000;
                    font-size: 11px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 8px;
                }
                .header h1 {
                    color: #333;
                    margin: 0 0 4px 0;
                    font-size: 14px;
                }
                .header p {
                    color: #666;
                    margin: 0;
                    font-size: 9px;
                }
                .instructions {
                    background: #e8f5e8;
                    border: 1px solid #4caf50;
                    border-radius: 4px;
                    padding: 8px;
                    margin-bottom: 12px;
                    font-size: 8px;
                    color: #2e7d32;
                }
                .qr-grid {
                    display: grid;
                    grid-template-columns: repeat(12, 1fr);  /* 12 columns for 15mm labels */
                    gap: 1mm;
                    max-width: 100%;
                }
                .qr-item {
                    border: 2px solid #000;
                    border-radius: 2px;
                    padding: 0.5mm;
                    text-align: center;
                    background: white;
                    page-break-inside: avoid;
                    box-sizing: border-box;
                    width: 17mm;       /* 15mm QR + border */
                    height: 19mm;      /* Compact height */
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-start;
                    align-items: center;
                }
                .qr-image {
                    width: 15mm;       /* Exactly 15mm */
                    height: 15mm;
                    margin: 0 auto 0.5mm auto;
                    display: block;
                    border: 1px solid #333;
                }
                .asset-name {
                    font-weight: bold;
                    font-size: 4px;
                    margin-bottom: 0.2mm;
                    word-wrap: break-word;
                    line-height: 1;
                    width: 100%;
                    text-align: center;
                    overflow: hidden;
                    max-height: 0.8em;
                }
                .asset-id {
                    font-family: 'Courier New', monospace;
                    font-size: 3.5px;
                    color: #333;
                    font-weight: bold;
                    width: 100%;
                    text-align: center;
                }
                .page-break { page-break-before: always; }
                .strategy-info {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 4px;
                    padding: 6px;
                    margin-bottom: 10px;
                    font-size: 8px;
                    color: #856404;
                }
            </style>
        </head>
        <body>
            <button class="print-button" onclick="window.print()">
                🖨️ Print 15mm Labels
            </button>
            <div class="header">
                <h1>📱 Ultra-Compact 15mm QR Labels</h1>
                <p>Strategy: ${strategy.toUpperCase()} • Generated: ${new Date().toLocaleDateString()} • ${assetData.length} Labels</p>
            </div>
            <div class="strategy-info">
                <strong>QR Strategy:</strong> ${strategy === 'asset_only' ? 'Asset ID Only (simplest)' : 'Minimal URL'} • 
                <strong>Scanning:</strong> Hold phone 3-4 inches away with good lighting
            </div>
            <div class="instructions">
                <strong>📋 15mm Label Instructions:</strong> Cut along border lines • Apply to clean, flat surface • 
                Scan from 3-4 inches with steady hands • Good lighting essential for small QR codes
            </div>
            <div class="qr-grid" id="qr-grid">
                <!-- Ultra-compact 15mm QR codes will be inserted here -->
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    
    const qrGrid = printWindow.document.getElementById('qr-grid');
    let loadedCount = 0;
    
    function updateProgress() {
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            const progress = (loadedCount / assetData.length) * 100;
            progressBar.style.width = progress + '%';
        }
    }
    
    // 12 columns × ~30 rows = ~360 labels per page
    const itemsPerPage = 360;
    
    assetData.forEach((asset, index) => {
        const qrItem = printWindow.document.createElement('div');
        qrItem.className = 'qr-item';
        
        // Page break
        if (index > 0 && index % itemsPerPage === 0) {
            qrItem.classList.add('page-break');
        }
        
        const qrImage = printWindow.document.createElement('img');
        qrImage.className = 'qr-image';
        qrImage.src = asset.qrUrl;
        
        qrImage.onload = function() {
            loadedCount++;
            updateProgress();
            
            if (loadedCount === assetData.length) {
                setTimeout(() => {
                    const loading = document.getElementById('qr-loading');
                    if (loading) loading.remove();
                    
                    printWindow.focus();
                    console.log(`Ultra-compact 15mm ${strategy} labels ready!`);
                }, 500);
            }
        };
        
        qrImage.onerror = function() {
            console.error(`Failed to load ultra-compact QR for ${asset.name}`);
            loadedCount++;
            updateProgress();
        };
        
        const assetName = printWindow.document.createElement('div');
        assetName.className = 'asset-name';
        // Very short names for 15mm labels
        assetName.textContent = asset.name.length > 8 ? asset.name.substring(0, 8) + '.' : asset.name;
        
        const assetId = printWindow.document.createElement('div');
        assetId.className = 'asset-id';
        assetId.textContent = asset.id;
        
        qrItem.appendChild(qrImage);
        qrItem.appendChild(assetName);
        qrItem.appendChild(assetId);
        
        qrGrid.appendChild(qrItem);
    });
}

// Updated single QR print for 15mm
function printQR() {
    if (!currentQRData.qrUrl) {
        console.error('No QR data available');
        return;
    }
    
    showQRStrategyModal((strategy) => {
        const assetId = currentQRData.assetId;
        const ultraCompactQRUrl = generateUltraCompactQR(assetId, strategy);
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>15mm Label - ${currentQRData.assetName}</title>
                    <style>
                        @page { margin: 0.5in; }
                        @media print { .print-button { display: none !important; } }
                        body { 
                            text-align: center; 
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            background: white;
                        }
                        .print-button {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: #FFD700;
                            color: #333;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-weight: bold;
                            cursor: pointer;
                            z-index: 1000;
                        }
                        .label-container {
                            margin: 20px auto;
                            max-width: 120px;
                            border: 3px solid #000;
                            padding: 6px;
                            border-radius: 4px;
                            background: white;
                        }
                        .qr-image { 
                            width: 15mm;
                            height: 15mm;
                            border: 2px solid #000;
                            margin: 4px 0;
                            display: block;
                        }
                        .asset-info {
                            margin: 4px 0;
                            padding: 4px;
                            background: #f5f5f5;
                            border-radius: 2px;
                        }
                        h3 { 
                            color: #333; 
                            margin-bottom: 3px;
                            font-size: 10px;
                            word-wrap: break-word;
                        }
                        p { 
                            margin: 1px 0; 
                            color: #666;
                            font-size: 8px;
                            font-weight: 600;
                        }
                        .size-info {
                            margin-top: 8px;
                            padding: 6px;
                            background: #e3f2fd;
                            border-radius: 3px;
                            font-size: 8px;
                            color: #1976d2;
                        }
                    </style>
                </head>
                <body>
                    <button class="print-button" onclick="window.print()">
                        🖨️ Print 15mm Label
                    </button>
                    <div class="label-container">
                        <h3>${currentQRData.assetName}</h3>
                        <img class="qr-image" src="${ultraCompactQRUrl}" alt="Ultra-Compact QR Code">
                        <div class="asset-info">
                            <p><strong>ID:</strong> ${currentQRData.assetId}</p>
                        </div>
                        <div class="size-info">
                            <strong>15MM ULTRA-COMPACT</strong><br>
                            ${strategy === 'asset_only' ? 'Asset ID Only' : 'Minimal URL'} Strategy
                        </div>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.focus();
        }, 500);
    });
}
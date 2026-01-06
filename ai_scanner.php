<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Equipment Scanner - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --black-primary: #000000;
            --white-primary: #FFFFFF;
            --green-accent: #4CAF50;
            --blue-accent: #2196F3;
            --red-accent: #FF5722;
            --gray-light: #F5F5F5;
            --orange-accent: #FF9800;
            --purple-accent: #9C27B0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            min-height: 100vh;
        }

        .navbar {
            background: rgba(0, 0, 0, 0.95);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--neofox-yellow);
            text-decoration: none;
        }

        .navbar-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--white-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 0.8rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--neofox-yellow);
            color: var(--black-primary);
        }

        .main-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, var(--black-primary), var(--purple-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 2rem;
        }

        /* Mode Toggle */
        .mode-toggle {
            display: flex;
            background: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 8px 8px 0px var(--black-primary);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .mode-btn {
            flex: 1;
            padding: 1rem 2rem;
            background: none;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            font-size: 1rem;
        }

        .mode-btn.active {
            background: var(--neofox-yellow);
            color: var(--black-primary);
        }

        .mode-btn:not(.active) {
            color: var(--black-primary);
        }

        .mode-btn:not(.active):hover {
            background: var(--gray-light);
        }

        /* Scanner Cards */
        .scanner-card {
            background: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 8px 8px 0px var(--black-primary);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .scanner-card:hover {
            transform: translateY(-4px) translateX(-2px);
            box-shadow: 12px 12px 0px var(--black-primary);
        }

        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .card-icon {
            width: 80px;
            height: 80px;
            background: var(--black-primary);
            color: var(--neofox-yellow);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: #666;
            font-size: 1rem;
        }

        /* Camera Feed */
        .camera-container {
            position: relative;
            width: 100%;
            height: 400px;
            border: 3px dashed var(--black-primary);
            border-radius: 16px;
            overflow: hidden;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .camera-placeholder {
            text-align: center;
            color: #666;
        }

        #cameraFeed, #bulkCameraFeed {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .capture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scanning-frame {
            width: 80%;
            height: 70%;
            max-width: 350px;
            max-height: 280px;
            border: 3px solid var(--neofox-yellow);
            border-radius: 12px;
            position: relative;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
            background: rgba(255, 215, 0, 0.05);
        }

        .scanning-frame::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-radius: 15px;
            animation: pulse 2s infinite;
        }

        .scanning-frame::after {
            content: 'Center equipment here';
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: var(--neofox-yellow);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.01); }
        }

        .corner-marker {
            position: absolute;
            width: 25px;
            height: 25px;
            border: 4px solid var(--neofox-yellow);
        }

        .corner-marker.top-left {
            top: -4px;
            left: -4px;
            border-right: none;
            border-bottom: none;
        }

        .corner-marker.top-right {
            top: -4px;
            right: -4px;
            border-left: none;
            border-bottom: none;
        }

        .corner-marker.bottom-left {
            bottom: -4px;
            left: -4px;
            border-right: none;
            border-top: none;
        }

        .corner-marker.bottom-right {
            bottom: -4px;
            right: -4px;
            border-left: none;
            border-top: none;
        }

        /* Camera quality indicator */
        .camera-info {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: var(--neofox-yellow);
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            pointer-events: none;
        }

        /* Upload Area */
        .upload-area {
            border: 3px dashed var(--black-primary);
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: var(--gray-light);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .upload-area.dragover {
            border-color: var(--blue-accent);
            background: #e3f2fd;
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--blue-accent);
        }

        .upload-text {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.9rem;
            color: #666;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: 3px solid var(--black-primary);
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            font-size: 1rem;
            background: none;
            font-family: inherit;
            min-width: 150px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px) translateX(-2px);
            box-shadow: 6px 6px 0px var(--black-primary);
        }

        .btn-success {
            background: var(--green-accent);
            color: var(--white-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn-danger {
            background: var(--red-accent);
            color: var(--white-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn-secondary {
            background: var(--white-primary);
            color: var(--black-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Status Indicator */
        .status-indicator {
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--white-primary);
            border: 2px solid var(--black-primary);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 4px 4px 0px var(--black-primary);
            z-index: 1000;
            display: none;
            min-width: 250px;
        }

        .status-indicator.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }

        .status-processing { background: var(--orange-accent); }
        .status-success { background: var(--green-accent); }
        .status-error { background: var(--red-accent); }

        .status-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-detail {
            font-size: 0.8rem;
            color: #666;
        }

        /* Results */
        .results-container {
            margin-top: 2rem;
        }

        .result-card {
            background: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 8px 8px 0px var(--black-primary);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .detected-item {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--black-primary);
        }

        .confidence-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid var(--black-primary);
        }

        .confidence-high {
            background: var(--green-accent);
            color: var(--white-primary);
        }

        .confidence-medium {
            background: var(--orange-accent);
            color: var(--white-primary);
        }

        .confidence-low {
            background: var(--red-accent);
            color: var(--white-primary);
        }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            background: var(--gray-light);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .detail-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-weight: 700;
            color: var(--black-primary);
            font-size: 1rem;
        }

        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 700;
            color: var(--black-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--black-primary);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        /* Loading States */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-light);
            border-top: 4px solid var(--neofox-yellow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            z-index: 10;
        }

        .processing-text {
            margin-top: 1rem;
            font-weight: 700;
            color: var(--black-primary);
        }

        /* Preview Image */
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            border: 2px solid var(--black-primary);
            margin: 1rem auto;
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.75rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .mode-toggle {
                margin-bottom: 1.5rem;
            }

            .mode-btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .camera-container {
                height: 300px;
            }

            .result-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .result-details {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .status-indicator {
                position: fixed;
                top: auto;
                bottom: 20px;
                right: 20px;
                left: 20px;
                max-width: none;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .scale-in {
            animation: scaleIn 0.3s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-cube"></i> NEOFOX GEAR
            </a>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="assets.php" class="nav-link">
                    <i class="fas fa-box"></i> Assets
                </a>
                <a href="ai_scanner.php" class="nav-link active">
                    <i class="fas fa-robot"></i> AI Scanner
                </a>
                <a href="add_asset.php" class="nav-link">
                    <i class="fas fa-plus"></i> Manual Add
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Status Indicator -->
    <div class="status-indicator" id="statusIndicator">
        <div class="status-header">
            <div class="status-icon" id="statusIcon"></div>
            <div class="status-text" id="statusText"></div>
        </div>
        <div class="status-detail" id="statusDetail"></div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-robot"></i> AI EQUIPMENT SCANNER
            </h1>
            <p class="page-subtitle">Quick and accurate equipment identification</p>
        </div>

        <!-- Mode Toggle -->
        <div class="mode-toggle">
            <button class="mode-btn active" id="singleModeBtn" onclick="switchMode('single')">
                <i class="fas fa-camera"></i> Single Item
            </button>
            <button class="mode-btn" id="bulkModeBtn" onclick="switchMode('bulk')">
                <i class="fas fa-images"></i> Bulk Scan
            </button>
        </div>

        <!-- Single Item Scanner -->
        <div class="scanner-card" id="singleScanner">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-crosshairs"></i>
                </div>
                <div class="card-title">Single Item Scanner</div>
                <div class="card-subtitle">Perfect for individual equipment pieces</div>
            </div>

            <div class="camera-container" id="cameraContainer">
                <div class="camera-placeholder" id="cameraPlaceholder">
                    <i class="fas fa-camera fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Click "Start Camera" to scan equipment</p>
                    <div style="margin-top: 1rem; font-size: 0.9rem; color: #999;">
                        📱 <strong>Camera Tips:</strong><br>
                        • Hold device steady<br>
                        • Ensure good lighting<br>
                        • Include brand names/labels<br>
                        • Keep equipment centered
                    </div>
                </div>
                <video id="cameraFeed" style="display: none;" autoplay playsinline></video>
                <div class="capture-overlay" id="captureOverlay" style="display: none;">
                    <div class="scanning-frame">
                        <div class="corner-marker top-left"></div>
                        <div class="corner-marker top-right"></div>
                        <div class="corner-marker bottom-left"></div>
                        <div class="corner-marker bottom-right"></div>
                    </div>
                    <div class="camera-info" id="cameraInfo" style="display: none;">
                        <i class="fas fa-video"></i> <span id="cameraResolution">Loading...</span>
                    </div>
                </div>
                <div class="processing-overlay" id="singleProcessing" style="display: none;">
                    <div class="spinner"></div>
                    <div class="processing-text">Analyzing equipment...</div>
                </div>
            </div>

            <div class="btn-group">
                <button class="btn btn-primary" id="startCameraBtn" onclick="startCamera()">
                    <i class="fas fa-video"></i> Start Camera
                </button>
                <button class="btn btn-success" id="captureBtn" onclick="capturePhoto()" style="display: none;">
                    <i class="fas fa-camera"></i> Scan Now
                </button>
                <button class="btn btn-danger" id="stopCameraBtn" onclick="stopCamera()" style="display: none;">
                    <i class="fas fa-stop"></i> Stop Camera
                </button>
            </div>
        </div>

        <!-- Bulk Scanner -->
        <div class="scanner-card" id="bulkScanner" style="display: none;">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-images"></i>
                </div>
                <div class="card-title">Bulk Equipment Scanner</div>
                <div class="card-subtitle">Take multiple photos or upload files for batch processing</div>
            </div>

            <!-- Camera Option for Bulk -->
            <div class="camera-container" id="bulkCameraContainer" style="display: none;">
                <div class="camera-placeholder" id="bulkCameraPlaceholder">
                    <i class="fas fa-camera fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Take multiple photos of different equipment</p>
                    <div style="margin-top: 1rem; font-size: 0.9rem; color: #999;">
                        📷 <strong>Bulk Tips:</strong><br>
                        • Take wide shots to capture multiple items<br>
                        • Include close-ups of brand/model labels<br>
                        • Different angles improve accuracy<br>
                        • Good lighting is essential
                    </div>
                </div>
                <video id="bulkCameraFeed" style="display: none;" autoplay playsinline></video>
                <div class="capture-overlay" id="bulkCaptureOverlay" style="display: none;">
                    <div class="scanning-frame">
                        <div class="corner-marker top-left"></div>
                        <div class="corner-marker top-right"></div>
                        <div class="corner-marker bottom-left"></div>
                        <div class="corner-marker bottom-right"></div>
                    </div>
                    <div class="camera-info" id="bulkCameraInfo" style="display: none;">
                        <i class="fas fa-video"></i> <span id="bulkCameraResolution">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Upload Option for Bulk -->
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('photoInput').click()">
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="upload-text">Drop photos here or click to upload</div>
                <div class="upload-hint">Upload multiple angles for better accuracy</div>
                <div class="processing-overlay" id="bulkProcessing" style="display: none;">
                    <div class="spinner"></div>
                    <div class="processing-text">Processing photos...</div>
                </div>
            </div>

            <input type="file" id="photoInput" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(event)">

            <!-- Photo Gallery -->
            <div id="photoGallery" style="display: none; margin: 1.5rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h4 style="margin: 0; font-weight: 700;">
                        <i class="fas fa-images"></i> Captured Photos
                    </h4>
                    <span id="photoCount" style="background: var(--neofox-yellow); padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 700; border: 2px solid var(--black-primary);">0 photos</span>
                </div>
                <div id="photoThumbnails" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; max-height: 200px; overflow-y: auto; padding: 1rem; background: var(--gray-light); border-radius: 12px; border: 2px solid var(--black-primary);"></div>
            </div>

            <!-- Bulk Mode Toggle -->
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                <button class="btn btn-secondary" id="showCameraBtn" onclick="showBulkCamera()">
                    <i class="fas fa-camera"></i> Use Camera
                </button>
                <button class="btn btn-secondary" id="showUploadBtn" onclick="showBulkUpload()" style="display: none;">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Files
                </button>
            </div>

            <!-- Camera Controls -->
            <div class="btn-group" id="bulkCameraControls" style="display: none;">
                <button class="btn btn-primary" id="startBulkCameraBtn" onclick="startBulkCamera()">
                    <i class="fas fa-video"></i> Start Camera
                </button>
                <button class="btn btn-success" id="captureBulkBtn" onclick="captureBulkPhoto()" style="display: none;">
                    <i class="fas fa-camera"></i> Take Photo
                </button>
                <button class="btn btn-danger" id="stopBulkCameraBtn" onclick="stopBulkCamera()" style="display: none;">
                    <i class="fas fa-stop"></i> Stop Camera
                </button>
            </div>

            <!-- Analysis Controls -->
            <div class="btn-group" id="bulkAnalysisControls">
                <button class="btn btn-success" id="analyzeBtn" onclick="analyzePhotos()" style="display: none;">
                    <i class="fas fa-brain"></i> Analyze All Photos
                </button>
                <button class="btn btn-secondary" id="clearBtn" onclick="clearImages()" style="display: none;">
                    <i class="fas fa-trash"></i> Clear All
                </button>
            </div>
        </div>

        <!-- Results Container -->
        <div class="results-container" id="resultsContainer">
            <!-- Results will be populated here -->
        </div>
    </div>

    <script>
        class ImprovedAIScanner {
            constructor() {
                this.stream = null;
                this.bulkStream = null;
                this.uploadedFiles = [];
                this.canvas = document.createElement('canvas');
                this.currentMode = 'single';
                this.isProcessing = false;
                this.bulkCameraMode = false;
                this.setupDragDrop();
            }

            switchMode(mode) {
                this.currentMode = mode;
                
                // Update button states
                document.getElementById('singleModeBtn').classList.toggle('active', mode === 'single');
                document.getElementById('bulkModeBtn').classList.toggle('active', mode === 'bulk');
                
                // Show/hide scanners
                document.getElementById('singleScanner').style.display = mode === 'single' ? 'block' : 'none';
                document.getElementById('bulkScanner').style.display = mode === 'bulk' ? 'block' : 'none';
                
                // Clear any existing results
                document.getElementById('resultsContainer').innerHTML = '';
                
                // Stop cameras if switching modes
                if (mode !== 'single' && this.stream) {
                    this.stopCamera();
                }
                if (mode !== 'bulk' && this.bulkStream) {
                    this.stopBulkCamera();
                }
                
                // Reset bulk scanner to upload mode
                if (mode === 'bulk') {
                    this.showBulkUpload();
                }
            }

            showBulkCamera() {
                this.bulkCameraMode = true;
                document.getElementById('bulkCameraContainer').style.display = 'block';
                document.getElementById('uploadArea').style.display = 'none';
                document.getElementById('bulkCameraControls').style.display = 'flex';
                document.getElementById('showCameraBtn').style.display = 'none';
                document.getElementById('showUploadBtn').style.display = 'inline-flex';
            }

            showBulkUpload() {
                this.bulkCameraMode = false;
                this.stopBulkCamera();
                document.getElementById('bulkCameraContainer').style.display = 'none';
                document.getElementById('uploadArea').style.display = 'block';
                document.getElementById('bulkCameraControls').style.display = 'none';
                document.getElementById('showCameraBtn').style.display = 'inline-flex';
                document.getElementById('showUploadBtn').style.display = 'none';
            }

            async startBulkCamera() {
                try {
                    // Same improved camera constraints for bulk mode
                    let constraints = [
                        // First try: High resolution with wide field of view
                        { 
                            video: { 
                                facingMode: 'environment',
                                width: { ideal: 1920, min: 1280 },
                                height: { ideal: 1080, min: 720 },
                                aspectRatio: { ideal: 16/9 }
                            } 
                        },
                        // Fallback: Standard resolution with environment camera
                        { 
                            video: { 
                                facingMode: 'environment',
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        },
                        // Last resort: Any available camera
                        { 
                            video: { 
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        }
                    ];

                    let stream = null;
                    for (let constraint of constraints) {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia(constraint);
                            break;
                        } catch (e) {
                            console.log('Bulk camera constraint failed, trying next:', e);
                        }
                    }

                    if (!stream) {
                        throw new Error('No suitable camera found');
                    }

                    this.bulkStream = stream;
                    
                    const video = document.getElementById('bulkCameraFeed');
                    const placeholder = document.getElementById('bulkCameraPlaceholder');
                    const overlay = document.getElementById('bulkCaptureOverlay');
                    
                    video.srcObject = this.bulkStream;
                    video.style.display = 'block';
                    placeholder.style.display = 'none';
                    overlay.style.display = 'flex';
                    
                    // Update buttons
                    document.getElementById('startBulkCameraBtn').style.display = 'none';
                    document.getElementById('captureBulkBtn').style.display = 'inline-flex';
                    document.getElementById('stopBulkCameraBtn').style.display = 'inline-flex';
                    
                    // Get actual video resolution for user feedback
                    video.onloadedmetadata = () => {
                        const resolution = `${video.videoWidth}x${video.videoHeight}`;
                        document.getElementById('bulkCameraResolution').textContent = resolution;
                        document.getElementById('bulkCameraInfo').style.display = 'block';
                        this.showStatus('success', 'Bulk camera ready', `${resolution} resolution - Wide field of view for multiple equipment scanning`);
                    };
                    
                } catch (error) {
                    console.error('Error accessing bulk camera:', error);
                    this.showStatus('error', 'Camera access failed', 'Please ensure you have a camera and have granted permissions');
                }
            }

            stopBulkCamera() {
                if (this.bulkStream) {
                    this.bulkStream.getTracks().forEach(track => track.stop());
                    this.bulkStream = null;
                }
                
                const video = document.getElementById('bulkCameraFeed');
                const placeholder = document.getElementById('bulkCameraPlaceholder');
                const overlay = document.getElementById('bulkCaptureOverlay');
                
                video.style.display = 'none';
                placeholder.style.display = 'block';
                overlay.style.display = 'none';
                
                // Reset buttons
                document.getElementById('startBulkCameraBtn').style.display = 'inline-flex';
                document.getElementById('captureBulkBtn').style.display = 'none';
                document.getElementById('stopBulkCameraBtn').style.display = 'none';
            }

            captureBulkPhoto() {
                if (this.isProcessing) return;
                
                const video = document.getElementById('bulkCameraFeed');
                const canvas = this.canvas;
                const ctx = canvas.getContext('2d');
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                canvas.toBlob((blob) => {
                    const file = new File([blob], `bulk_capture_${Date.now()}.jpg`, { type: 'image/jpeg' });
                    this.uploadedFiles.push(file);
                    this.updateBulkUI();
                    this.showStatus('success', `Photo ${this.uploadedFiles.length} captured`, 'Take more photos or analyze when ready');
                }, 'image/jpeg', 0.9);
            }

            setupDragDrop() {
                const uploadArea = document.getElementById('uploadArea');
                
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    this.handleFiles(e.dataTransfer.files);
                });
            }

            showStatus(type, text, detail = '') {
                const indicator = document.getElementById('statusIndicator');
                const icon = document.getElementById('statusIcon');
                const statusText = document.getElementById('statusText');
                const statusDetail = document.getElementById('statusDetail');
                
                icon.className = `status-icon status-${type}`;
                statusText.textContent = text;
                statusDetail.textContent = detail;
                
                indicator.classList.add('show');
                
                if (type === 'success' || type === 'error') {
                    setTimeout(() => {
                        indicator.classList.remove('show');
                    }, 4000);
                }
            }

            hideStatus() {
                document.getElementById('statusIndicator').classList.remove('show');
            }

            async startCamera() {
                try {
                    // Try different camera configurations for better equipment scanning
                    let constraints = [
                        // First try: High resolution with wide field of view
                        { 
                            video: { 
                                facingMode: 'environment',
                                width: { ideal: 1920, min: 1280 },
                                height: { ideal: 1080, min: 720 },
                                aspectRatio: { ideal: 16/9 }
                            } 
                        },
                        // Fallback: Standard resolution with environment camera
                        { 
                            video: { 
                                facingMode: 'environment',
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        },
                        // Last resort: Any available camera
                        { 
                            video: { 
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            } 
                        }
                    ];

                    let stream = null;
                    for (let constraint of constraints) {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia(constraint);
                            break;
                        } catch (e) {
                            console.log('Camera constraint failed, trying next:', e);
                        }
                    }

                    if (!stream) {
                        throw new Error('No suitable camera found');
                    }

                    this.stream = stream;
                    
                    const video = document.getElementById('cameraFeed');
                    const placeholder = document.getElementById('cameraPlaceholder');
                    const overlay = document.getElementById('captureOverlay');
                    
                    video.srcObject = this.stream;
                    video.style.display = 'block';
                    placeholder.style.display = 'none';
                    overlay.style.display = 'flex';
                    
                    // Update buttons
                    document.getElementById('startCameraBtn').style.display = 'none';
                    document.getElementById('captureBtn').style.display = 'inline-flex';
                    document.getElementById('stopCameraBtn').style.display = 'inline-flex';
                    
                    // Get actual video resolution for user feedback
                    video.onloadedmetadata = () => {
                        const resolution = `${video.videoWidth}x${video.videoHeight}`;
                        document.getElementById('cameraResolution').textContent = resolution;
                        document.getElementById('cameraInfo').style.display = 'block';
                        this.showStatus('success', 'Camera ready', `${resolution} resolution - Wide field of view for equipment scanning`);
                    };
                    
                } catch (error) {
                    console.error('Error accessing camera:', error);
                    this.showStatus('error', 'Camera access failed', 'Please ensure you have a camera and have granted permissions');
                }
            }

            stopCamera() {
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.stream = null;
                }
                
                const video = document.getElementById('cameraFeed');
                const placeholder = document.getElementById('cameraPlaceholder');
                const overlay = document.getElementById('captureOverlay');
                
                video.style.display = 'none';
                placeholder.style.display = 'block';
                overlay.style.display = 'none';
                
                // Reset buttons
                document.getElementById('startCameraBtn').style.display = 'inline-flex';
                document.getElementById('captureBtn').style.display = 'none';
                document.getElementById('stopCameraBtn').style.display = 'none';
                
                this.hideStatus();
            }

            async capturePhoto() {
                if (this.isProcessing) return;
                
                const video = document.getElementById('cameraFeed');
                const canvas = this.canvas;
                const ctx = canvas.getContext('2d');
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                // Show processing overlay
                document.getElementById('singleProcessing').style.display = 'flex';
                this.isProcessing = true;
                this.showStatus('processing', 'Analyzing equipment...', 'Using AI to identify the item');
                
                canvas.toBlob(async (blob) => {
                    // Compress the image before sending
                    const file = new File([blob], `single_capture_${Date.now()}.jpg`, { type: 'image/jpeg' });
                    const compressedFile = await this.compressImage(file, true);
                    await this.analyzeImage(compressedFile, true);
                    
                    // Hide processing overlay
                    document.getElementById('singleProcessing').style.display = 'none';
                    this.isProcessing = false;
                }, 'image/jpeg', 0.8); // Slightly lower quality for speed
            }

            handleFiles(files) {
                Array.from(files).forEach(file => {
                    if (file.type.startsWith('image/') && this.uploadedFiles.length < 20) {
                        this.uploadedFiles.push(file);
                    }
                });
                this.updateBulkUI();
            }

            updateBulkUI() {
                const count = this.uploadedFiles.length;
                const photoGallery = document.getElementById('photoGallery');
                const photoCount = document.getElementById('photoCount');
                const photoThumbnails = document.getElementById('photoThumbnails');
                const analyzeBtn = document.getElementById('analyzeBtn');
                const clearBtn = document.getElementById('clearBtn');
                
                if (count > 0) {
                    photoGallery.style.display = 'block';
                    photoCount.textContent = `${count} photo${count === 1 ? '' : 's'}`;
                    analyzeBtn.style.display = 'inline-flex';
                    clearBtn.style.display = 'inline-flex';
                    
                    // Update thumbnails
                    photoThumbnails.innerHTML = '';
                    this.uploadedFiles.forEach((file, index) => {
                        const thumbnailDiv = document.createElement('div');
                        thumbnailDiv.style.position = 'relative';
                        thumbnailDiv.style.aspectRatio = '1';
                        thumbnailDiv.style.borderRadius = '8px';
                        thumbnailDiv.style.overflow = 'hidden';
                        thumbnailDiv.style.border = '2px solid var(--black-primary)';
                        
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.innerHTML = '×';
                        removeBtn.style.position = 'absolute';
                        removeBtn.style.top = '4px';
                        removeBtn.style.right = '4px';
                        removeBtn.style.background = 'var(--red-accent)';
                        removeBtn.style.color = 'white';
                        removeBtn.style.border = 'none';
                        removeBtn.style.borderRadius = '50%';
                        removeBtn.style.width = '20px';
                        removeBtn.style.height = '20px';
                        removeBtn.style.cursor = 'pointer';
                        removeBtn.style.fontSize = '12px';
                        removeBtn.style.display = 'flex';
                        removeBtn.style.alignItems = 'center';
                        removeBtn.style.justifyContent = 'center';
                        removeBtn.onclick = () => this.removeImage(index);
                        
                        thumbnailDiv.appendChild(img);
                        thumbnailDiv.appendChild(removeBtn);
                        photoThumbnails.appendChild(thumbnailDiv);
                    });
                } else {
                    photoGallery.style.display = 'none';
                    analyzeBtn.style.display = 'none';
                    clearBtn.style.display = 'none';
                }
            }

            removeImage(index) {
                this.uploadedFiles.splice(index, 1);
                this.updateBulkUI();
            }

            clearImages() {
                this.uploadedFiles = [];
                this.updateBulkUI();
                document.getElementById('resultsContainer').innerHTML = '';
                this.showStatus('success', 'All photos cleared', 'Ready for new photos');
            }

            async analyzePhotos() {
                if (this.uploadedFiles.length === 0 || this.isProcessing) return;
                
                this.isProcessing = true;
                document.getElementById('bulkProcessing').style.display = 'flex';
                this.showStatus('processing', 'Processing photos...', `Analyzing ${Math.min(this.uploadedFiles.length, 3)} images (processing limited for speed)`);
                
                try {
                    // Limit to 3 images for speed, process the most recent ones
                    const imagesToProcess = this.uploadedFiles.slice(-3);
                    const results = [];
                    
                    for (let i = 0; i < imagesToProcess.length; i++) {
                        const file = imagesToProcess[i];
                        this.showStatus('processing', `Processing image ${i + 1}/${imagesToProcess.length}...`, 'Fast AI analysis in progress');
                        
                        const result = await this.analyzeImage(file, false);
                        if (result) {
                            results.push(...result);
                        }
                        
                        // Minimal delay for UI responsiveness
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }
                    
                    // Merge similar results
                    const mergedResults = this.mergeResults(results);
                    
                    if (mergedResults.length > 0) {
                        this.displayResults(mergedResults);
                        this.showStatus('success', `Found ${mergedResults.length} items`, 'Review and add to inventory');
                    } else {
                        this.showStatus('error', 'No equipment detected', 'Try clearer photos with better lighting and visible brand names');
                    }
                    
                } catch (error) {
                    console.error('Bulk analysis error:', error);
                    this.showStatus('error', 'Analysis failed', error.message);
                } finally {
                    document.getElementById('bulkProcessing').style.display = 'none';
                    this.isProcessing = false;
                }
            }

            async analyzeImage(file, isSingle = false) {
                try {
                    // Compress image on frontend for faster upload
                    const compressedFile = await this.compressImage(file, isSingle);
                    
                    const formData = new FormData();
                    formData.append('action', 'analyze_images');
                    formData.append('single_mode', isSingle ? '1' : '0');
                    formData.append('images[]', compressedFile);

                    const response = await fetch('process_ai_detection.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        if (response.status === 401) {
                            this.showStatus('error', 'Session expired', 'Redirecting to login...');
                            setTimeout(() => window.location.href = 'login.php', 2000);
                            return null;
                        }
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success && data.results && data.results.length > 0) {
                        if (isSingle) {
                            this.displayResults(data.results);
                        }
                        return data.results;
                    }
                    
                    return null;
                    
                } catch (error) {
                    console.error('Analysis error:', error);
                    throw error;
                }
            }

            async compressImage(file, isSingle = false) {
                return new Promise((resolve) => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const img = new Image();
                    
                    img.onload = () => {
                        // Optimize size for faster processing
                        const maxWidth = isSingle ? 800 : 600;
                        const quality = 0.7;
                        
                        let { width, height } = img;
                        
                        // Resize if too large
                        if (width > maxWidth) {
                            const ratio = maxWidth / width;
                            width = maxWidth;
                            height = height * ratio;
                        }
                        
                        canvas.width = width;
                        canvas.height = height;
                        
                        // Draw and compress
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        canvas.toBlob((blob) => {
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        }, 'image/jpeg', quality);
                    };
                    
                    img.src = URL.createObjectURL(file);
                });
            }

            mergeResults(results) {
                if (!results || results.length === 0) return [];
                
                // Group by brand + model combination
                const grouped = {};
                results.forEach(result => {
                    const key = `${result.brand || 'Unknown'}_${result.model || 'Unknown'}`.toLowerCase();
                    if (!grouped[key]) {
                        grouped[key] = [];
                    }
                    grouped[key].push(result);
                });
                
                // Merge groups and pick best confidence
                const merged = [];
                Object.values(grouped).forEach(group => {
                    const best = group.reduce((prev, current) => 
                        (current.confidence > prev.confidence) ? current : prev
                    );
                    
                    // Boost confidence if seen multiple times
                    if (group.length > 1) {
                        best.confidence = Math.min(0.98, best.confidence + (group.length * 0.05));
                        best.detections_count = group.length;
                    }
                    
                    merged.push(best);
                });
                
                // Sort by confidence
                return merged.sort((a, b) => b.confidence - a.confidence);
            }

            displayResults(results) {
                const container = document.getElementById('resultsContainer');
                container.innerHTML = '';
                
                results.forEach((result, index) => {
                    const confidenceClass = result.confidence >= 0.8 ? 'high' : 
                                          result.confidence >= 0.6 ? 'medium' : 'low';
                    
                    const resultCard = document.createElement('div');
                    resultCard.className = 'result-card fade-in';
                    resultCard.style.animationDelay = `${index * 0.1}s`;
                    
                    resultCard.innerHTML = `
                        <div class="result-header">
                            <div class="detected-item">${result.name || 'Unknown Equipment'}</div>
                            <div class="confidence-badge confidence-${confidenceClass}">
                                ${Math.round(result.confidence * 100)}% confident
                                ${result.detections_count > 1 ? ` (seen ${result.detections_count}x)` : ''}
                            </div>
                        </div>
                        
                        <div class="result-details">
                            <div class="detail-item">
                                <div class="detail-label">Brand</div>
                                <div class="detail-value">${result.brand || 'Unknown'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Model</div>
                                <div class="detail-value">${result.model || 'Unknown Model'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Category</div>
                                <div class="detail-value">${result.category || 'Equipment'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Type</div>
                                <div class="detail-value">${result.type || 'Unknown Type'}</div>
                            </div>
                        </div>
                        
                        <form onsubmit="addToInventory(event, ${index}, ${JSON.stringify(result).replace(/"/g, '&quot;')})" style="margin-top: 2rem;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Asset Name</label>
                                    <input type="text" class="form-control" name="asset_name" value="${result.name || ''}" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select class="form-control" name="category" required>
                                        <option value="Camera" ${result.category === 'Camera' ? 'selected' : ''}>Camera</option>
                                        <option value="Audio" ${result.category === 'Audio' ? 'selected' : ''}>Audio</option>
                                        <option value="Lighting" ${result.category === 'Lighting' ? 'selected' : ''}>Lighting</option>
                                        <option value="Accessories" ${result.category === 'Accessories' ? 'selected' : ''}>Accessories</option>
                                        <option value="Computer" ${result.category === 'Computer' ? 'selected' : ''}>Computer</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Serial Number (if visible)</label>
                                <input type="text" class="form-control" name="serial_number" placeholder="Enter if visible on equipment">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2">AI Detected: ${result.brand || 'Unknown'} ${result.model || 'Unknown Model'} (${Math.round(result.confidence * 100)}% confidence)</textarea>
                            </div>
                            
                            <input type="hidden" name="brand" value="${result.brand || ''}">
                            <input type="hidden" name="model" value="${result.model || ''}">
                            <input type="hidden" name="features" value="${Array.isArray(result.features) ? result.features.join(', ') : (result.features || '')}">
                            
                            <div class="btn-group">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add to Inventory
                                </button>
                                <button type="button" class="btn btn-danger" onclick="removeResult(this)">
                                    <i class="fas fa-times"></i> Not Correct
                                </button>
                            </div>
                        </form>
                    `;
                    
                    container.appendChild(resultCard);
                });
                
                // Scroll to results
                container.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Global instance
        const scanner = new ImprovedAIScanner();

        // Global functions
        function switchMode(mode) {
            scanner.switchMode(mode);
        }

        function startCamera() {
            scanner.startCamera();
        }

        function stopCamera() {
            scanner.stopCamera();
        }

        function capturePhoto() {
            scanner.capturePhoto();
        }

        function showBulkCamera() {
            scanner.showBulkCamera();
        }

        function showBulkUpload() {
            scanner.showBulkUpload();
        }

        function startBulkCamera() {
            scanner.startBulkCamera();
        }

        function stopBulkCamera() {
            scanner.stopBulkCamera();
        }

        function captureBulkPhoto() {
            scanner.captureBulkPhoto();
        }

        function handleFileSelect(event) {
            scanner.handleFiles(event.target.files);
        }

        function analyzePhotos() {
            scanner.analyzePhotos();
        }

        function clearImages() {
            scanner.clearImages();
        }

        function removeResult(button) {
            const resultCard = button.closest('.result-card');
            resultCard.style.animation = 'scaleOut 0.3s ease';
            setTimeout(() => {
                resultCard.remove();
            }, 300);
        }

        async function addToInventory(event, resultIndex, resultData) {
            event.preventDefault();
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(event.target);
                formData.append('action', 'add_detected_asset');
                
                const response = await fetch('process_ai_detection.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 401) {
                        scanner.showStatus('error', 'Session expired', 'Redirecting to login...');
                        setTimeout(() => window.location.href = 'login.php', 2000);
                        return;
                    }
                    throw new Error(data.error || 'Failed to add asset');
                }

                if (data.success) {
                    scanner.showStatus('success', 'Asset added!', `${formData.get('asset_name')} added with ID: ${data.asset_id}`);
                    
                    // Replace form with success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success scale-in';
                    successDiv.style.background = 'var(--green-accent)';
                    successDiv.style.color = 'white';
                    successDiv.style.padding = '1rem';
                    successDiv.style.borderRadius = '8px';
                    successDiv.style.textAlign = 'center';
                    successDiv.style.marginTop = '2rem';
                    successDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i>
                        <strong>${formData.get('asset_name')}</strong> added successfully! 
                        <br><small>Asset ID: ${data.asset_id}</small>
                    `;
                    
                    event.target.replaceWith(successDiv);
                    
                } else {
                    throw new Error(data.error || 'Unknown error occurred');
                }
                
            } catch (error) {
                console.error('Error adding asset:', error);
                scanner.showStatus('error', 'Failed to add asset', error.message);
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Add CSS for scale out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes scaleOut {
                from { transform: scale(1); opacity: 1; }
                to { transform: scale(0.8); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (scanner.stream) {
                scanner.stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>
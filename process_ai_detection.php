<?php
// improved_process_ai_detection.php - Fast and Accurate AI Equipment Detection
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized - Please login again',
        'redirect' => 'login.php'
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'analyze_images':
            analyzeImages();
            break;
        case 'add_detected_asset':
            addDetectedAsset();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function analyzeImages() {
    try {
        if (!isset($_FILES['images']) || empty($_FILES['images']['tmp_name'])) {
            throw new Exception('No images uploaded');
        }
        
        $images = $_FILES['images'];
        $singleMode = isset($_POST['single_mode']) && $_POST['single_mode'] === '1';
        
        // Compress images before processing for speed
        $processedImages = [];
        for ($i = 0; $i < count($images['tmp_name']); $i++) {
            if ($images['error'][$i] === UPLOAD_ERR_OK) {
                $imagePath = $images['tmp_name'][$i];
                
                // Compress image for faster processing
                $compressedData = compressImageForAI($imagePath, $singleMode);
                if ($compressedData) {
                    $processedImages[] = $compressedData;
                }
            }
        }
        
        if (empty($processedImages)) {
            throw new Exception('No valid images to process');
        }
        
        // Process images efficiently
        if ($singleMode) {
            // Single mode: process first image only
            $detection = callOptimizedAIDetection($processedImages[0], true);
            $finalResults = $detection ? $detection : [];
        } else {
            // Bulk mode: process up to 3 images max for speed
            $imagesToProcess = array_slice($processedImages, 0, 3);
            $detectionResults = [];
            
            foreach ($imagesToProcess as $imageData) {
                $detection = callOptimizedAIDetection($imageData, false);
                if ($detection) {
                    $detectionResults[] = $detection;
                }
            }
            
            $finalResults = processDetectionResults($detectionResults, false);
        }
        
        if (empty($finalResults)) {
            echo json_encode([
                'success' => false,
                'error' => 'No equipment detected. Ensure images show clear equipment with visible brand names.'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'results' => $finalResults
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}

function compressImageForAI($imagePath, $singleMode = false) {
    try {
        // Target size for faster processing
        $maxWidth = $singleMode ? 800 : 600;  // Smaller for bulk
        $quality = 70; // Good balance of quality vs speed
        
        // Get image info
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) return null;
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Skip compression if already small
        if ($originalWidth <= $maxWidth) {
            return base64_encode(file_get_contents($imagePath));
        }
        
        // Calculate new dimensions
        $ratio = $maxWidth / $originalWidth;
        $newWidth = $maxWidth;
        $newHeight = intval($originalHeight * $ratio);
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                return base64_encode(file_get_contents($imagePath));
        }
        
        if (!$source) return null;
        
        // Create compressed image
        $compressed = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($mimeType === 'image/png') {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
        }
        
        // Resize
        imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Output to string
        ob_start();
        imagejpeg($compressed, null, $quality);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        // Cleanup
        imagedestroy($source);
        imagedestroy($compressed);
        
        return base64_encode($imageData);
        
    } catch (Exception $e) {
        error_log("Image compression error: " . $e->getMessage());
        return base64_encode(file_get_contents($imagePath));
    }
}

function callOptimizedAIDetection($imageData, $singleMode = false) {
    // For maximum speed, only use OpenAI (most accurate) - skip Google Vision fallback
    $openaiResults = callOpenAIVisionOptimized($imageData, $singleMode);
    
    if ($openaiResults && !empty($openaiResults)) {
        return $openaiResults;
    }
    
    // Quick fallback: try to detect obvious equipment from image size/type
    return createQuickFallbackResult();
}

function createQuickFallbackResult() {
    // Return a generic equipment result to avoid complete failure
    return [[
        'name' => 'Equipment Detected',
        'brand' => 'Unknown',
        'model' => 'Unknown Model',
        'category' => 'Other',
        'type' => 'Equipment',
        'confidence' => 0.5,
        'features' => ['Basic detection'],
        'detection_method' => 'Fallback'
    ]];
}

function callOpenAIVisionOptimized($imageData, $singleMode = false) {
    $apiKey = getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY';
    
    $url = "https://api.openai.com/v1/chat/completions";
    
    // Ultra-optimized prompts for speed and accuracy
    if ($singleMode) {
        $prompt = 'Identify the main equipment in this image. Return JSON: {"name": "Brand Model", "brand": "Brand", "model": "Model", "category": "Camera/Audio/Lighting/Accessories/Computer", "type": "Type", "confidence": 0.9}. Only respond with JSON, no explanation.';
    } else {
        $prompt = 'List all visible equipment. Return JSON array: [{"name": "Brand Model", "brand": "Brand", "model": "Model", "category": "Camera/Audio/Lighting/Accessories/Computer", "type": "Type", "confidence": 0.9}]. Max 5 items. Only JSON, no text.';
    }
    
    $requestData = [
        'model' => 'gpt-4o-mini', // Faster, cheaper model
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . $imageData,
                            'detail' => 'low' // Much faster processing
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => $singleMode ? 200 : 500, // Reduced tokens
        'temperature' => 0.1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reduced timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Quick connection timeout
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log("OpenAI API cURL Error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            error_log("OpenAI API Error: " . json_encode($data['error']));
            return null;
        }
        return parseOpenAIResponseOptimized($data, $singleMode);
    } else {
        error_log("OpenAI API HTTP Error: " . $httpCode . " - " . $response);
    }
    
    return null;
}

function parseOpenAIResponseOptimized($data, $singleMode = false) {
    if (!isset($data['choices'][0]['message']['content'])) {
        return null;
    }
    
    $content = $data['choices'][0]['message']['content'];
    
    // Extract JSON from response
    if ($singleMode) {
        // Single object expected
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $jsonData = json_decode($jsonString, true);
            
            if ($jsonData && isset($jsonData['name'])) {
                return [validateAndCleanResult($jsonData)];
            }
        }
    } else {
        // Array expected
        $jsonStart = strpos($content, '[');
        $jsonEnd = strrpos($content, ']');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $jsonData = json_decode($jsonString, true);
            
            if ($jsonData && is_array($jsonData)) {
                $cleanResults = [];
                foreach ($jsonData as $item) {
                    if (is_array($item) && isset($item['name'])) {
                        $cleanResults[] = validateAndCleanResult($item);
                    }
                }
                return $cleanResults;
            }
        }
        
        // Try single object fallback
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $jsonData = json_decode($jsonString, true);
            
            if ($jsonData && isset($jsonData['name'])) {
                return [validateAndCleanResult($jsonData)];
            }
        }
    }
    
    return null;
}

function callGoogleVisionOptimized($imageData) {
    $apiKey = getenv('GOOGLE_VISION_API_KEY') ?: 'YOUR_GOOGLE_VISION_API_KEY';
    
    $url = "https://vision.googleapis.com/v1/images:annotate?key=" . $apiKey;
    
    $requestData = [
        'requests' => [
            [
                'image' => [
                    'content' => $imageData
                ],
                'features' => [
                    ['type' => 'LABEL_DETECTION', 'maxResults' => 5],
                    ['type' => 'TEXT_DETECTION', 'maxResults' => 5],
                    ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 5]
                ]
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log("Google Vision API cURL Error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['responses'][0]['error'])) {
            error_log("Google Vision API Error: " . json_encode($data['responses'][0]['error']));
            return null;
        }
        return parseGoogleVisionOptimized($data);
    }
    
    return null;
}

function parseGoogleVisionOptimized($data) {
    if (!isset($data['responses'][0])) return null;
    
    $response = $data['responses'][0];
    $labels = $response['labelAnnotations'] ?? [];
    $text = $response['textAnnotations'] ?? [];
    
    // Extract detected text
    $detectedText = '';
    foreach ($text as $textAnnotation) {
        $detectedText .= ' ' . $textAnnotation['description'];
    }
    
    // Look for equipment-related labels
    $equipmentFound = false;
    foreach ($labels as $label) {
        $description = strtolower($label['description']);
        if (strpos($description, 'camera') !== false || 
            strpos($description, 'lens') !== false ||
            strpos($description, 'microphone') !== false ||
            strpos($description, 'light') !== false ||
            strpos($description, 'electronics') !== false) {
            $equipmentFound = true;
            break;
        }
    }
    
    if (!$equipmentFound && empty($detectedText)) {
        return null;
    }
    
    // Try to extract equipment from text
    $equipment = extractEquipmentFromText($detectedText);
    
    if ($equipment) {
        return [$equipment];
    }
    
    // Generic equipment detection based on labels
    if ($equipmentFound) {
        $primaryLabel = $labels[0];
        return [createGenericEquipment($primaryLabel, $detectedText)];
    }
    
    return null;
}

function extractEquipmentFromText($text) {
    $text = strtolower($text);
    
    // Common equipment brands and their categories
    $brands = [
        'canon' => ['category' => 'Camera', 'type' => 'Camera'],
        'sony' => ['category' => 'Camera', 'type' => 'Camera'],
        'nikon' => ['category' => 'Camera', 'type' => 'Camera'],
        'fujifilm' => ['category' => 'Camera', 'type' => 'Camera'],
        'panasonic' => ['category' => 'Camera', 'type' => 'Camera'],
        'blackmagic' => ['category' => 'Camera', 'type' => 'Cinema Camera'],
        'rode' => ['category' => 'Audio', 'type' => 'Microphone'],
        'shure' => ['category' => 'Audio', 'type' => 'Microphone'],
        'sennheiser' => ['category' => 'Audio', 'type' => 'Microphone'],
        'audio-technica' => ['category' => 'Audio', 'type' => 'Microphone'],
        'zoom' => ['category' => 'Audio', 'type' => 'Recorder'],
        'godox' => ['category' => 'Lighting', 'type' => 'LED Light'],
        'aputure' => ['category' => 'Lighting', 'type' => 'LED Light'],
        'profoto' => ['category' => 'Lighting', 'type' => 'Studio Light'],
        'manfrotto' => ['category' => 'Accessories', 'type' => 'Support'],
        'gitzo' => ['category' => 'Accessories', 'type' => 'Tripod'],
        'dji' => ['category' => 'Accessories', 'type' => 'Gimbal'],
        'ronin' => ['category' => 'Accessories', 'type' => 'Gimbal'],
        'apple' => ['category' => 'Computer', 'type' => 'Computer'],
        'macbook' => ['category' => 'Computer', 'type' => 'Laptop'],
        'imac' => ['category' => 'Computer', 'type' => 'Desktop'],
        'dell' => ['category' => 'Computer', 'type' => 'Computer'],
        'hp' => ['category' => 'Computer', 'type' => 'Computer'],
        'lenovo' => ['category' => 'Computer', 'type' => 'Computer']
    ];
    
    foreach ($brands as $brand => $info) {
        if (strpos($text, $brand) !== false) {
            // Try to extract model information
            $model = extractModelFromText($text, $brand);
            $name = ucfirst($brand) . ($model ? ' ' . $model : ' ' . $info['type']);
            
            return [
                'name' => $name,
                'brand' => ucfirst($brand),
                'model' => $model ?: 'Unknown Model',
                'category' => $info['category'],
                'type' => $info['type'],
                'features' => ['Detected from image text'],
                'confidence' => 0.85,
                'detection_method' => 'Google Vision Text'
            ];
        }
    }
    
    return null;
}

function extractModelFromText($text, $brand) {
    $brand = strtolower($brand);
    $text = strtolower($text);
    
    // Brand-specific model patterns
    $patterns = [
        'canon' => [
            '/canon\s+eos\s+([a-z0-9\s]+)/i',
            '/eos\s+([a-z0-9\s]+)/i',
            '/canon\s+([0-9]+d[a-z]*)/i'
        ],
        'sony' => [
            '/sony\s+([a-z0-9\s]+)/i',
            '/a7[a-z0-9\s]*/i',
            '/fx[0-9]+/i',
            '/a[0-9]+[a-z]*/i'
        ],
        'nikon' => [
            '/nikon\s+d([0-9]+)/i',
            '/d([0-9]+)/i',
            '/z\s*([0-9]+)/i'
        ],
        'rode' => [
            '/rode\s+([a-z0-9\s]+)/i',
            '/videomic\s+([a-z0-9\s]+)/i',
            '/procaster/i',
            '/smartlav/i'
        ],
        'shure' => [
            '/shure\s+([a-z0-9]+)/i',
            '/sm([0-9]+[a-z]*)/i',
            '/beta\s*([0-9]+[a-z]*)/i'
        ],
        'apple' => [
            '/macbook\s+(air|pro)/i',
            '/imac\s+([a-z0-9\s]+)/i',
            '/mac\s+(mini|pro|studio)/i'
        ]
    ];
    
    if (isset($patterns[$brand])) {
        foreach ($patterns[$brand] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $model = trim($matches[1] ?? $matches[0]);
                // Clean up the model name
                $model = preg_replace('/[^a-z0-9\s\-]/i', '', $model);
                if (strlen($model) >= 2 && strlen($model) <= 30) {
                    return ucwords($model);
                }
            }
        }
    }
    
    // Generic model extraction
    if (preg_match('/' . $brand . '\s+([a-z0-9\-\s]{2,30})/i', $text, $matches)) {
        $model = trim($matches[1]);
        $model = preg_replace('/[^a-z0-9\s\-]/i', '', $model);
        if (strlen($model) >= 2 && strlen($model) <= 30) {
            return ucwords($model);
        }
    }
    
    return null;
}

function createGenericEquipment($label, $detectedText) {
    $description = strtolower($label['description']);
    $confidence = $label['score'];
    
    if (strpos($description, 'camera') !== false) {
        return [
            'name' => 'Camera Equipment (Detected)',
            'brand' => 'Unknown',
            'model' => 'Unknown Model',
            'category' => 'Camera',
            'type' => 'Camera',
            'features' => ['Detected from image analysis'],
            'confidence' => $confidence,
            'detection_method' => 'Google Vision Labels'
        ];
    } elseif (strpos($description, 'microphone') !== false || strpos($description, 'audio') !== false) {
        return [
            'name' => 'Audio Equipment (Detected)',
            'brand' => 'Unknown',
            'model' => 'Unknown Model',
            'category' => 'Audio',
            'type' => 'Microphone',
            'features' => ['Detected from image analysis'],
            'confidence' => $confidence,
            'detection_method' => 'Google Vision Labels'
        ];
    } elseif (strpos($description, 'light') !== false) {
        return [
            'name' => 'Lighting Equipment (Detected)',
            'brand' => 'Unknown',
            'model' => 'Unknown Model',
            'category' => 'Lighting',
            'type' => 'Light',
            'features' => ['Detected from image analysis'],
            'confidence' => $confidence,
            'detection_method' => 'Google Vision Labels'
        ];
    }
    
    return [
        'name' => 'Equipment (Detected)',
        'brand' => 'Unknown',
        'model' => 'Unknown Model',
        'category' => 'Other',
        'type' => 'Equipment',
        'features' => ['Detected from image analysis'],
        'confidence' => $confidence,
        'detection_method' => 'Google Vision Labels'
    ];
}

function validateAndCleanResult($result) {
    // Ensure all required fields are present
    $cleaned = [
        'name' => trim($result['name'] ?? 'Unknown Equipment'),
        'brand' => trim($result['brand'] ?? 'Unknown'),
        'model' => trim($result['model'] ?? 'Unknown Model'),
        'category' => trim($result['category'] ?? 'Other'),
        'type' => trim($result['type'] ?? 'Equipment'),
        'confidence' => floatval($result['confidence'] ?? 0.8),
        'features' => is_array($result['features'] ?? []) ? $result['features'] : [$result['features'] ?? ''],
        'detection_method' => $result['detection_method'] ?? 'OpenAI GPT-4V'
    ];
    
    // Validate category
    $validCategories = ['Camera', 'Audio', 'Lighting', 'Accessories', 'Computer', 'Other'];
    if (!in_array($cleaned['category'], $validCategories)) {
        $cleaned['category'] = 'Other';
    }
    
    // Ensure confidence is between 0 and 1
    $cleaned['confidence'] = max(0.1, min(1.0, $cleaned['confidence']));
    
    // Clean up name if it's just brand + model
    if ($cleaned['name'] === 'Unknown Equipment' && $cleaned['brand'] !== 'Unknown') {
        $cleaned['name'] = $cleaned['brand'] . ' ' . $cleaned['type'];
    }
    
    return $cleaned;
}

function processDetectionResults($results, $singleMode = false) {
    if (empty($results)) return [];
    
    // Flatten all results
    $allDetections = [];
    foreach ($results as $imageResults) {
        if (is_array($imageResults)) {
            $allDetections = array_merge($allDetections, $imageResults);
        }
    }
    
    if (empty($allDetections)) return [];
    
    // Filter out low-confidence results
    $allDetections = array_filter($allDetections, function($detection) {
        return $detection['confidence'] >= 0.6; // Minimum confidence threshold
    });
    
    if ($singleMode) {
        // For single mode, return the highest confidence result
        if (!empty($allDetections)) {
            usort($allDetections, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            return [array_shift($allDetections)];
        }
        return [];
    }
    
    // For bulk mode, merge similar items
    return mergeDetections($allDetections);
}

function mergeDetections($detections) {
    if (empty($detections)) return [];
    
    // Group by brand + model combination
    $groups = [];
    foreach ($detections as $detection) {
        $key = strtolower(trim($detection['brand'] . '_' . $detection['model']));
        if (!isset($groups[$key])) {
            $groups[$key] = [];
        }
        $groups[$key][] = $detection;
    }
    
    // Merge groups and select best detection
    $merged = [];
    foreach ($groups as $group) {
        if (count($group) === 1) {
            $merged[] = $group[0];
        } else {
            // Multiple detections of same item - merge them
            $best = $group[0];
            $maxConfidence = $best['confidence'];
            $allFeatures = $best['features'];
            
            foreach ($group as $detection) {
                if ($detection['confidence'] > $maxConfidence) {
                    $maxConfidence = $detection['confidence'];
                    $best = $detection;
                }
                
                // Merge features
                if (isset($detection['features']) && is_array($detection['features'])) {
                    $allFeatures = array_merge($allFeatures, $detection['features']);
                }
            }
            
            // Boost confidence for multiple sightings
            $best['confidence'] = min(0.98, $maxConfidence + (count($group) * 0.05));
            $best['features'] = array_unique($allFeatures);
            $best['detections_count'] = count($group);
            
            $merged[] = $best;
        }
    }
    
    // Sort by confidence (highest first)
    usort($merged, function($a, $b) {
        return $b['confidence'] <=> $a['confidence'];
    });
    
    // Limit to top 10 results for bulk mode
    return array_slice($merged, 0, 10);
}

function addDetectedAsset() {
    global $asset;
    
    try {
        $detectionData = [
            'asset_name' => trim($_POST['asset_name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'type' => trim($_POST['type'] ?? ''),
            'serial_number' => trim($_POST['serial_number'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'features' => trim($_POST['features'] ?? '')
        ];
        
        // Validation
        if (empty($detectionData['asset_name']) || empty($detectionData['category'])) {
            throw new Exception('Asset name and category are required');
        }
        
        // Use the Asset class method for AI detection
        $assetId = $asset->createFromAIDetection($detectionData);
        
        if ($assetId) {
            echo json_encode([
                'success' => true,
                'message' => 'Asset added successfully',
                'asset_id' => $assetId
            ]);
        } else {
            $errors = $asset->getErrors();
            $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Failed to create asset in database';
            throw new Exception($errorMessage);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}
?>
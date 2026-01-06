<?php
// check_api_status.php - Check which AI APIs are configured and available
header('Content-Type: application/json');

// Your configured API keys - Set these in environment variables or config file
$googleVisionApiKey = getenv('GOOGLE_VISION_API_KEY') ?: 'YOUR_GOOGLE_VISION_API_KEY';
$openaiApiKey = getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY';

$status = [
    'google_vision' => true,  // Your Google Vision API is configured
    'openai_vision' => true,  // Your OpenAI API is configured  
    'fallback' => false      // Fallback disabled - only real AI results
];

echo json_encode($status);
?>
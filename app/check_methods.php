<?php
// check_methods.php - Check what methods exist in GearRequest class
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 GearRequest Class Analysis</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} .method{background:#f0f0f0;padding:10px;margin:5px 0;border-radius:5px;}</style>";

try {
    require_once 'classes/GearRequest.php';
    echo "<div class='success'>✅ GearRequest class loaded successfully</div>";
    
    // Check if class exists
    if (class_exists('GearRequest')) {
        echo "<div class='success'>✅ GearRequest class exists</div>";
        
        // Get all methods
        $reflection = new ReflectionClass('GearRequest');
        $methods = $reflection->getMethods();
        
        echo "<h2>📋 Available Methods in GearRequest Class:</h2>";
        
        $important_methods = ['create', 'sendAdminNotification', 'buildAdminNotificationEmail'];
        
        foreach ($methods as $method) {
            $method_name = $method->getName();
            $is_important = in_array($method_name, $important_methods);
            $visibility = $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected');
            
            $class_name = $is_important ? 'success' : 'info';
            echo "<div class='method {$class_name}'>";
            echo "<strong>{$method_name}()</strong> - {$visibility}";
            if ($is_important) echo " ⭐ IMPORTANT";
            echo "</div>";
        }
        
        // Check specific methods
        echo "<h2>🔍 Checking Specific Methods:</h2>";
        
        if (method_exists('GearRequest', 'create')) {
            echo "<div class='success'>✅ create() method exists</div>";
        } else {
            echo "<div class='error'>❌ create() method missing</div>";
        }
        
        if (method_exists('GearRequest', 'sendAdminNotification')) {
            echo "<div class='success'>✅ sendAdminNotification() method exists (email functionality available)</div>";
        } else {
            echo "<div class='error'>❌ sendAdminNotification() method missing (NO EMAIL FUNCTIONALITY)</div>";
        }
        
        if (method_exists('GearRequest', 'buildAdminNotificationEmail')) {
            echo "<div class='success'>✅ buildAdminNotificationEmail() method exists</div>";
        } else {
            echo "<div class='error'>❌ buildAdminNotificationEmail() method missing</div>";
        }
        
        // Try to get the source code of the create method
        echo "<h2>📄 Source Code Analysis:</h2>";
        try {
            $create_method = $reflection->getMethod('create');
            $filename = $create_method->getFileName();
            $start_line = $create_method->getStartLine();
            $end_line = $create_method->getEndLine();
            
            echo "<div class='info'>";
            echo "<strong>File:</strong> {$filename}<br>";
            echo "<strong>Lines:</strong> {$start_line} - {$end_line}<br>";
            echo "</div>";
            
            // Read the file and show the create method
            $file_lines = file($filename);
            $method_lines = array_slice($file_lines, $start_line - 1, $end_line - $start_line + 1);
            
            echo "<h3>create() method source:</h3>";
            echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;'>";
            echo htmlspecialchars(implode('', $method_lines));
            echo "</pre>";
            
            // Check if sendAdminNotification is called
            $method_source = implode('', $method_lines);
            if (strpos($method_source, 'sendAdminNotification') !== false) {
                echo "<div class='success'>✅ create() method calls sendAdminNotification()</div>";
            } else {
                echo "<div class='error'>❌ create() method does NOT call sendAdminNotification() - THIS IS THE PROBLEM!</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>Error analyzing source: " . $e->getMessage() . "</div>";
        }
        
    } else {
        echo "<div class='error'>❌ GearRequest class not found</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error loading GearRequest class: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<h2>🔧 Solution:</h2>";
echo "<div class='info'>";
echo "If the sendAdminNotification() method is missing or not being called, you need to:<br>";
echo "1. Replace your current classes/GearRequest.php with the enhanced version<br>";
echo "2. The enhanced version includes automatic email notifications<br>";
echo "3. Make sure EmailNotification.php is the SMTP version<br>";
echo "</div>";

echo "<h2>📋 Current Status Summary:</h2>";
echo "<div class='info'>";
echo "• SMTP Email System: ✅ Working (test emails received)<br>";
echo "• EmailNotification Class: ✅ Working<br>";
echo "• GearRequest Email Integration: ❓ Check results above<br>";
echo "</div>";
?>
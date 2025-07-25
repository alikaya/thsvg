<?php
/**
 * WordPress REST API SVG Upload Test
 * Upload SVG to WordPress from different computer
 */

// WordPress site information
$wp_site_url = 'https://your-wp-site.com';
$wp_username = 'your-wp-username';
$wp_password = 'your-wp-password'; // or Application Password

$svg_file_path = __DIR__ . '/test2.svg';

// SVG file check
if (!file_exists($svg_file_path)) {
    die("❌ simple.svg file not found!\n");
}

if (!is_readable($svg_file_path)) {
    die("❌ File is not readable: {$svg_file_path}\n");
}

echo "🚀 WordPress REST API SVG Upload Test\n";
echo "======================================\n\n";

// First, let's test WordPress connection
echo "🔍 WordPress Connection Test...\n";
$test_endpoint = rtrim($wp_site_url, '/') . '/wp-json/wp/v2';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$test_result = curl_exec($ch);
$test_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($test_http_code === 200) {
    echo "✅ WordPress REST API is accessible\n\n";
} else {
    echo "⚠️ WordPress REST API problem (HTTP: $test_http_code)\n\n";
}

/**
 * Uploads SVG to WordPress media library
 *
 * @param string $site_url WordPress site URL
 * @param string $username WordPress username
 * @param string $password Application Password
 * @param string $local_image_path Path to SVG file to upload
 * @return array Upload result
 */
function upload_svg_to_wordpress($site_url, $username, $password, $local_image_path) {
    $filename = basename($local_image_path);
    $endpoint = rtrim($site_url, '/') . '/wp-json/wp/v2/media';
    
    echo "📤 Starting Upload...\n";
    echo "Site: $site_url\n";
    echo "File: $filename\n";
    echo "Size: " . number_format(filesize($local_image_path) / 1024, 2) . " KB\n\n";
    
    // Determine MIME type
    $file_mime_type = mime_content_type($local_image_path);
    if (!$file_mime_type) {
        $file_mime_type = 'image/svg+xml'; // Default for SVG
    }
    
    // Read file contents
    $file_contents = file_get_contents($local_image_path);
    if ($file_contents === false) {
        return [
            'success' => false,
            'data' => "Could not read file contents: {$local_image_path}"
        ];
    }
    
    // Create unique boundary
    $boundary = '----WebKitFormBoundary' . uniqid();
    
    // Create multipart/form-data payload
    $payload = '';
    $payload .= '--' . $boundary . "\r\n";
    $payload .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
    $payload .= 'Content-Type: ' . $file_mime_type . "\r\n";
    $payload .= "\r\n";
    $payload .= $file_contents . "\r\n";
    $payload .= '--' . $boundary . '--' . "\r\n";
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // Set headers
    $auth_string = $username . ':' . $password;
    $headers = [
        'Authorization: Basic ' . base64_encode($auth_string),
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($payload)
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // SSL verification (for development)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Verbose output for debugging
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check cURL error
    if ($curl_error) {
        return [
            'success' => false,
            'data' => "cURL Error: " . $curl_error
        ];
    }
    
    $response_data = json_decode($result, true);
    
    echo "📊 HTTP Response Code: $http_code\n";
    
    // Evaluate response
    $response = [
        'success' => false,
        'data' => '',
    ];
    
    if ($http_code >= 200 && $http_code < 300 && isset($response_data['source_url'])) {
        $response['success'] = true;
        $response['id'] = $response_data['id'];
        $response['url'] = $response_data['source_url'];
        $response['title'] = $response_data['title']['rendered'] ?? $filename;
        $response['filesize'] = $response_data['media_details']['filesize'] ?? filesize($local_image_path);
        
        echo "✅ Upload Successful!\n";
        echo "🆔 Media ID: " . $response['id'] . "\n";
        echo "🔗 URL: " . $response['url'] . "\n";
        echo "📝 Title: " . $response['title'] . "\n";
        echo "📏 Size: " . $response['filesize'] . " bytes\n";
        
    } else {
        $response['success'] = false;
        
        echo "❌ Upload Failed!\n";
        echo "📊 HTTP Code: $http_code\n";
        
        if (is_array($response_data) && isset($response_data['message'])) {
            $response['data'] = $response_data['message'];
            echo "📝 Error Message: " . $response_data['message'] . "\n";
            
            // Analyze plugin specific errors
            if (isset($response_data['code'])) {
                echo "🔍 Error Code: " . $response_data['code'] . "\n";
                
                switch ($response_data['code']) {
                    case 'rest_upload_svg_not_allowed':
                        echo "\n💡 Solution: Your user role doesn't have SVG upload permission.\n";
                        echo "   → Add your user role in plugin settings.\n";
                        break;
                        
                    case 'rest_upload_svg_too_large':
                        echo "\n💡 Solution: File size exceeds plugin limit.\n";
                        echo "   → Increase file size limit in plugin settings.\n";
                        break;
                        
                    case 'rest_upload_svg_unsafe':
                        echo "\n💡 Solution: SVG file cannot pass security check.\n";
                        echo "   → Check SVG content or disable sanitization.\n";
                        break;
                        
                    case 'rest_cannot_upload':
                    case 'rest_upload_unknown_error':
                        echo "\n💡 Solution: THSVG plugin is not active or SVG support is missing.\n";
                        echo "   → Activate the plugin and check settings.\n";
                        break;
                        
                    case 'rest_cannot_create':
                        echo "\n💡 Solution: WordPress user permission issue.\n";
                        echo "   → Check if user has 'admin' role.\n";
                        echo "   → Check if 'administrator' role is added in plugin settings.\n";
                        echo "   → Check if Application Password is correct.\n";
                        break;
                        
                    case 'rest_forbidden':
                        echo "\n💡 Solution: Authentication problem.\n";
                        echo "   → Check username/password or Application Password.\n";
                        break;
                        
                    case 'rest_upload_file_too_big':
                        echo "\n💡 Solution: WordPress upload limit exceeded.\n";
                        echo "   → Increase upload limit in wp-config.php or .htaccess file.\n";
                        break;
                        
                    default:
                        echo "\n❓ Unknown error code. Check raw response.\n";
                }
            }
            
        } elseif (is_string($result)) {
            $response['data'] = "Raw Response: " . $result;
            echo "📄 Raw Response: " . substr($result, 0, 200) . "...\n";
        } else {
            $response['data'] = "Response came in unexpected format.";
            echo "⚠️ Response came in unexpected format.\n";
        }
    }
    
    return $response;
}

// Start upload test
$result = upload_svg_to_wordpress($wp_site_url, $wp_username, $wp_password, $svg_file_path);

// Evaluate result
if ($result['success']) {
    echo "\n🎉 Test Successful! Plugin is working correctly.\n";
    echo "📋 Uploaded file can be viewed in Media Library.\n";
} else {
    echo "\n⚠️ Test Failed!\n";
    echo "📝 Error: " . $result['data'] . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📋 CHECKLIST:\n";
echo "□ Is THSVG plugin active?\n";
echo "□ Is user role defined in plugin settings?\n";  
echo "□ Is WordPress Application Password correct?\n";
echo "□ Is site URL correct?\n";
echo "□ Does SVG file exist?\n";
echo "□ Is internet connection active?\n"; 

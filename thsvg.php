<?php
/**
 * Plugin Name: THSVG - SVG Uploader
 * Plugin URI: https://github.com/alikaya/thsvg
 * Description: Professional WordPress plugin that adds secure SVG file upload functionality.
 * Version: 1.0.0
 * Author: TH Software Team
 * License: GPL v3
 * Text Domain: thsvg
 */

// Prevent direct access outside of WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin class
class THSVGUploader {
    
    private $options;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load plugin settings
        $this->options = get_option('thsvg_uploader_settings', array(
            'enable_sanitization' => true,
            'max_file_size' => 2048, // KB
            'allowed_roles' => array('administrator', 'editor'),
            'enable_preview' => true
        ));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Add SVG MIME type support (for both normal and REST API)
        add_filter('upload_mimes', array($this, 'add_svg_mime_type'));
        
        // Security check for SVG files
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 10, 5);
        
        // Pre-upload check
        add_filter('wp_handle_upload_prefilter', array($this, 'handle_upload_prefilter'));
        
        // SVG preview in media library
        if (isset($this->options['enable_preview']) && $this->options['enable_preview']) {
            add_filter('wp_prepare_attachment_for_js', array($this, 'svg_media_thumbnails'), 10, 3);
        }
        
        // Add admin CSS
        add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
    }
    
    /**
     * Check if user has SVG upload permission
     */
    private function can_upload_svg() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        
        // Always allow admin users
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check upload_files capability
        if (!current_user_can('upload_files')) {
            return false;
        }
        
        $user_roles = $current_user->roles;
        
        foreach ($user_roles as $role) {
            if (in_array($role, $this->options['allowed_roles'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add SVG MIME type to supported file types
     */
    public function add_svg_mime_type($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
    
    /**
     * Fix MIME type control for SVG files
     */
    public function fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = '') {
        $ext = isset($data['ext']) ? $data['ext'] : '';
        
        if (strlen($ext) < 1) {
            $exploded = explode('.', $filename);
            $ext = strtolower(end($exploded));
        }
        
        if ($ext === 'svg') {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svg';
        } elseif ($ext === 'svgz') {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svgz';
        }
        
        return $data;
    }
    
    /**
     * Pre-upload file check
     */
    public function handle_upload_prefilter($file) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($ext) === 'svg') {
            error_log("THSVG DEBUG: SVG upload started - " . $file['name']);
            error_log("THSVG DEBUG: File size - " . $file['size'] . " bytes");
            error_log("THSVG DEBUG: MIME type - " . $file['type']);
            
            // User permission check
            try {
                if (!$this->can_upload_svg()) {
                    error_log("THSVG DEBUG: Permission check failed");
                    $file['error'] = 'You do not have permission to upload SVG files.';
                    return $file;
                }
                error_log("THSVG DEBUG: Permission check successful");
            } catch (Exception $e) {
                error_log("THSVG DEBUG: Exception in permission check - " . $e->getMessage());
                $file['error'] = 'Error occurred during SVG permission check.';
                return $file;
            }
            
            // File size check
            $max_size = isset($this->options['max_file_size']) ? $this->options['max_file_size'] * 1024 : 2048 * 1024;
            if ($file['size'] > $max_size) {
                $max_size_kb = isset($this->options['max_file_size']) ? $this->options['max_file_size'] : 2048;
                error_log("THSVG DEBUG: File too large - " . $file['size'] . " > " . $max_size);
                $file['error'] = sprintf(
                    'SVG file is too large. Maximum file size: %s KB',
                    $max_size_kb
                );
                return $file;
            }
            error_log("THSVG DEBUG: Size check successful");
            
            // SVG security check
            $enable_sanitization = isset($this->options['enable_sanitization']) ? $this->options['enable_sanitization'] : true;
            if ($enable_sanitization) {
                error_log("THSVG DEBUG: Sanitization starting");
                
                $original_content = file_get_contents($file['tmp_name']);
                error_log("THSVG DEBUG: Original file " . strlen($original_content) . " characters");
                
                if ($original_content && !$this->is_svg_safe($original_content)) {
                    error_log("THSVG DEBUG: SVG is not safe");
                    $file['error'] = 'SVG file failed security check.';
                    return $file;
                }
                error_log("THSVG DEBUG: SVG security check OK");
                
                if ($original_content) {
                    $sanitized_content = $this->sanitize_svg($original_content);
                    error_log("THSVG DEBUG: Sanitized file " . strlen($sanitized_content) . " characters");
                    
                    $write_result = file_put_contents($file['tmp_name'], $sanitized_content);
                    error_log("THSVG DEBUG: file_put_contents result: " . ($write_result !== false ? $write_result . " bytes written" : "FAILED"));
                    
                    // Check if file was written properly
                    $check_content = file_get_contents($file['tmp_name']);
                    error_log("THSVG DEBUG: Check - file is now " . strlen($check_content) . " characters");
                }
            } else {
                error_log("THSVG DEBUG: Sanitization disabled");
            }
            
            error_log("THSVG DEBUG: SVG processing completed");
        }
        
        return $file;
    }
    
    /**
     * Check if SVG is safe
     */
    private function is_svg_safe($svg_content) {
        // Check for dangerous patterns
        $dangerous_patterns = array(
            '/<script/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/onload=/i',
            '/onclick=/i',
            '/onmouseover=/i',
            '/onerror=/i',
            '/<meta/i',
            '/<iframe/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $svg_content)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create thumbnails for SVG files in media library
     */
    public function svg_media_thumbnails($response, $attachment, $meta) {
        if ($response['mime'] === 'image/svg+xml' && empty($response['sizes'])) {
            $svg_path = get_attached_file($attachment->ID);
            
            if (file_exists($svg_path)) {
                // Read SVG file content and get dimensions
                $svg_content = file_get_contents($svg_path);
                $svg_width = $this->get_svg_dimensions($svg_content)['width'];
                $svg_height = $this->get_svg_dimensions($svg_content)['height'];
                
                $response['sizes'] = array(
                    'full' => array(
                        'url' => $response['url'],
                        'width' => $svg_width,
                        'height' => $svg_height,
                        'orientation' => $svg_height > $svg_width ? 'portrait' : 'landscape'
                    )
                );
                
                // Use SVG for thumbnail
                $response['icon'] = $response['url'];
                $response['thumb'] = $response['url'];
            }
        }
        
        return $response;
    }
    
    /**
     * Extract dimension information from SVG file
     */
    private function get_svg_dimensions($svg_content) {
        $width = 150; // Default width
        $height = 150; // Default height
        
        // Look for width and height attributes
        if (preg_match('/width=["\']([^"\']*)["\']/', $svg_content, $width_match)) {
            $width = intval($width_match[1]);
        }
        
        if (preg_match('/height=["\']([^"\']*)["\']/', $svg_content, $height_match)) {
            $height = intval($height_match[1]);
        }
        
        // Try to get dimensions from ViewBox
        if (preg_match('/viewBox=["\']([^"\']*)["\']/', $svg_content, $viewbox_match)) {
            $viewbox = explode(' ', $viewbox_match[1]);
            if (count($viewbox) === 4) {
                $width = $width ?: intval($viewbox[2]);
                $height = $height ?: intval($viewbox[3]);
            }
        }
        
        return array(
            'width' => $width ?: 150,
            'height' => $height ?: 150
        );
    }
    
    /**
     * Add CSS for admin panel
     */
    public function admin_styles() {
        $screen = get_current_screen();
        
        if ($screen && ($screen->id === 'upload' || $screen->id === 'media' || $screen->id === 'settings_page_thsvg')) {
            echo '<style>
                .media-icon img[src$=".svg"] {
                    width: 100% !important;
                    height: auto !important;
                    max-width: 60px;
                    max-height: 60px;
                }
                
                .attachment-preview .thumbnail {
                    overflow: hidden;
                }
                
                .attachment-preview .thumbnail img[src$=".svg"] {
                    width: 100% !important;
                    height: 100% !important;
                    object-fit: contain;
                }
                
                .thsvg-admin-container {
                    max-width: 800px;
                    margin: 20px 0;
                }
                
                .thsvg-section {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                
                .thsvg-section h3 {
                    margin-top: 0;
                    color: #23282d;
                }
            </style>';
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'THSVG Uploader Settings',
            'SVG Uploader',
            'manage_options',
            'thsvg',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('thsvg_uploader_settings', 'thsvg_uploader_settings');
        
        // Handle form processing on settings page
        if (isset($_POST['submit']) && isset($_POST['thsvg_uploader_settings'])) {
            $settings = $_POST['thsvg_uploader_settings'];
            
            // Security check
            if (!wp_verify_nonce($_POST['thsvg_nonce'], 'thsvg_uploader_save')) {
                wp_die('Security check failed.');
            }
            
            // Clean and save settings
            $clean_settings = array(
                'enable_sanitization' => isset($settings['enable_sanitization']),
                'max_file_size' => intval($settings['max_file_size']),
                'allowed_roles' => isset($settings['allowed_roles']) ? $settings['allowed_roles'] : array(),
                'enable_preview' => isset($settings['enable_preview'])
            );
            
            update_option('thsvg_uploader_settings', $clean_settings);
            $this->options = $clean_settings;
            
            add_settings_error(
                'thsvg_uploader_settings',
                'settings_saved',
                'Settings saved successfully.',
                'updated'
            );
        }
    }
    
    /**
     * Admin settings page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>THSVG - SVG Uploader Settings</h1>
            
            <?php settings_errors(); ?>
            
            <div class="thsvg-admin-container">
                <form method="post" action="">
                    <?php wp_nonce_field('thsvg_uploader_save', 'thsvg_nonce'); ?>
                    
                    <div class="thsvg-section">
                        <h3>Security Settings</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">SVG Sanitization</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="thsvg_uploader_settings[enable_sanitization]" 
                                               value="1" <?php checked($this->options['enable_sanitization']); ?>>
                                        Sanitize SVG files for security (recommended)
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Maximum File Size</th>
                                <td>
                                    <input type="number" name="thsvg_uploader_settings[max_file_size]" 
                                           value="<?php echo esc_attr($this->options['max_file_size']); ?>" 
                                           min="1" max="10240"> KB
                                    <p class="description">Maximum file size for SVG files (in KB)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="thsvg-section">
                        <h3>User Permissions</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Allowed User Roles</th>
                                <td>
                                    <?php
                                    $roles = wp_roles()->get_names();
                                    foreach ($roles as $role_key => $role_name) :
                                    ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="thsvg_uploader_settings[allowed_roles][]" 
                                               value="<?php echo esc_attr($role_key); ?>"
                                               <?php checked(in_array($role_key, $this->options['allowed_roles'])); ?>>
                                        <?php echo esc_html($role_name); ?>
                                    </label>
                                    <?php endforeach; ?>
                                    <p class="description">Select user roles that can upload SVG files</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="thsvg-section">
                        <h3>Display Settings</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Media Library Preview</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="thsvg_uploader_settings[enable_preview]" 
                                               value="1" <?php checked($this->options['enable_preview']); ?>>
                                        Show SVG file previews in media library
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <?php submit_button('Save Settings'); ?>
                </form>
                
                <div class="thsvg-section">
                    <h3>Plugin Information</h3>
                    <p><strong>Version:</strong> 1.0.0</p>
                    <p><strong>Description:</strong> This plugin adds secure SVG file upload functionality to your WordPress site.</p>
                    <p><strong>Features:</strong></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>SVG and SVGZ file format support</li>
                        <li>User role-based permissions</li>
                        <li>SVG security sanitization</li>
                        <li>File size control</li>
                        <li>Media library preview</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Actions to perform during activation
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Save default settings
        $default_settings = array(
            'enable_sanitization' => true,
            'max_file_size' => 2048,
            'allowed_roles' => array('administrator', 'editor'),
            'enable_preview' => true
        );
        
        add_option('thsvg_uploader_settings', $default_settings);
        update_option('thsvg_uploader_activated', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Actions to perform during deactivation
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Delete activation record (keep settings)
        delete_option('thsvg_uploader_activated');
    }
    
    /**
     * Sanitize SVG content (for security)
     */
    public function sanitize_svg($svg_content) {
        // Remove dangerous elements (style removed - needed for SVG)
        $dangerous_tags = array(
            'script', 'object', 'embed', 'iframe',
            'frame', 'frameset', 'applet', 'base', 'form', 'input'
        );
        
        // Remove dangerous attributes (href removed - needed for SVG use)
        $dangerous_attrs = array(
            'onload', 'onclick', 'onmouseover', 'onerror', 'onchange',
            'onmousedown', 'onmouseup', 'onkeydown', 'onkeyup', 'onfocus',
            'onblur', 'onsubmit', 'onreset'
        );
        
        // Preserve XML declaration
        $xml_declaration = '';
        if (preg_match('/^<\?xml[^>]*\?>\s*/', $svg_content, $matches)) {
            $xml_declaration = $matches[0];
            $svg_content = substr($svg_content, strlen($matches[0]));
        }
        
        // Check if style tags contain JavaScript
        $svg_content = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/si', function($matches) {
            $style_content = $matches[1];
            
            // Are there JavaScript patterns in style?
            $js_patterns = array('/javascript:/i', '/expression\s*\(/i', '/eval\s*\(/i', '/function\s*\(/i');
            
            foreach ($js_patterns as $pattern) {
                if (preg_match($pattern, $style_content)) {
                    return ''; // Remove style if it contains JavaScript
                }
            }
            
            return $matches[0]; // Keep safe CSS
        }, $svg_content);
        
        // Check if link tags have JavaScript href
        $svg_content = preg_replace_callback('/<link[^>]*>/si', function($matches) {
            $link_tag = $matches[0];
            
            // Is there javascript: in href?
            if (preg_match('/href\s*=\s*["\']javascript:/i', $link_tag)) {
                return ''; // Remove if JavaScript href
            }
            
            return $link_tag; // Keep normal link
        }, $svg_content);
        
        // Remove meta refreshes
        $svg_content = preg_replace('/<meta[^>]*http-equiv[^>]*>/si', '', $svg_content);
        
        // Remove dangerous tags
        foreach ($dangerous_tags as $tag) {
            $svg_content = preg_replace('/<' . $tag . '.*?<\/' . $tag . '>/si', '', $svg_content);
            $svg_content = preg_replace('/<' . $tag . '.*?\/>/si', '', $svg_content);
        }
        
        // Remove dangerous attributes
        foreach ($dangerous_attrs as $attr) {
            $svg_content = preg_replace('/' . $attr . '=["\'][^"\']*["\']/i', '', $svg_content);
        }
        
        // Remove JavaScript hrefs (but keep normal hrefs)
        $svg_content = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svg_content);
        
        // Add XML declaration back
        return $xml_declaration . $svg_content;
    }
}

// Start the plugin
new THSVGUploader(); 

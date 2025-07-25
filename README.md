# THSVG - SVG Uploader WordPress Plugin

Professional WordPress plugin that adds secure SVG file upload functionality.

## 🚀 Features

- ✅ **SVG and SVGZ format support**
- 🔒 **Advanced security** - Malicious code sanitization
- 👥 **Role-based permissions**
- 📏 **File size control** - Configurable maximum size
- 🖼️ **Media library preview** - Direct SVG viewing
- ⚙️ **Easy management** - WordPress admin settings
- 🛡️ **Security first** - Protection against XSS and other attacks

## 📋 Installation

1. Upload `thsvg-uploader.php` to `/wp-content/plugins/thsvg-uploader/` directory
2. Go to **Plugins** > **Installed Plugins** in WordPress admin
3. Find "THSVG - SVG Uploader" and click **Activate**
4. Configure settings in **Settings** > **SVG Uploader**

## ⚙️ Settings

After activation, configure the plugin in **Settings** > **SVG Uploader**:

### Security Settings
- **SVG Sanitization**: Automatically clean malicious code (recommended)
- **Maximum File Size**: Size limit for SVG files (in KB)

### User Permissions
- **Allowed User Roles**: Select which roles can upload SVG files
  - Administrator
  - Editor
  - Author
  - Contributor

### Display Settings
- **Media Library Preview**: Show SVG previews in media library

## 🔒 Security

Multi-layered security system for safe SVG uploads:

- Removes `<script>` tags and JavaScript code
- Strips event handlers like `onclick`, `onload`
- Filters dangerous elements: `<object>`, `<embed>`, `<iframe>`
- Validates MIME types and file sizes
- User permission checks

## 📝 Usage

1. Activate the plugin and configure settings
2. Go to **Media** > **Add New** in WordPress admin
3. Upload your SVG files using drag & drop or file selector
4. Use SVG files in your content like any other media

## 🔧 Technical Details

### Supported Formats
- `.svg` - Standard SVG files
- `.svgz` - Compressed SVG files

### Requirements
- WordPress 5.0+
- PHP 7.4+

## 📊 FAQ

**Q: Why aren't my SVG files uploading?**
A: Check if the plugin is active, your user role has permission, file size is within limits, and the SVG passes security checks.

**Q: Can I disable security sanitization?**
A: Yes, but not recommended. You can disable "SVG Sanitization" in plugin settings.

**Q: What happens if I deactivate the plugin?**
A: SVG upload feature stops working, but previously uploaded SVG files remain.

## 📄 License

This plugin is distributed under GPL v3 license.

---

**Developer**: TH Software Team  
**Version**: 1.0.0  
**WordPress Compatibility**: 5.0+ 

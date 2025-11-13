# WordPress Remote Update Manager

A WordPress plugin and management platform for remotely managing WordPress updates across multiple sites.

## Components

### 1. WordPress Plugin (`wp-remote-update-plugin/`)

A WordPress plugin that exposes authenticated REST API endpoints for checking and performing updates.

**Features:**
- Generate API tokens for authentication
- REST API endpoints for checking update status
- REST API endpoints for updating core, plugins, and themes
- Secure token-based authentication

**Installation:**
1. Copy the `wp-remote-update-plugin` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Go to Settings > Remote Updates to generate API tokens

**API Endpoints:**
- `GET /wp-json/remote-update/v1/status` - Check for available updates
- `POST /wp-json/remote-update/v1/update-core` - Update WordPress core
- `POST /wp-json/remote-update/v1/update-plugins` - Update plugins
- `POST /wp-json/remote-update/v1/update-themes` - Update themes

**Authentication:**
Include the API token in the Authorization header:
```
Authorization: Bearer YOUR_TOKEN_HERE
```

### 2. Management Platform (`platform/`)

A PHP web application for managing multiple WordPress sites and their updates.

**Features:**
- Add/edit/delete WordPress sites
- Check for available updates across all sites
- Trigger updates remotely
- View update logs

**Installation:**

1. **Configure the platform:**
   - Edit `platform/includes/config.php`
   - Set your admin username and password (change the defaults!) !!!
   - Configure database settings (SQLite by default)

2. **Set up web server:**
   - Point your web server document root to the `platform/` directory
   - Ensure PHP has write permissions to `platform/data/` directory
   - Enable mod_rewrite if using Apache

3. **Access the platform:**
   - Navigate to your platform URL
   - Login with your configured credentials
   - Add WordPress sites with their API tokens

**Default Credentials:**
- Username: `admin`
- Password: `admin`

**⚠️ IMPORTANT:** Change these credentials in `platform/includes/config.php` before deploying!

## Security Notes

- API tokens are stored securely in the WordPress database
- HTTPS is recommended for all API communication
- Change default admin credentials before production use
- The platform uses SQLite by default (can be changed to MySQL)
- Session-based authentication for the management platform

## Requirements

**WordPress Plugin:**
- WordPress 5.0+
- PHP 7.2+
- WordPress file system write permissions

**Management Platform:**
- PHP 7.2+
- PDO extension (SQLite or MySQL)
- cURL extension
- Web server (Apache/Nginx)

## Database

The platform uses SQLite by default. The database file will be created automatically at `platform/data/database.sqlite`.

To use MySQL instead, edit `platform/includes/config.php` and change `DB_TYPE` to `'mysql'` and configure the MySQL connection details.

## Usage

1. Install the plugin on each WordPress site you want to manage
2. Generate an API token in WordPress admin (Settings > Remote Updates)
3. Add the site to the management platform with the API token
4. Use the dashboard to check for updates and trigger them remotely

## License

GPL v2 or later


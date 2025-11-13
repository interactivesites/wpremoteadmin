<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

require_login();

$db = new Database();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $api_token = trim($_POST['api_token'] ?? '');
    
    if (empty($name) || empty($url) || empty($api_token)) {
        $error = 'All fields are required';
    } else {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Invalid URL format';
        } else {
            $result = $db->add_site($name, $url, $api_token);
            if ($result) {
                header('Location: /index.php?added=1');
                exit;
            } else {
                $error = 'Failed to add site. URL may already exist.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Site - WordPress Update Manager</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Add WordPress Site</h1>
            <div class="header-actions">
                <a href="/index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </header>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Site Name</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    <small>Friendly name to identify this site</small>
                </div>
                
                <div class="form-group">
                    <label for="url">Site URL</label>
                    <input type="url" id="url" name="url" required value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>" placeholder="https://example.com">
                    <small>Full URL of your WordPress site</small>
                </div>
                
                <div class="form-group">
                    <label for="api_token">API Token</label>
                    <input type="text" id="api_token" name="api_token" required value="<?php echo htmlspecialchars($_POST['api_token'] ?? ''); ?>" placeholder="Your API token from WordPress plugin">
                    <small>Generate this token in WordPress admin under Settings > Remote Updates</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Site</button>
                    <a href="/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


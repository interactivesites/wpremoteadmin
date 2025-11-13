<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

require_login();

$db = new Database();
$sites = $db->get_all_sites();

// Handle site deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($db->delete_site($id)) {
        header('Location: /index.php?deleted=1');
        exit;
    }
}

// Handle check updates
$check_results = array();
if (isset($_GET['check']) && isset($_GET['id'])) {
    require_once __DIR__ . '/includes/WordPressClient.php';
    
    $id = intval($_GET['id']);
    $site = $db->get_site($id);
    
    if ($site) {
        $client = new WordPressClient($site['url'], $site['api_token']);
        $result = $client->check_status();
        
        if ($result && isset($result['success']) && $result['success']) {
            $check_results[$id] = $result['data'];
            $db->update_last_checked($id);
        } else {
            $check_results[$id] = array('error' => $result['error'] ?? 'Failed to check updates');
        }
    }
}

// Handle update actions
if (isset($_POST['action']) && isset($_POST['site_id'])) {
    require_once __DIR__ . '/includes/WordPressClient.php';
    
    $site_id = intval($_POST['site_id']);
    $site = $db->get_site($site_id);
    $action = $_POST['action'];
    
    if ($site) {
        $client = new WordPressClient($site['url'], $site['api_token']);
        $result = null;
        $update_type = '';
        
        switch ($action) {
            case 'update_core':
                $result = $client->update_core();
                $update_type = 'core';
                break;
            case 'update_plugins':
                $result = $client->update_plugins();
                $update_type = 'plugins';
                break;
            case 'update_themes':
                $result = $client->update_themes();
                $update_type = 'themes';
                break;
        }
        
        if ($result) {
            $status = (isset($result['success']) && $result['success']) ? 'success' : 'error';
            $message = json_encode($result);
            $db->add_log($site_id, $update_type, $status, $message);
            
            header('Location: /index.php?updated=' . $site_id . '&type=' . $update_type);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Update Manager</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>WordPress Update Manager</h1>
            <div class="header-actions">
                <a href="/add-site.php" class="btn btn-primary">Add Site</a>
                <a href="/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Site deleted successfully</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Update completed for site ID <?php echo intval($_GET['updated']); ?></div>
        <?php endif; ?>
        
        <div class="sites-grid">
            <?php if (empty($sites)): ?>
                <div class="empty-state">
                    <p>No sites added yet. <a href="/add-site.php">Add your first site</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($sites as $site): ?>
                    <div class="site-card">
                        <div class="site-header">
                            <h2><?php echo htmlspecialchars($site['name']); ?></h2>
                            <div class="site-actions">
                                <a href="/edit-site.php?id=<?php echo $site['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/index.php?delete=1&id=<?php echo $site['id']; ?>" 
                                   class="btn btn-small btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this site?');">Delete</a>
                            </div>
                        </div>
                        
                        <div class="site-info">
                            <p><strong>URL:</strong> <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank"><?php echo htmlspecialchars($site['url']); ?></a></p>
                            <?php if ($site['last_checked']): ?>
                                <p><strong>Last Checked:</strong> <?php echo date('Y-m-d H:i:s', strtotime($site['last_checked'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="site-updates">
                            <?php if (isset($check_results[$site['id']])): ?>
                                <?php $updates = $check_results[$site['id']]; ?>
                                <?php if (isset($updates['error'])): ?>
                                    <div class="alert alert-error"><?php echo htmlspecialchars($updates['error']); ?></div>
                                <?php else: ?>
                                    <div class="updates-info">
                                        <?php if ($updates['updates']['core']): ?>
                                            <div class="update-item">
                                                <strong>Core:</strong> Update available to <?php echo htmlspecialchars($updates['updates']['core']['version']); ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                    <input type="hidden" name="action" value="update_core">
                                                    <button type="submit" class="btn btn-small btn-primary">Update</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($updates['updates']['plugins'])): ?>
                                            <div class="update-item">
                                                <strong>Plugins:</strong> <?php echo count($updates['updates']['plugins']); ?> update(s) available
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                    <input type="hidden" name="action" value="update_plugins">
                                                    <button type="submit" class="btn btn-small btn-primary">Update All</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($updates['updates']['themes'])): ?>
                                            <div class="update-item">
                                                <strong>Themes:</strong> <?php echo count($updates['updates']['themes']); ?> update(s) available
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                    <input type="hidden" name="action" value="update_themes">
                                                    <button type="submit" class="btn btn-small btn-primary">Update All</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!$updates['updates']['core'] && empty($updates['updates']['plugins']) && empty($updates['updates']['themes'])): ?>
                                            <p class="no-updates">All up to date!</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/index.php?check=1&id=<?php echo $site['id']; ?>" class="btn btn-small">Check Updates</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


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
        $error = 'Site name, URL, and API token are required';
    } else {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Invalid URL format';
        } else {
            $site_id = $db->add_site($name, $url, $api_token);
            if ($site_id) {
                // Handle contract data if provided
                if (!empty($_POST['contract_name'])) {
                    // Handle file upload
                    $contract_file_path = null;
                    if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/contracts/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
                        if ($file_extension === 'pdf') {
                            $file_name = 'contract_' . $site_id . '_' . time() . '.pdf';
                            $file_path = $upload_dir . $file_name;
                            
                            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $file_path)) {
                                $contract_file_path = $file_name;
                            }
                        }
                    }
                    
                    // Parse options from form
                    $contract_options = array();
                    if (isset($_POST['options']) && is_array($_POST['options'])) {
                        foreach ($_POST['options'] as $option) {
                            if (!empty($option['name'])) {
                                $contract_options[] = array(
                                    'name' => $option['name'],
                                    'is_included' => isset($option['is_included']) ? 1 : 0
                                );
                            }
                        }
                    }
                    
                    $contract_data = array(
                        'contract_name' => $_POST['contract_name'] ?? '',
                        'start_date' => $_POST['start_date'] ?? '',
                        'end_date' => $_POST['end_date'] ?? '',
                        'monthly_price' => $_POST['monthly_price'] ?? 0,
                        'yearly_price' => $_POST['yearly_price'] ?? 0,
                        'payment_flag_current_year' => isset($_POST['payment_flag_current_year']) ? 1 : 0,
                        'contract_file_path' => $contract_file_path,
                        'notes' => $_POST['notes'] ?? '',
                        'options' => $contract_options
                    );
                    
                    $db->save_contract($site_id, $contract_data);
                }
                
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
    <style>
        .option-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .option-row input[type="text"] {
            flex: 1;
        }
        .option-row .option-toggle {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: #f5f5f5;
            cursor: pointer;
            border-radius: 4px;
        }
        .option-row .option-toggle.included {
            background: #00a32a;
            color: white;
            border-color: #00a32a;
        }
        .option-row .option-toggle.excluded {
            background: #d63638;
            color: white;
            border-color: #d63638;
        }
        .remove-option {
            background: #d63638;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #ddd;
        }
        .form-section h2 {
            margin-bottom: 20px;
            color: #23282d;
        }
    </style>
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
            <form method="POST" action="" enctype="multipart/form-data">
                <h2>Site Information</h2>
                
                <div class="form-group">
                    <label for="name">Site Name *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    <small>Friendly name to identify this site</small>
                </div>
                
                <div class="form-group">
                    <label for="url">Site URL *</label>
                    <input type="url" id="url" name="url" required value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>" placeholder="https://example.com">
                    <small>Full URL of your WordPress site</small>
                </div>
                
                <div class="form-group">
                    <label for="api_token">API Token *</label>
                    <input type="text" id="api_token" name="api_token" required value="<?php echo htmlspecialchars($_POST['api_token'] ?? ''); ?>" placeholder="Your API token from WordPress plugin">
                    <small>Generate this token in WordPress admin under Settings > Remote Updates</small>
                </div>
                
                <div class="form-section">
                    <h2>Hosting Contract (Optional)</h2>
                    
                    <div class="form-group">
                        <label for="contract_name">Contract Name</label>
                        <input type="text" id="contract_name" name="contract_name" value="<?php echo htmlspecialchars($_POST['contract_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" onchange="calculateYearlyPrice()">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="monthly_price">Monthly Price</label>
                        <input type="number" id="monthly_price" name="monthly_price" step="0.01" value="<?php echo htmlspecialchars($_POST['monthly_price'] ?? ''); ?>" onchange="calculateYearlyPrice()">
                    </div>
                    
                    <div class="form-group">
                        <label for="yearly_price">Yearly Price</label>
                        <input type="number" id="yearly_price" name="yearly_price" step="0.01" value="<?php echo htmlspecialchars($_POST['yearly_price'] ?? ''); ?>" onchange="calculateMonthlyPrice()">
                        <small>Auto-calculated from monthly price if left empty</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="payment_flag_current_year" value="1" <?php echo isset($_POST['payment_flag_current_year']) ? 'checked' : ''; ?>>
                            Payment received for current year
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Included Options</label>
                        <div id="options-container"></div>
                        <button type="button" class="btn btn-small" onclick="addOption()">+ Add Option</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="contract_file">Contract PDF File</label>
                        <input type="file" id="contract_file" name="contract_file" accept=".pdf">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Site</button>
                    <a href="/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let optionIndex = 0;
        
        function addOption() {
            const container = document.getElementById('options-container');
            const row = document.createElement('div');
            row.className = 'option-row';
            row.innerHTML = `
                <input type="text" name="options[${optionIndex}][name]" placeholder="Option name">
                <button type="button" class="option-toggle included" data-index="${optionIndex}" onclick="toggleOption(${optionIndex})">+</button>
                <input type="hidden" name="options[${optionIndex}][is_included]" id="option_${optionIndex}_included" value="1">
                <button type="button" class="remove-option" onclick="removeOption(this)">Remove</button>
            `;
            container.appendChild(row);
            optionIndex++;
        }
        
        function removeOption(button) {
            button.closest('.option-row').remove();
        }
        
        function toggleOption(index) {
            const toggle = document.querySelector(`.option-toggle[data-index="${index}"]`);
            const hidden = document.getElementById(`option_${index}_included`);
            
            if (toggle.classList.contains('included')) {
                toggle.classList.remove('included');
                toggle.classList.add('excluded');
                toggle.textContent = '-';
                hidden.value = '0';
            } else {
                toggle.classList.remove('excluded');
                toggle.classList.add('included');
                toggle.textContent = '+';
                hidden.value = '1';
            }
        }
        
        function calculateYearlyPrice() {
            const monthly = parseFloat(document.getElementById('monthly_price').value) || 0;
            if (monthly > 0) {
                document.getElementById('yearly_price').value = (monthly * 12).toFixed(2);
            }
        }
        
        function calculateMonthlyPrice() {
            const yearly = parseFloat(document.getElementById('yearly_price').value) || 0;
            if (yearly > 0) {
                document.getElementById('monthly_price').value = (yearly / 12).toFixed(2);
            }
        }
    </script>
</body>
</html>


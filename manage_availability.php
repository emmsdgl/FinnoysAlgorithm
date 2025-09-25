<?php
require_once 'db.php';
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = getDB();
$message = '';
// Action can come from POST (for forms) or GET (for navigation)
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$edit_id = $_GET['edit'] ?? null;

// Preserve edit context across form submissions
$current_edit_id = $_GET['edit'] ?? (isset($_POST['edit_id']) ? $_POST['edit_id'] : null);

// Get all active employees for dropdown
$employees = $db->query("
    SELECT id, name FROM employees 
    WHERE end_date IS NULL OR end_date > CURRENT_DATE 
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle add/edit form submissions (only if not already handled by delete)
if ($_POST && $action !== 'delete') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
    
    $employee_id = intval($_POST['employee_id']);
    $date = $_POST['date'];
    $time_in = $_POST['time_in'];
    $time_out = $_POST['time_out'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    // Trim inputs
    $date = trim($date);
    $time_in = trim($time_in);
    $time_out = trim($time_out);
    
    // Server-side validation
    $validation_errors = [];
    
    if (empty($employee_id)) $validation_errors[] = 'Employee is required';
    if (empty($date)) $validation_errors[] = 'Date is required';
    if (empty($time_in)) $validation_errors[] = 'Time In is required';
    if (empty($time_out)) $validation_errors[] = 'Time Out is required';
    
    // Validate date using DateTime
    if (!empty($date)) {
        $date_check = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_check || $date_check->format('Y-m-d') !== $date) {
            $validation_errors[] = 'Invalid date';
        }
    }
    
    // Validate time using DateTime
    if (!empty($time_in)) {
        $time_in_check = DateTime::createFromFormat('H:i', $time_in);
        if (!$time_in_check || $time_in_check->format('H:i') !== $time_in) {
            $validation_errors[] = 'Invalid time in format (use HH:MM)';
        }
    }
    
    if (!empty($time_out)) {
        $time_out_check = DateTime::createFromFormat('H:i', $time_out);
        if (!$time_out_check || $time_out_check->format('H:i') !== $time_out) {
            $validation_errors[] = 'Invalid time out format (use HH:MM)';
        }
    }
    
    // Validate time_in < time_out using DateTime objects
    if (!empty($time_in) && !empty($time_out) && empty($validation_errors)) {
        $time_in_obj = DateTime::createFromFormat('H:i', $time_in);
        $time_out_obj = DateTime::createFromFormat('H:i', $time_out);
        if ($time_in_obj && $time_out_obj && $time_in_obj >= $time_out_obj) {
            $validation_errors[] = 'Time Out must be later than Time In';
        }
    }
    
    // Combine time_in and time_out into time_slot format
    $time_slot = $time_in . '-' . $time_out;
    
    if (empty($validation_errors)) {
        try {
            if ($action === 'add') {
                // Check for overlapping availability for same employee on same date
                $overlap_stmt = $db->prepare("
                    SELECT id, time_slot FROM employee_availability 
                    WHERE employee_id = ? AND date = ?
                ");
                $overlap_stmt->execute([$employee_id, $date]);
                $existing_records = $overlap_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $has_overlap = false;
                foreach ($existing_records as $record) {
                    if (strpos($record['time_slot'], '-') !== false) {
                        list($existing_start, $existing_end) = explode('-', trim($record['time_slot']));
                        $existing_start = trim($existing_start);
                        $existing_end = trim($existing_end);
                        
                        // Convert to DateTime objects for accurate comparison
                        $time_in_obj = DateTime::createFromFormat('H:i', $time_in);
                        $time_out_obj = DateTime::createFromFormat('H:i', $time_out);
                        $existing_start_obj = DateTime::createFromFormat('H:i', $existing_start);
                        $existing_end_obj = DateTime::createFromFormat('H:i', $existing_end);
                        
                        // Check for time overlap using DateTime objects
                        if ($time_in_obj && $time_out_obj && $existing_start_obj && $existing_end_obj) {
                            if ($time_in_obj < $existing_end_obj && $time_out_obj > $existing_start_obj) {
                                $has_overlap = true;
                                break;
                            }
                        }
                    }
                }
                
                if ($has_overlap) {
                    $message = '<div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This time slot overlaps with existing availability!
                    </div>';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO employee_availability (employee_id, date, time_slot, is_available) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$employee_id, $date, $time_slot, $is_available]);
                    // Redirect after successful add (Post-Redirect-Get pattern)
                    header('Location: manage_availability.php?success=added');
                    exit;
                }
            } elseif ($action === 'edit' && $current_edit_id) {
                // Check for overlapping availability (excluding current record)
                $overlap_stmt = $db->prepare("
                    SELECT id, time_slot FROM employee_availability 
                    WHERE employee_id = ? AND date = ? AND id != ?
                ");
                $overlap_stmt->execute([$employee_id, $date, $current_edit_id]);
                $existing_records = $overlap_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $has_overlap = false;
                foreach ($existing_records as $record) {
                    if (strpos($record['time_slot'], '-') !== false) {
                        list($existing_start, $existing_end) = explode('-', trim($record['time_slot']));
                        $existing_start = trim($existing_start);
                        $existing_end = trim($existing_end);
                        
                        // Convert to DateTime objects for accurate comparison
                        $time_in_obj = DateTime::createFromFormat('H:i', $time_in);
                        $time_out_obj = DateTime::createFromFormat('H:i', $time_out);
                        $existing_start_obj = DateTime::createFromFormat('H:i', $existing_start);
                        $existing_end_obj = DateTime::createFromFormat('H:i', $existing_end);
                        
                        // Check for time overlap using DateTime objects
                        if ($time_in_obj && $time_out_obj && $existing_start_obj && $existing_end_obj) {
                            if ($time_in_obj < $existing_end_obj && $time_out_obj > $existing_start_obj) {
                                $has_overlap = true;
                                break;
                            }
                        }
                    }
                }
                
                if ($has_overlap) {
                    $message = '<div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This time slot overlaps with existing availability!
                    </div>';
                } else {
                    $stmt = $db->prepare("
                        UPDATE employee_availability 
                        SET employee_id = ?, date = ?, time_slot = ?, is_available = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$employee_id, $date, $time_slot, $is_available, $current_edit_id]);
                    // Redirect after successful edit (Post-Redirect-Get pattern)
                    header('Location: manage_availability.php?success=updated');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> An error occurred. Please try again.
            </div>';
            error_log('Database error in manage_availability.php: ' . $e->getMessage());
        }
    } else {
        $message = '<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> ' . implode('<br>', $validation_errors) . '
        </div>';
    }
}

// Handle delete action first (POST only for security)
if ($_POST && $action === 'delete' && isset($_POST['delete_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM employee_availability WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        // Redirect after successful delete (Post-Redirect-Get pattern)
        header('Location: manage_availability.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> Error deleting record.
        </div>';
        error_log('Delete error: ' . $e->getMessage());
    }
}

// Handle success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Availability record added successfully!
            </div>';
            break;
        case 'updated':
            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Availability record updated successfully!
            </div>';
            break;
        case 'deleted':
            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Availability record deleted successfully!
            </div>';
            break;
    }
}

// Get data for edit form
$edit_data = null;
if ($action === 'edit' && $current_edit_id) {
    $stmt = $db->prepare("
        SELECT ea.*, e.name as employee_name 
        FROM employee_availability ea
        JOIN employees e ON ea.employee_id = e.id
        WHERE ea.id = ?
    ");
    $stmt->execute([$current_edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        // Parse time_slot into time_in and time_out
        if (strpos($edit_data['time_slot'], '-') !== false) {
            list($edit_data['time_in'], $edit_data['time_out']) = explode('-', $edit_data['time_slot']);
        }
    }
}

// Get all availability records for list view
$availability_records = [];
if ($action === 'list') {
    // Date filter logic
    $filter_date = $_GET['filter_date'] ?? '';

    $query = "
        SELECT ea.id, ea.date, ea.time_slot, ea.is_available,
               e.name AS employee_name,
               COALESCE(teams.team_names, 'No Team') AS team_name
        FROM employee_availability ea
        JOIN employees e ON ea.employee_id = e.id
        LEFT JOIN (
            SELECT tm.employee_id, 
                   GROUP_CONCAT(DISTINCT t.team_name ORDER BY t.team_name SEPARATOR ', ') AS team_names
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.id
            GROUP BY tm.employee_id
        ) teams ON teams.employee_id = e.id
    ";

    $params = [];
    if (!empty($filter_date)) {
        $query .= " WHERE ea.date = ? ";
        $params[] = $filter_date;
    }

    $query .= " GROUP BY ea.id, ea.employee_id, e.name, ea.date, ea.time_slot, ea.is_available 
                ORDER BY ea.date DESC, ea.time_slot ASC, e.name ASC ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $availability_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse time_slot for display
    foreach ($availability_records as $key => $record) {
        if (strpos($record['time_slot'], '-') !== false) {
            list($availability_records[$key]['time_in'], $availability_records[$key]['time_out']) = explode('-', $record['time_slot']);
        } else {
            $availability_records[$key]['time_in'] = $record['time_slot'];
            $availability_records[$key]['time_out'] = '';
        }
    }

}

// Helper function to get employee name
function getEmployeeName($employees, $employee_id) {
    foreach ($employees as $emp) {
        if ($emp['id'] == $employee_id) {
            return $emp['name'];
        }
    }
    return 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Availability Management - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .main-card { background: white; border-radius: 15px; box-shadow: 0 0 30px rgba(0,0,0,0.1); }
        .navbar-brand { font-weight: bold; }
        .availability-badge { font-size: 0.9em; }
        .time-slot { font-family: monospace; font-weight: bold; }
        .action-buttons .btn { margin-right: 5px; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-users-cog"></i> OptiCrew WMS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> Employees
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php#employees"><i class="fas fa-list"></i> View All</a></li>
                            <li><a class="dropdown-item" href="add_employee.php"><i class="fas fa-user-plus"></i> Add Employee</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="manage_availability.php"><i class="fas fa-calendar-check"></i> Manage Availability</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-tasks"></i> Tasks
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="client_booking.php"><i class="fas fa-calendar-plus"></i> Client Booking</a></li>
                            <li><a class="dropdown-item" href="admin_add_task.php"><i class="fas fa-plus-circle"></i> Recurring Clients</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="optimization.php"><i class="fas fa-magic"></i> Run Optimization</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="main-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-calendar-check text-primary"></i> Employee Availability Management</h2>
                        <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Availability
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php echo $message; ?>

                    <?php if ($action === 'list'): ?>
                    <!-- Date Filter -->
                    <form method="GET" class="mb-3 d-flex align-items-center" action="manage_availability.php">
                        <input type="hidden" name="action" value="list">
                        <label for="filter_date" class="me-2 fw-bold">Filter by Date:</label>
                        <input type="date" name="filter_date" id="filter_date" class="form-control me-2" style="max-width: 200px;" value="<?php echo htmlspecialchars($_GET['filter_date'] ?? ''); ?>">
                        <button type="submit" class="btn btn-outline-primary me-2">Filter</button>
                        <?php if (!empty($_GET['filter_date'])): ?>
                        <a href="manage_availability.php?action=list" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                    <!-- List View -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee</th>
                                    <th>Team</th>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($availability_records)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times fa-2x"></i><br>
                                        No availability records found for this date.<br>
                                        <a href="?action=add" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus"></i> Add First Record
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($availability_records as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['employee_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['team_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                    <td class="time-slot"><?php echo htmlspecialchars($record['time_in']); ?></td>
                                    <td class="time-slot"><?php echo htmlspecialchars($record['time_out']); ?></td>
                                    <td>
                                        <?php if ($record['is_available']): ?>
                                        <span class="badge bg-success availability-badge">
                                            <i class="fas fa-check"></i> Available
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger availability-badge">
                                            <i class="fas fa-times"></i> Unavailable
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="?action=edit&edit=<?php echo $record['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this availability record?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="delete_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php elseif ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Form -->
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <h4 class="mb-4">
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> text-primary"></i>
                                <?php echo ucfirst($action); ?> Availability Record
                            </h4>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="<?php echo $action; ?>">
                                <?php if ($action === 'edit' && $current_edit_id): ?>
                                <input type="hidden" name="edit_id" value="<?php echo (int)$current_edit_id; ?>">
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="employee_id" class="form-label">Employee *</label>
                                            <select class="form-control" name="employee_id" id="employee_id" required>
                                                <option value="">Select Employee</option>
                                                <?php foreach ($employees as $employee): ?>
                                                <option value="<?php echo $employee['id']; ?>" 
                                                        <?php echo ($edit_data && $edit_data['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($employee['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date" class="form-label">Date *</label>
                                            <input type="date" class="form-control" name="date" id="date" 
                                                   value="<?php echo $edit_data ? $edit_data['date'] : date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="time_in" class="form-label">Time In *</label>
                                            <input type="time" class="form-control" name="time_in" id="time_in" 
                                                   value="<?php echo $edit_data ? $edit_data['time_in'] : '09:00'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="time_out" class="form-label">Time Out *</label>
                                            <input type="time" class="form-control" name="time_out" id="time_out" 
                                                   value="<?php echo $edit_data ? $edit_data['time_out'] : '17:00'; ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_available" id="is_available" 
                                               <?php echo (!$edit_data || $edit_data['is_available']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_available">
                                            <i class="fas fa-check-circle text-success"></i> Employee is available during this time
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="?action=list" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to List
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo ucfirst($action); ?> Record
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation - only for add/edit forms, not delete forms
        document.addEventListener('DOMContentLoaded', function() {
            const timeInField = document.getElementById('time_in');
            const timeOutField = document.getElementById('time_out');
            
            if (timeInField && timeOutField) {
                const form = timeInField.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const timeIn = timeInField.value;
                        const timeOut = timeOutField.value;
                        
                        if (timeIn && timeOut && timeIn >= timeOut) {
                            e.preventDefault();
                            alert('Time Out must be later than Time In');
                            return false;
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
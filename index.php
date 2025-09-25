<?php
require_once 'db.php';
session_start();

$db = getDB();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Basic authorization check (you may want to enhance this with proper user roles)
function isAuthorizedUser() {
    // For now, allow access since this is an admin dashboard - enhance this with proper role checking
    // In a production environment, you should check for valid user authentication and admin role
    return true; // TODO: Replace with proper authentication like: isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'
}

// Get counts for dashboard
$emp_stmt = $db->query("SELECT COUNT(*) as count FROM employees WHERE end_date IS NULL OR end_date > CURRENT_DATE");
$active_employees = $emp_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$task_stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status != 'completed'");
$active_tasks = $task_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$team_stmt = $db->query("SELECT COUNT(*) as count FROM teams");
$total_teams = $team_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get all employees for main table
$employees = $db->query("
    SELECT id, name, skills, hourly_rate, max_hours_per_3weeks, 
           start_date, end_date, principal_duties, collective_agreement
    FROM employees 
    ORDER BY start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all tasks with team information for main table
$tasks = $db->query("
    SELECT t.*, team.team_name 
    FROM tasks t 
    LEFT JOIN teams team ON t.team_id = team.id 
    ORDER BY 
        CASE t.priority_level 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END, 
        t.scheduled_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get teams for assignment dropdown
$teams = $db->query("SELECT * FROM teams ORDER BY team_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle optimization request
$optimization_message = '';
$optimization_results = null;
if ($_POST && isset($_POST['run_optimization'])) {
    require_once 'HybridScheduler.php';
    $scheduler = new HybridScheduler();
    $results = $scheduler->runOptimization();
    $optimization_results = $results; // Store for detailed display
    
    if ($results['success']) {
        $assignedCount = count($results['assignments']);
        $totalTasks = count($results['debug_info']['rule_based_filtering']);
        $unassignedCount = count($results['debug_info']['unassigned_tasks']);
        
        $optimization_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> <strong>Optimization Complete!</strong><br>' . 
                               $results['message'] . '<br>' .
                               "<strong>Results:</strong> {$assignedCount} tasks assigned, {$unassignedCount} unassigned out of {$totalTasks} total tasks.</div>";
    } else {
        $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Optimization Failed:</strong> ' . $results['message'] . '</div>';
    }
    
    // Refresh tasks to show updated assignments
    $tasks = $db->query("
        SELECT t.*, team.team_name 
        FROM tasks t 
        LEFT JOIN teams team ON t.team_id = team.id 
        ORDER BY 
            CASE t.priority_level 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END, 
            t.scheduled_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Handle task deletion
if ($_POST && isset($_POST['delete_task'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Security error: Invalid request token.</div>';
        error_log('CSRF token mismatch in task deletion');
    } 
    // Authorization check
    elseif (!isAuthorizedUser()) {
        $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Access denied: You do not have permission to delete tasks.</div>';
        error_log('Unauthorized task deletion attempt');
    }
    else {
        $task_id = intval($_POST['task_id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            
            // Check if task was actually deleted
            if ($stmt->rowCount() > 0) {
                $optimization_message = '<div class="alert alert-success"><i class="fas fa-trash"></i> Task deleted successfully!</div>';
            } else {
                $optimization_message = '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> Task not found or already deleted.</div>';
            }
            
            // Refresh tasks
            $tasks = $db->query("
                SELECT t.*, team.team_name 
                FROM tasks t 
                LEFT JOIN teams team ON t.team_id = team.id 
                ORDER BY 
                    CASE t.priority_level 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'low' THEN 4 
                    END, 
                    t.scheduled_date ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Also update dashboard stats
            $task_stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status != 'completed'");
            $active_tasks = $task_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
        } catch (PDOException $e) {
            $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error deleting task: Database error occurred.</div>';
            error_log('Task deletion error: ' . $e->getMessage());
        }
    }
}

// Handle manual team assignment
if ($_POST && isset($_POST['assign_task'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Security error: Invalid request token.</div>';
        error_log('CSRF token mismatch in task assignment');
    }
    // Authorization check  
    elseif (!isAuthorizedUser()) {
        $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Access denied: You do not have permission to assign tasks.</div>';
        error_log('Unauthorized task assignment attempt');
    }
    else {
        $task_id = intval($_POST['task_id']);
        $team_id = !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
        
        try {
            $stmt = $db->prepare("UPDATE tasks SET team_id = ? WHERE id = ?");
            $stmt->execute([$team_id, $task_id]);
            $optimization_message = '<div class="alert alert-info"><i class="fas fa-check"></i> Task assignment updated successfully!</div>';
            
            // Refresh tasks
            $tasks = $db->query("
                SELECT t.*, team.team_name 
                FROM tasks t 
                LEFT JOIN teams team ON t.team_id = team.id 
                ORDER BY 
                    CASE t.priority_level 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'low' THEN 4 
                    END, 
                    t.scheduled_date ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $optimization_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error updating assignment: Database error occurred.</div>';
            error_log('Task assignment error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiCrew - Fin-noys Workforce Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .dashboard-card { background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .stat-card { border-left: 4px solid #007bff; }
        .priority-urgent { background-color: #dc3545; color: white; }
        .priority-high { background-color: #fd7e14; color: white; }
        .priority-medium { background-color: #ffc107; color: black; }
        .priority-low { background-color: #28a745; color: white; }
        .navbar-brand { font-weight: bold; }
        .status-pending { color: #ffc107; }
        .status-in_progress { color: #007bff; }
        .status-completed { color: #28a745; }
        .status-on_hold { color: #dc3545; }
        .sortable { 
            cursor: pointer; 
            user-select: none;
            transition: background-color 0.2s ease;
        }
        .sortable:hover { 
            background-color: #e9ecef !important; 
        }
        .sortable.asc i::before { content: "\f0de"; }
        .sortable.desc i::before { content: "\f0dd"; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .filter-row { border-bottom: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-users"></i> OptiCrew WMS - Fin-noys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> Employees
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php#employees"><i class="fas fa-list"></i> View All</a></li>
                            <li><a class="dropdown-item" href="add_employee.php"><i class="fas fa-user-plus"></i> Add Employee</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="manage_availability.php"><i class="fas fa-calendar-check"></i> Manage Availability</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-tasks"></i> Tasks
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php#tasks"><i class="fas fa-list"></i> View All</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="client_booking.php"><i class="fas fa-calendar-plus"></i> Client Booking</a></li>
                            <li><a class="dropdown-item" href="admin_add_task.php"><i class="fas fa-building"></i> Recurring Tasks</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users-cog"></i> Teams
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="list_teams.php"><i class="fas fa-list"></i> View All Teams</a></li>
                            <li><a class="dropdown-item" href="create_team.php"><i class="fas fa-users"></i> Create Team</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="run_optimization" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-robot"></i> Run Optimization
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card dashboard-card p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Active Employees</h6>
                            <h3 class="text-primary"><?php echo $active_employees; ?></h3>
                        </div>
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card dashboard-card p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Pending Tasks</h6>
                            <h3 class="text-warning"><?php echo $active_tasks; ?></h3>
                        </div>
                        <i class="fas fa-tasks fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card dashboard-card p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Active Teams</h6>
                            <h3 class="text-info"><?php echo $total_teams; ?></h3>
                        </div>
                        <i class="fas fa-users-cog fa-2x text-info"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card dashboard-card p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">System Status</h6>
                            <h5 class="text-success"><i class="fas fa-check-circle"></i> Online</h5>
                        </div>
                        <i class="fas fa-server fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card p-3">
                    <h5><i class="fas fa-cogs"></i> Quick Actions</h5>
                    <div class="row">
                        <div class="col-md-2">
                            <a href="add_employee.php" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-user-plus"></i><br>Add Employee
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="client_booking.php" class="btn btn-info w-100 mb-2">
                                <i class="fas fa-calendar-alt"></i><br>Client Booking
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="admin_add_task.php" class="btn btn-warning w-100 mb-2">
                                <i class="fas fa-building"></i><br>Recurring Clients
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="create_team.php" class="btn btn-secondary w-100 mb-2">
                                <i class="fas fa-users"></i><br>Create Team
                            </a>
                        </div>
                        <div class="col-md-2">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="run_optimization" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <button type="submit" class="btn btn-danger w-100 mb-2">
                                    <i class="fas fa-robot"></i><br>Run Optimization
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optimization Results -->
        <?php if ($optimization_message): ?>
        <div class="row mb-4">
            <div class="col-12">
                <?php echo $optimization_message; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detailed Optimization Results -->
        <?php if ($optimization_results && $optimization_results['success']): ?>
        <div class="row mb-4">
            <div class="col-12 text-end mb-3">
                <a href="export_csv.php" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-download"></i> Export Schedule to CSV
                </a>
            </div>
            <!-- Assigned Tasks -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-check-circle"></i> Assigned Tasks (<?php echo count($optimization_results['debug_info']['final_assignments']); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Task</th>
                                        <th>Team</th>
                                        <th>Priority</th>
                                        <th>Time</th>
                                        <th>Fitness</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($optimization_results['debug_info']['final_assignments'] as $assignment): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $assignment['task_id']; ?></strong><br>
                                            <small><?php echo htmlspecialchars($assignment['task_title']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($assignment['assigned_team']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge priority-<?php echo $assignment['priority']; ?>">
                                                <?php echo ucfirst($assignment['priority']); ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo $assignment['scheduled_time']; ?></small></td>
                                        <td><small><?php echo number_format($assignment['fitness_score'], 3); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Unassigned Tasks -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-exclamation-triangle"></i> Unassigned Tasks (<?php echo count($optimization_results['debug_info']['unassigned_tasks']); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($optimization_results['debug_info']['unassigned_tasks'])): ?>
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                            All tasks successfully assigned!
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Task</th>
                                        <th>Priority</th>
                                        <th>Skills</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($optimization_results['debug_info']['unassigned_tasks'] as $unassigned): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $unassigned['task_id']; ?></strong><br>
                                            <small><?php echo htmlspecialchars($unassigned['task_title']); ?></small><br>
                                            <small class="text-muted"><?php echo $unassigned['scheduled_time']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge priority-<?php echo $unassigned['priority']; ?>">
                                                <?php echo ucfirst($unassigned['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo implode(', ', $unassigned['required_skills']); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-danger"><?php echo $unassigned['reason']; ?></small><br>
                                            <small class="text-muted">Teams available: <?php echo $unassigned['feasible_teams_count']; ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-bug"></i> Algorithm Debug Log</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="debugAccordion">
                            <!-- Rule-Based Filtering -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="ruleBasedHeader">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ruleBasedCollapse">
                                        <i class="fas fa-filter me-2"></i> Phase 1: Rule-Based Filtering (<?php echo count($optimization_results['debug_info']['rule_based_filtering']); ?> tasks processed)
                                    </button>
                                </h2>
                                <div id="ruleBasedCollapse" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                                    <div class="accordion-body">
                                        <?php foreach ($optimization_results['debug_info']['rule_based_filtering'] as $taskDebug): ?>
                                        <div class="border rounded p-3 mb-3">
                                            <h6><strong>Task #<?php echo $taskDebug['task_id']; ?>:</strong> <?php echo htmlspecialchars($taskDebug['task_title']); ?></h6>
                                            <!-- <p><strong>Required Skills:</strong> <?php echo implode(', ', $taskDebug['required_skills']); ?></p> -->
                                            <p><strong>Priority:</strong> <span class="badge priority-<?php echo $taskDebug['priority']; ?>"><?php echo ucfirst($taskDebug['priority']); ?></span></p>
                                            <p><strong>Scheduled:</strong> <?php echo $taskDebug['scheduled_time']; ?></p>
                                            <p><strong>Feasible Teams:</strong> <?php echo $taskDebug['feasible_teams_count']; ?></p>
                                            <?php if (!empty($taskDebug['feasible_teams'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr><th>Team</th><th>Suitability Score</th><th>Availability</th><th>Skills Status</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($taskDebug['feasible_teams'] as $team): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                                            <td><?php echo $team['suitability_score']; ?></td>
                                                            <td><?php echo number_format($team['availability_score'], 2); ?></td>
                                                            <td>
                                                                <?php if (empty($team['skill_match']['missing'])): ?>
                                                                <span class="text-success">✓ All skills</span>
                                                                <?php else: ?>
                                                                <span class="text-warning">Missing: <?php echo implode(', ', $team['skill_match']['missing']); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- GA Results -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="gaHeader">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#gaCollapse">
                                        <i class="fas fa-dna me-2"></i> Phase 2: Genetic Algorithm Optimization
                                    </button>
                                </h2>
                                <div id="gaCollapse" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Optimization Statistics</h6>
                                                <ul>
                                                    <li>Assignments Made: <?php echo $optimization_results['debug_info']['genetic_algorithm']['optimization_stats']['assignments_made'] ?? 'N/A'; ?></li>
                                                    <li>Coverage Rate: <?php echo $optimization_results['debug_info']['genetic_algorithm']['optimization_stats']['coverage_rate'] ?? 'N/A'; ?>%</li>
                                                    <li>Average Fitness: <?php echo $optimization_results['debug_info']['genetic_algorithm']['optimization_stats']['average_fitness'] ?? 'N/A'; ?></li>
                                                    <li>Execution Time: <?php echo number_format($optimization_results['execution_time'], 3); ?> seconds</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Priority Distribution</h6>
                                                <?php $priorityDist = $optimization_results['debug_info']['genetic_algorithm']['optimization_stats']['priority_distribution'] ?? []; ?>
                                                <?php foreach ($priorityDist as $priority => $count): ?>
                                                <p><?php echo ucfirst($priority); ?>: <strong><?php echo $count; ?> tasks</strong></p>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Tables -->
        <div class="row">
            <!-- Employees Table -->
            <div class="col-12 mb-4" id="employees">
                <div class="card dashboard-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users"></i> Employees</h5>
                        <div class="d-flex gap-2">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control form-control-sm" id="employeeSearch" 
                                       placeholder="Search employees...">
                            </div>
                            <a href="add_employee.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Add Employee
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="sortable" data-sort="id">ID <i class="fas fa-sort"></i></th>
                                        <th class="sortable" data-sort="name">Name <i class="fas fa-sort"></i></th>
                                        <th>Skills</th>
                                        <th class="sortable" data-sort="hourly_rate">Hourly Rate <i class="fas fa-sort"></i></th>
                                        <th class="sortable" data-sort="max_hours">Max Hours/3 Weeks <i class="fas fa-sort"></i></th>
                                        <th class="sortable" data-sort="start_date">Contract Dates <i class="fas fa-sort"></i></th>
                                        <th>Duties</th>
                                        <th>Collective Agreement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><?php echo $emp['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                                        <td>
                                            <small>
                                            <?php 
                                            $skills = json_decode($emp['skills'], true);
                                            echo is_array($skills) ? implode(', ', array_slice($skills, 0, 3)) : 'None';
                                            if (is_array($skills) && count($skills) > 3) echo '...';
                                            ?>
                                            </small>
                                        </td>
                                        <td>€<?php echo number_format($emp['hourly_rate'], 2); ?></td>
                                        <td><?php echo $emp['max_hours_per_3weeks']; ?>h</td>
                                        <td>
                                            <small>
                                                Start: <?php echo date('M j, Y', strtotime($emp['start_date'])); ?><br>
                                                <?php if ($emp['end_date']): ?>
                                                End: <?php echo date('M j, Y', strtotime($emp['end_date'])); ?>
                                                <?php else: ?>
                                                <span class="text-success">Active</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($emp['principal_duties'], 0, 50)); ?>...</small></td>
                                        <td><small><?php echo htmlspecialchars($emp['collective_agreement']); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="col-12" id="tasks">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-tasks"></i> Tasks</h5>
                            <div class="d-flex gap-2">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="run_optimization" value="1">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-robot"></i> Run Optimization
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="taskSearch" placeholder="Search tasks...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="priorityFilter">
                                    <option value="">All Priorities</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" id="dateFilter" placeholder="Filter by date">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <!-- <th>Required Skills</th> -->
                                        <th>Priority</th>
                                        <th>Date</th>
                                        <th>Time Slot</th>
                                        <th>Status</th>
                                        <th>Assigned Team</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                    <tr data-date="<?php echo $task['scheduled_date'] ?? ''; ?>">
                                        <td><?php echo $task['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($task['title']); ?></strong></td>
                                        <!-- ...existing code... -->
                                        <td>
                                            <span class="badge priority-<?php echo $task['priority_level']; ?>">
                                                <?php echo ucfirst($task['priority_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $task['scheduled_date'] ? date('M j, Y', strtotime($task['scheduled_date'])) : '<small class="text-muted">Not scheduled</small>'; ?></td>
                                        <td><small><?php echo $task['time_slot'] ? $task['time_slot'] : 'TBD'; ?></small></td>
                                        <td>
                                            <i class="fas fa-circle status-<?php echo $task['status']; ?>"></i>
                                            <small><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($task['team_name']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($task['team_name']); ?></span>
                                            <?php else: ?>
                                                <small class="text-muted">Unassigned</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 align-items-center">
                                                <!-- Team Assignment Dropdown -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="assign_task" value="1">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <select name="team_id" class="form-select form-select-sm" style="width: 120px; font-size: 11px;" onchange="this.form.submit()">
                                                        <option value="">Unassigned</option>
                                                        <?php foreach ($teams as $team): ?>
                                                        <option value="<?php echo $team['id']; ?>" <?php echo ($task['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(substr($team['team_name'], 0, 12)); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                                <!-- Delete Button -->
                                                <form method="POST" class="d-inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars($task['title']); ?>')">
                                                    <input type="hidden" name="delete_task" value="1">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Task">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Employee Search Functionality
        document.getElementById('employeeSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const employeeTable = document.querySelector('#employees table tbody');
            const rows = employeeTable.querySelectorAll('tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Task Search and Filter Functionality
        function filterTasks() {
            const searchTerm = document.getElementById('taskSearch').value.toLowerCase();
            const priorityFilter = document.getElementById('priorityFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const taskTable = document.querySelector('#tasks table tbody');
            const rows = taskTable.querySelectorAll('tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const priority = row.querySelector('.badge').textContent.toLowerCase().trim();
                const statusCell = row.cells[6]; // Status column
                const status = statusCell.textContent.toLowerCase().replace(/\s+/g, '_').trim();
                let show = true;
                // Text search
                if (searchTerm && !text.includes(searchTerm)) {
                    show = false;
                }
                // Priority filter
                if (priorityFilter && priority !== priorityFilter) {
                    show = false;
                }
                // Status filter
                if (statusFilter && !status.includes(statusFilter)) {
                    show = false;
                }
                // Date filter
                const rowDateRaw = row.getAttribute('data-date');
                if (dateFilter) {
                    if (!rowDateRaw || rowDateRaw === '' || rowDateRaw === '0000-00-00') {
                        show = false;
                    } else {
                        show = show && (rowDateRaw === dateFilter);
                    }
                }
                row.style.display = show ? '' : 'none';
            });
        }

        // Add event listeners for task filters
        document.getElementById('taskSearch').addEventListener('input', filterTasks);
        document.getElementById('priorityFilter').addEventListener('change', filterTasks);
        document.getElementById('statusFilter').addEventListener('change', filterTasks);
        document.getElementById('dateFilter').addEventListener('change', filterTasks);

        // Clear all filters
        function clearFilters() {
            document.getElementById('taskSearch').value = '';
            document.getElementById('priorityFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            filterTasks();
        }

        // Table Sorting Functionality
        function sortTable(table, columnIndex, isNumeric = false, isDate = false) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const header = table.querySelector(`th:nth-child(${columnIndex + 1})`);
            
            // Toggle sort direction
            const isAscending = !header.classList.contains('asc');
            
            // Remove previous sort indicators
            table.querySelectorAll('.sortable').forEach(th => {
                th.classList.remove('asc', 'desc');
            });
            
            // Add current sort indicator
            header.classList.add(isAscending ? 'asc' : 'desc');
            
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim();
                const bText = b.cells[columnIndex].textContent.trim();
                
                let aVal, bVal;
                
                if (isNumeric) {
                    aVal = parseFloat(aText.replace(/[^0-9.-]/g, '')) || 0;
                    bVal = parseFloat(bText.replace(/[^0-9.-]/g, '')) || 0;
                } else if (isDate) {
                    aVal = new Date(aText);
                    bVal = new Date(bText);
                } else {
                    aVal = aText.toLowerCase();
                    bVal = bText.toLowerCase();
                }
                
                if (aVal < bVal) return isAscending ? -1 : 1;
                if (aVal > bVal) return isAscending ? 1 : -1;
                return 0;
            });
            
            // Reorder the rows in the table
            rows.forEach(row => tbody.appendChild(row));
        }

        // Add click event listeners to sortable headers
        document.addEventListener('DOMContentLoaded', function() {
            const employeeTable = document.querySelector('#employees table');
            const sortableHeaders = employeeTable.querySelectorAll('.sortable');
            
            sortableHeaders.forEach((header, index) => {
                header.addEventListener('click', function() {
                    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
                    const sortType = header.dataset.sort;
                    
                    if (sortType === 'id' || sortType === 'hourly_rate' || sortType === 'max_hours') {
                        sortTable(employeeTable, columnIndex, true);
                    } else if (sortType === 'start_date') {
                        sortTable(employeeTable, columnIndex, false, true);
                    } else {
                        sortTable(employeeTable, columnIndex);
                    }
                });
            });

            // CSV Export functionality (will be implemented separately)
            window.exportScheduleCSV = function() {
                alert('CSV export functionality will be implemented next!');
            };
        });

        // Task deletion confirmation
        function confirmDelete(taskTitle) {
            return confirm('Are you sure you want to delete the task "' + taskTitle + '"?\\n\\nThis action cannot be undone.');
        }
    </script>
</body>
</html>
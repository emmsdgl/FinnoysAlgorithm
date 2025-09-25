<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

if (!$team_id) {
    header('Location: list_teams.php');
    exit;
}

// Handle status updates
if ($_POST && isset($_POST['task_id']) && isset($_POST['new_status'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND team_id = ?");
        $stmt->execute([$new_status, $task_id, $team_id]);
        $message = '<div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> Task status updated successfully!
        </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get team information
$team_stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
$team_stmt->execute([$team_id]);
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: list_teams.php');
    exit;
}

// Get team members
$members_query = "
    SELECT e.* FROM employees e
    JOIN team_members tm ON e.id = tm.employee_id
    WHERE tm.team_id = ?
    ORDER BY e.name
";
$members = $db->prepare($members_query);
$members->execute([$team_id]);
$team_members = $members->fetchAll(PDO::FETCH_ASSOC);

// Get team tasks
$tasks_query = "
    SELECT * FROM tasks 
    WHERE team_id = ? 
    ORDER BY 
        CASE priority_level 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        scheduled_date ASC,
        created_at DESC
";
$tasks_stmt = $db->prepare($tasks_query);
$tasks_stmt->execute([$team_id]);
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

$status_colors = [
    'pending' => 'warning',
    'in_progress' => 'primary',
    'on_hold' => 'danger',
    'completed' => 'success'
];

$priority_colors = [
    'urgent' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'success'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($team['team_name']); ?> Tasks - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .task-card { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .task-card:hover { transform: translateY(-2px); }
        .team-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .member-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: linear-gradient(45deg, #28a745, #007bff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .status-badge { cursor: pointer; }
        .task-priority { font-size: 0.85em; }
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
                        <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
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
                            <li><a class="dropdown-item" href="add_task.php"><i class="fas fa-plus-circle"></i> Add Task</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="client_booking.php"><i class="fas fa-calendar-plus"></i> Client Booking</a></li>
                            <li><a class="dropdown-item" href="admin_add_task.php"><i class="fas fa-building"></i> Recurring Tasks</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users-cog"></i> Teams
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="list_teams.php"><i class="fas fa-list"></i> View All Teams</a></li>
                            <li><a class="dropdown-item" href="create_team.php"><i class="fas fa-users"></i> Create Team</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo $message; ?>

        <!-- Team Header -->
        <div class="card mb-4">
            <div class="team-header p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="fas fa-users"></i> <?php echo htmlspecialchars($team['team_name']); ?></h2>
                        <p class="mb-0">Team Task Management & Status Updates</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h3><?php echo count($tasks); ?></h3>
                        <p class="mb-0">Assigned Tasks</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <h6 class="text-primary mb-3"><i class="fas fa-user-friends"></i> Team Members</h6>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($team_members as $member): ?>
                    <div class="d-flex align-items-center">
                        <div class="member-avatar me-2">
                            <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
                            <small class="text-muted">€<?php echo $member['hourly_rate']; ?>/hr</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Task Statistics -->
        <div class="row mb-4">
            <?php
            $status_counts = array_count_values(array_column($tasks, 'status'));
            $status_labels = [
                'pending' => 'Pending',
                'in_progress' => 'In Progress', 
                'on_hold' => 'On Hold',
                'completed' => 'Completed'
            ];
            ?>
            <?php foreach ($status_labels as $status => $label): ?>
            <div class="col-md-3">
                <div class="card bg-<?php echo $status_colors[$status]; ?> text-white text-center p-3">
                    <h3><?php echo $status_counts[$status] ?? 0; ?></h3>
                    <p class="mb-0"><?php echo $label; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tasks -->
        <div class="row">
            <?php if (empty($tasks)): ?>
            <div class="col-12">
                <div class="card task-card p-5 text-center">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Tasks Assigned</h4>
                    <p class="text-muted">This team has no tasks assigned yet.</p>
                    <a href="add_task.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Task
                    </a>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): 
                    $required_skills = json_decode($task['required_skills'], true);
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card task-card h-100">
                        <div class="card-header bg-<?php echo $priority_colors[$task['priority_level']]; ?> text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="task-priority">
                                    <i class="fas fa-exclamation-circle"></i> 
                                    <?php echo ucfirst($task['priority_level']); ?> Priority
                                </span>
                                <span class="badge bg-light text-dark">
                                    <?php echo $task['estimated_duration']; ?>h
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($task['location']); ?>
                                </small>
                            </div>

                            <?php if ($task['scheduled_date']): ?>
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M j, Y', strtotime($task['scheduled_date'])); ?>
                                    <?php if ($task['time_slot']): ?>
                                        at <?php echo $task['time_slot']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Required Skills:</small>
                                <?php foreach ($required_skills as $skill): ?>
                                <span class="badge bg-light text-dark me-1"><?php echo ucfirst(str_replace('_', ' ', $skill)); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <form method="POST" class="status-update-form">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="mb-2">
                                    <label class="form-label small">Current Status:</label>
                                    <select name="new_status" class="form-select form-select-sm" 
                                            onchange="this.form.submit()">
                                        <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>
                                            Pending
                                        </option>
                                        <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>
                                            In Progress
                                        </option>
                                        <option value="on_hold" <?php echo $task['status'] == 'on_hold' ? 'selected' : ''; ?>>
                                            On Hold
                                        </option>
                                        <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>
                                            Completed
                                        </option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php echo $status_colors[$task['status']]; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                                <small class="text-muted">
                                    ID: #<?php echo $task['id']; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="list_teams.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Back to Teams
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when status changes
        document.querySelectorAll('.status-update-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert-dismissible').forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>
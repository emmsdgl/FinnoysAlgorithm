<?php
require_once 'db.php';
session_start();

$db = getDB();
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

if (!$team_id) {
    header('Location: list_teams.php');
    exit;
}

// Get team information
$team_stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
$team_stmt->execute([$team_id]);
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: list_teams.php');
    exit;
}

// Get team members with detailed information
$members_query = "
    SELECT e.*, 
           JSON_EXTRACT(e.skills, '$') as parsed_skills
    FROM employees e
    JOIN team_members tm ON e.id = tm.employee_id
    WHERE tm.team_id = ?
    ORDER BY e.name
";
$members_stmt = $db->prepare($members_query);
$members_stmt->execute([$team_id]);
$team_members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team tasks with statistics
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

// Calculate team statistics
$total_tasks = count($tasks);
$status_counts = array_count_values(array_column($tasks, 'status'));
$priority_counts = array_count_values(array_column($tasks, 'priority_level'));
$total_estimated_hours = array_sum(array_column($tasks, 'estimated_duration')) / 60;

// Calculate team skills
$all_skills = [];
foreach ($team_members as $member) {
    $skills = json_decode($member['parsed_skills'], true);
    if (is_array($skills)) {
        $all_skills = array_merge($all_skills, $skills);
    }
}
$team_skills = array_unique($all_skills);

// Color schemes
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

$status_labels = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress', 
    'on_hold' => 'On Hold',
    'completed' => 'Completed'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($team['team_name']); ?> - Team Details | OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .team-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .team-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .team-card:hover { transform: translateY(-2px); }
        .member-card { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .member-card:hover { transform: translateY(-2px); }
        .member-avatar { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            background: linear-gradient(45deg, #28a745, #007bff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        .task-card { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .task-card:hover { transform: translateY(-2px); }
        .stat-card { 
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .skill-badge { 
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            margin: 2px;
        }
        .performance-chart {
            height: 200px;
            position: relative;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
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
        <!-- Team Header -->
        <div class="team-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars($team['team_name']); ?></h1>
                    <p class="mb-2 opacity-75">Comprehensive Team Overview & Performance Metrics</p>
                    <p class="mb-0"><small><i class="fas fa-calendar"></i> Created: <?php echo date('M j, Y', strtotime($team['created_at'])); ?></small></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="list_teams.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left"></i> All Teams
                        </a>
                        <a href="team_tasks.php?team_id=<?php echo $team_id; ?>" class="btn btn-light">
                            <i class="fas fa-tasks"></i> Manage Tasks
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <h3 class="text-primary"><?php echo count($team_members); ?></h3>
                    <p class="mb-0"><i class="fas fa-users"></i> Team Members</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <h3 class="text-warning"><?php echo $total_tasks; ?></h3>
                    <p class="mb-0"><i class="fas fa-tasks"></i> Total Tasks</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <h3 class="text-info"><?php echo number_format($total_estimated_hours, 1); ?>h</h3>
                    <p class="mb-0"><i class="fas fa-clock"></i> Estimated Hours</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <h3 class="text-success"><?php echo count($team_skills); ?></h3>
                    <p class="mb-0"><i class="fas fa-star"></i> Combined Skills</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Team Members Section -->
            <div class="col-md-6 mb-4">
                <div class="card team-card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-user-friends"></i> Team Members</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($team_members)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Members Assigned</h6>
                            <p class="text-muted">This team has no members assigned yet.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($team_members as $member): 
                                $member_skills = json_decode($member['parsed_skills'], true);
                            ?>
                            <div class="member-card p-3 mb-3">
                                <div class="row align-items-center">
                                    <div class="col-3">
                                        <div class="member-avatar mx-auto">
                                            <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                                        </div>
                                    </div>
                                    <div class="col-9">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($member['name']); ?></h6>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-euro-sign"></i> €<?php echo $member['hourly_rate']; ?>/hr • 
                                                <i class="fas fa-clock"></i> <?php echo $member['max_hours_per_3weeks']; ?>h/3wks
                                            </small>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Skills:</small>
                                            <?php if (is_array($member_skills)): ?>
                                                <?php foreach (array_slice($member_skills, 0, 3) as $skill): ?>
                                                <span class="badge bg-light text-dark me-1"><?php echo ucfirst($skill); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($member_skills) > 3): ?>
                                                <span class="badge bg-secondary">+<?php echo count($member_skills) - 3; ?> more</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No skills listed</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> Since <?php echo date('M Y', strtotime($member['start_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Team Skills & Performance -->
            <div class="col-md-6 mb-4">
                <div class="card team-card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-chart-line"></i> Team Capabilities</h5>
                    </div>
                    <div class="card-body">
                        <!-- Team Skills -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Combined Skills Portfolio</h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($team_skills as $skill): ?>
                                <span class="skill-badge"><?php echo ucfirst(str_replace('_', ' ', $skill)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Task Status Distribution -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Task Status Distribution</h6>
                            <?php foreach ($status_labels as $status => $label): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo $label; ?></span>
                                <div class="d-flex align-items-center">
                                    <div class="progress me-2" style="width: 100px; height: 20px;">
                                        <div class="progress-bar bg-<?php echo $status_colors[$status]; ?>" 
                                             style="width: <?php echo $total_tasks > 0 ? (($status_counts[$status] ?? 0) / $total_tasks * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="badge bg-<?php echo $status_colors[$status]; ?>"><?php echo $status_counts[$status] ?? 0; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Priority Distribution -->
                        <div>
                            <h6 class="text-primary mb-3">Priority Distribution</h6>
                            <?php foreach (['urgent', 'high', 'medium', 'low'] as $priority): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo ucfirst($priority); ?></span>
                                <div class="d-flex align-items-center">
                                    <div class="progress me-2" style="width: 100px; height: 20px;">
                                        <div class="progress-bar bg-<?php echo $priority_colors[$priority]; ?>" 
                                             style="width: <?php echo $total_tasks > 0 ? (($priority_counts[$priority] ?? 0) / $total_tasks * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="badge bg-<?php echo $priority_colors[$priority]; ?>"><?php echo $priority_counts[$priority] ?? 0; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tasks -->
        <div class="row">
            <div class="col-12">
                <div class="card team-card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-tasks"></i> Recent Tasks (<?php echo count($tasks); ?>)</h5>
                        <a href="team_tasks.php?team_id=<?php echo $team_id; ?>" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-external-link-alt"></i> Manage All Tasks
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($tasks)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Tasks Assigned</h6>
                            <p class="text-muted">This team has no tasks assigned yet.</p>
                            <a href="add_task.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First Task
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Task</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Scheduled</th>
                                        <th>Duration</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($tasks, 0, 10) as $task): 
                                        $required_skills = json_decode($task['required_skills'], true);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            <br><small class="text-muted">ID: #<?php echo $task['id']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $priority_colors[$task['priority_level']]; ?>">
                                                <?php echo ucfirst($task['priority_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_colors[$task['status']]; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['scheduled_date']): ?>
                                                <small>
                                                    <?php echo date('M j, Y', strtotime($task['scheduled_date'])); ?>
                                                    <?php if ($task['time_slot']): ?>
                                                        <br><?php echo $task['time_slot']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">Not scheduled</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo $task['estimated_duration']; ?>m</small></td>
                                        <td><small><?php echo htmlspecialchars($task['location'] ?: 'TBD'); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($tasks) > 10): ?>
                            <div class="p-3 text-center bg-light">
                                <small class="text-muted">Showing 10 of <?php echo count($tasks); ?> tasks. </small>
                                <a href="team_tasks.php?team_id=<?php echo $team_id; ?>" class="btn btn-sm btn-outline-primary">
                                    View All Tasks
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
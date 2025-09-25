<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';

// ADD THIS BLOCK to handle success messages from redirects
if (isset($_GET['success']) && $_GET['success'] == 'updated') {
    $message = '<div class="alert alert-success">Team updated successfully!</div>';
}

// Get teams with member information
$teams_query = "
    SELECT t.id, t.team_name, t.created_at,
           COUNT(tm.employee_id) as member_count,
           GROUP_CONCAT(e.name ORDER BY e.name SEPARATOR ', ') as member_names
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id
    LEFT JOIN employees e ON tm.employee_id = e.id
    GROUP BY t.id, t.team_name, t.created_at
    ORDER BY t.created_at DESC
";
$teams = $db->query($teams_query)->fetchAll(PDO::FETCH_ASSOC);

// Get task assignments for each team
$task_assignments = [];
foreach ($teams as $team) {
    $task_query = "SELECT COUNT(*) as task_count, 
                          SUM(CASE WHEN status != 'completed' THEN 1 ELSE 0 END) as active_tasks
                   FROM tasks WHERE team_id = ?";
    $stmt = $db->prepare($task_query);
    $stmt->execute([$team['id']]);
    $task_assignments[$team['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .team-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .team-card:hover { transform: translateY(-5px); }
        .team-avatar { 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            background: linear-gradient(45deg, #007bff, #28a745);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        .stats-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
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
                            <li><a class="dropdown-item active" href="list_teams.php"><i class="fas fa-list"></i> View All Teams</a></li>
                            <li><a class="dropdown-item" href="create_team.php"><i class="fas fa-users"></i> Create Team</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-users-cog"></i> Team Management</h2>
                <p class="text-muted">Manage cleaning teams and view their assignments</p>
            </div>
            <a href="create_team.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus"></i> Create New Team
            </a>
        </div>
        
        <!-- Display message if exists -->
        <?php if ($message) { echo $message; } ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card p-4 text-center">
                    <h3><?php echo count($teams); ?></h3>
                    <p class="mb-0"><i class="fas fa-users"></i> Active Teams</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card p-4 text-center">
                    <h3><?php echo array_sum(array_column($teams, 'member_count')); ?></h3>
                    <p class="mb-0"><i class="fas fa-user"></i> Total Members</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card p-4 text-center">
                    <?php 
                    $total_active_tasks = 0;
                    foreach ($task_assignments as $task_data) {
                        $total_active_tasks += $task_data['active_tasks'];
                    }
                    ?>
                    <h3><?php echo $total_active_tasks; ?></h3>
                    <p class="mb-0"><i class="fas fa-tasks"></i> Active Tasks</p>
                </div>
            </div>
        </div>

        <!-- Teams Grid -->
        <div class="row">
            <?php if (empty($teams)): ?>
            <div class="col-12">
                <div class="card team-card p-5 text-center">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Teams Created Yet</h4>
                    <p class="text-muted">Create your first team to start organizing your workforce</p>
                    <a href="create_team.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus"></i> Create First Team
                    </a>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($teams as $team): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card team-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="team-avatar me-3">
                                    <?php echo strtoupper(substr($team['team_name'], 0, 2)); ?>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($team['team_name']); ?></h5>
                                    <small class="text-muted">Created <?php echo date('M j, Y', strtotime($team['created_at'])); ?></small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-primary"><i class="fas fa-users"></i> Team Members (<?php echo $team['member_count']; ?>/3)</h6>
                                <?php if ($team['member_names']): ?>
                                    <?php 
                                    $names = explode(', ', $team['member_names']);
                                    foreach ($names as $name): 
                                    ?>
                                    <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($name); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No members assigned</span>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-primary"><i class="fas fa-tasks"></i> Task Assignment</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <strong><?php echo $task_assignments[$team['id']]['task_count']; ?></strong>
                                            <br><small>Total Tasks</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-warning rounded">
                                            <strong><?php echo $task_assignments[$team['id']]['active_tasks']; ?></strong>
                                            <br><small>Active Tasks</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0">
                            <div class="d-grid gap-2">
                                <a href="team_view.php?team_id=<?php echo $team['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-info-circle"></i> View Details
                                </a>
                                <div class="btn-group w-100">
                                    <a href="team_tasks.php?team_id=<?php echo $team['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-tasks"></i> Manage Tasks
                                    </a>
                                    
                                    <!-- THIS IS THE CHANGE: Convert the button to a link -->
                                    <a href="edit_team.php?id=<?php echo $team['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>

                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete team <strong id="teamToDelete"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone. All task assignments will be removed.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Team</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editTeam(teamId) {
            // Redirect to edit page (you can implement this)
            alert('Edit team functionality - can be implemented as needed');
        }

        function confirmDelete(teamId, teamName) {
            document.getElementById('teamToDelete').textContent = teamName;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            document.getElementById('confirmDeleteBtn').onclick = function() {
                deleteTeam(teamId);
            };
            
            modal.show();
        }

        function deleteTeam(teamId) {
            // You can implement team deletion via AJAX here
            // For now, we'll use a simple form submission
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_team.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'team_id';
            input.value = teamId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
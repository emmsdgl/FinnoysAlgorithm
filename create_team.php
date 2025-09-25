<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';

// Get available employees
$employees = $db->query("SELECT * FROM employees WHERE end_date IS NULL OR end_date > CURRENT_DATE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    $team_name = trim($_POST['team_name']);
    $selected_employees = isset($_POST['employees']) ? $_POST['employees'] : [];

    if (!empty($team_name) && count($selected_employees) == 3) {
        try {
            $db->beginTransaction();
            
            // Create team
            $stmt = $db->prepare("INSERT INTO teams (team_name) VALUES (?)");
            $stmt->execute([$team_name]);
            $team_id = $db->lastInsertId();
            
            // Add team members
            $member_stmt = $db->prepare("INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)");
            foreach ($selected_employees as $employee_id) {
                $member_stmt->execute([$team_id, $employee_id]);
            }
            
            $db->commit();
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Team created successfully!</div>';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Please provide a team name and select exactly 3 employees.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Team - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .employee-card { 
            border: 2px solid #e9ecef; 
            border-radius: 8px; 
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .employee-card:hover { 
            border-color: #007bff; 
            transform: translateY(-2px);
        }
        .employee-card.selected { 
            border-color: #28a745; 
            background-color: #f8fff8;
        }
        .counter { 
            position: fixed; 
            top: 100px; 
            right: 20px; 
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Selection Counter -->
    <div class="counter">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5><span id="selectedCount">0</span> / 3</h5>
                <small>Selected</small>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-users"></i> OptiCrew WMS - Fin-noys</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a class="nav-link" href="list_teams.php"><i class="fas fa-list"></i> View Teams</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card form-card">
                    <div class="card-header bg-white">
                        <h4><i class="fas fa-users"></i> Create New Team</h4>
                        <p class="text-muted mb-0">Select exactly 3 employees to form a team</p>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" id="teamForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="team_name" class="form-label">Team Name *</label>
                                    <input type="text" class="form-control form-control-lg" id="team_name" 
                                           name="team_name" placeholder="e.g., Delta Team" required>
                                </div>
                            </div>

                            <h5 class="mb-3"><i class="fas fa-user-friends"></i> Select Team Members (Choose 3)</h5>
                            <div class="row">
                                <?php foreach ($employees as $employee): 
                                    $skills = json_decode($employee['skills'], true);
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="employee-card p-3 h-100" onclick="toggleEmployee(<?php echo $employee['id']; ?>)">
                                        <input type="checkbox" name="employees[]" value="<?php echo $employee['id']; ?>" 
                                               id="emp_<?php echo $employee['id']; ?>" style="display: none;">
                                        <div class="text-center">
                                            <i class="fas fa-user-circle fa-3x text-primary mb-2"></i>
                                            <h6><?php echo htmlspecialchars($employee['name']); ?></h6>
                                            <p class="text-muted mb-1">
                                                <small>€<?php echo $employee['hourly_rate']; ?>/hr • <?php echo ucfirst($employee['pay_period']); ?></small>
                                            </p>
                                            <div class="mb-2">
                                                <?php foreach (array_slice($skills, 0, 3) as $skill): ?>
                                                <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $skill)); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($skills) > 3): ?>
                                                <span class="badge bg-secondary">+<?php echo count($skills) - 3; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-success">
                                                <i class="fas fa-calendar"></i> Since <?php echo date('M Y', strtotime($employee['start_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-success btn-lg" id="createTeamBtn" disabled>
                                    <i class="fas fa-users"></i> Create Team
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedCount = 0;
        const maxSelection = 3;

        function toggleEmployee(employeeId) {
            const checkbox = document.getElementById('emp_' + employeeId);
            const card = checkbox.closest('.employee-card');
            
            if (checkbox.checked) {
                // Deselect
                checkbox.checked = false;
                card.classList.remove('selected');
                selectedCount--;
            } else {
                // Select (if under limit)
                if (selectedCount < maxSelection) {
                    checkbox.checked = true;
                    card.classList.add('selected');
                    selectedCount++;
                }
            }
            
            updateCounter();
        }

        function updateCounter() {
            document.getElementById('selectedCount').textContent = selectedCount;
            const createBtn = document.getElementById('createTeamBtn');
            
            if (selectedCount === maxSelection) {
                createBtn.disabled = false;
                createBtn.classList.remove('btn-secondary');
                createBtn.classList.add('btn-success');
            } else {
                createBtn.disabled = true;
                createBtn.classList.remove('btn-success');
                createBtn.classList.add('btn-secondary');
            }
        }
    </script>
</body>
</html>
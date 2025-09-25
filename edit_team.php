<<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';
$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$team_id) {
    header('Location: list_teams.php');
    exit();
}

// Handle form submission for UPDATE
if ($_POST) {
    $team_name = trim($_POST['team_name']);
    $selected_employees = isset($_POST['employees']) ? $_POST['employees'] : [];

    if (!empty($team_name) && count($selected_employees) >= 2 && count($selected_employees) <= 3) {
        
        // --- NEW SWAP LOGIC ---

        // 1. Get the original members of the team BEFORE the edit
        $stmt = $db->prepare("SELECT employee_id FROM team_members WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $original_member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Calculate the difference to see who was added and who was removed
        $employees_to_add = array_diff($selected_employees, $original_member_ids);
        $employees_to_remove = array_diff($original_member_ids, $selected_employees);

        // 3. Check if this is a simple 1-for-1 swap scenario
        if (count($employees_to_add) === 1 && count($employees_to_remove) === 1) {
            // It is a swap!
            $added_employee_id = reset($employees_to_add);
            $removed_employee_id = reset($employees_to_remove);

            // Find the original team of the employee being added
            $source_stmt = $db->prepare("SELECT team_id FROM team_members WHERE employee_id = ?");
            $source_stmt->execute([$added_employee_id]);
            $source_team_id = $source_stmt->fetchColumn();

            // Only proceed if the added employee was actually on another team
            if ($source_team_id) {
                try {
                    $db->beginTransaction();

                    // Update the team name first
                    $name_stmt = $db->prepare("UPDATE teams SET team_name = ? WHERE id = ?");
                    $name_stmt->execute([$team_name, $team_id]);

                    // Atomically swap the members
                    // Move the REMOVED employee to the ADDED employee's old team
                    $swap_out_stmt = $db->prepare("UPDATE team_members SET team_id = ? WHERE employee_id = ?");
                    $swap_out_stmt->execute([$source_team_id, $removed_employee_id]);

                    // Move the ADDED employee to the team being edited
                    $swap_in_stmt = $db->prepare("UPDATE team_members SET team_id = ? WHERE employee_id = ?");
                    $swap_in_stmt->execute([$team_id, $added_employee_id]);

                    $db->commit();
                    header('Location: list_teams.php?success=swapped');
                    exit();

                } catch (PDOException $e) {
                    $db->rollBack();
                    $message = '<div class="alert alert-danger">Error swapping team members: ' . $e->getMessage() . '</div>';
                }
            }
        }

        // --- FALLBACK LOGIC (for non-swap edits) ---
        // If it's not a 1-for-1 swap, or the added employee was unassigned, use the original update method.
        try {
            $db->beginTransaction();

            // 1. Update team name
            $stmt = $db->prepare("UPDATE teams SET team_name = ? WHERE id = ?");
            $stmt->execute([$team_name, $team_id]);

            // 2. Delete existing members for this team
            $stmt = $db->prepare("DELETE FROM team_members WHERE team_id = ?");
            $stmt->execute([$team_id]);

            // 3. Insert the new members
            $member_stmt = $db->prepare("INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)");
            foreach ($selected_employees as $employee_id) {
                $member_stmt->execute([$team_id, $employee_id]);
            }

            $db->commit();
            header('Location: list_teams.php?success=updated');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger">Error updating team: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Please provide a team name and select 2 or 3 employees.</div>';
    }
}


// --- The rest of the file (fetching data to pre-fill the form) remains the same ---
$stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team_to_edit) {
    header('Location: list_teams.php');
    exit();
}

$stmt = $db->prepare("SELECT employee_id FROM team_members WHERE team_id = ?");
$stmt->execute([$team_id]);
$current_member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Small but important change: make sure we can select from all employees, even those on other teams
$employees = $db->query("SELECT * FROM employees WHERE end_date IS NULL OR end_date > CURRENT_DATE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* (Your CSS from create_team.php goes here - it's identical) */
        body { background-color: #f8f9fa; }
        .form-card { background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .employee-card { border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
        .employee-card:hover { border-color: #007bff; transform: translateY(-2px); }
        .employee-card.selected { border-color: #28a745; background-color: #f8fff8; border-width: 3px;}
        .counter { position: fixed; top: 100px; right: 20px; z-index: 1000; }
    </style>
</head>
<body>
    <!-- (Your Counter HTML goes here - it's identical) -->
    <div class="counter">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5><span id="selectedCount">0</span> / 3</h5>
                <small>Selected</small>
            </div>
        </div>
    </div>

    <!-- (Your Navigation HTML goes here - it's identical) -->
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
                        <h4><i class="fas fa-edit"></i> Edit Team</h4>
                        <p class="text-muted mb-0">Modify the team name and member selection</p>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        <form method="POST" id="teamForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="team_name" class="form-label">Team Name *</label>
                                    <input type="text" class="form-control form-control-lg" id="team_name" name="team_name" value="<?php echo htmlspecialchars($team_to_edit['team_name']); ?>" required>
                                </div>
                            </div>

                            <h5 class="mb-3"><i class="fas fa-user-friends"></i> Select Team Members (Choose 2 or 3)</h5>
                            <div class="row">
                                <?php foreach ($employees as $employee): 
                                    $skills = json_decode($employee['skills'], true) ?: [];

                                    $isSelected = in_array($employee['id'], $current_member_ids);
                                ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="employee-card p-3 h-100 <?php echo $isSelected ? 'selected' : ''; ?>" onclick="toggleEmployee(<?php echo $employee['id']; ?>)">
                                            <input type="checkbox" name="employees[]" value="<?php echo $employee['id']; ?>" id="emp_<?php echo $employee['id']; ?>" style="display: none;" <?php echo $isSelected ? 'checked' : ''; ?>>
                                            
                                            <!-- The rest of this HTML card can now safely use the $skills variable -->
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
                                <a href="list_teams.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left"></i> Back to Team List</a>
                                <button type="submit" class="btn btn-primary btn-lg" id="createTeamBtn"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const maxSelection = 3;
        const minSelection = 2;
        // Initialize the count with the number of members already selected
        let selectedCount = <?php echo count($current_member_ids); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            updateCounter(); // Call on page load to set initial state
        });

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
            // Enable the button if the selection is valid (2 or 3)
            if (selectedCount >= minSelection && selectedCount <= maxSelection) {
                createBtn.disabled = false;
                createBtn.classList.remove('btn-secondary');
                createBtn.classList.add('btn-primary');
            } else {
                createBtn.disabled = true;
                createBtn.classList.remove('btn-primary');
                createBtn.classList.add('btn-secondary');
            }
        }
    </script>
</body>
</html>
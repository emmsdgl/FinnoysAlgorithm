<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $skills_input = trim($_POST['skills']);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 13.00);
    $pay_period = $_POST['pay_period'] ?? 'monthly';
    $max_hours = intval($_POST['max_hours_per_3weeks'] ?? 90);
    $trial_months = intval($_POST['trial_period_months'] ?? 3);
    $collective_agreement = $_POST['collective_agreement'] ?? 'Property Service Sector Collective Agreement';
    $insurance = $_POST['insurance'] ?? 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits';
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $principal_duties = $_POST['principal_duties'] ?? 'Cleaning accommodations, Maintenance, Restaurant Staff';
    
    // Convert comma-separated skills to JSON array
    $skills = [];
    if (!empty($skills_input)) {
        $skills = array_map('trim', explode(',', $skills_input));
        $skills = array_filter($skills); // Remove empty values
    }

    if (!empty($name) && !empty($start_date) && !empty($skills)) {
        try {
            $stmt = $db->prepare("INSERT INTO employees (name, skills, hourly_rate, pay_period, max_hours_per_3weeks, trial_period_months, collective_agreement, insurance, start_date, end_date, principal_duties) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                json_encode($skills),
                $hourly_rate,
                $pay_period,
                $max_hours,
                $trial_months,
                $collective_agreement,
                $insurance,
                $start_date,
                $end_date,
                $principal_duties
            ]);
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Employee added successfully! <a href="index.php" class="alert-link">Return to Dashboard</a></div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Please fill in Name, Skills, and Start Date.</div>';
    }
}

// Common skill examples for reference
$skill_examples = 'cleaning, deep_cleaning, maintenance, customer_service, restaurant_service, quality_control, snow_removal, laundry, room_preparation';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-users"></i> OptiCrew WMS - Fin-noys</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a class="nav-link" href="add_task.php"><i class="fas fa-tasks"></i> Add Task</a>
                <a class="nav-link" href="list_teams.php"><i class="fas fa-users-cog"></i> Teams</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card form-card">
                    <div class="card-header bg-white">
                        <h4><i class="fas fa-user-plus"></i> Add New Employee</h4>
                        <p class="text-muted mb-0">Enter employee details and contract information</p>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST">
                            <!-- Basic Information -->
                            <h5 class="text-primary mb-3"><i class="fas fa-user"></i> Employee Information</h5>
                                    
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="e.g., John Doe" required>
                            </div>

                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills * <small class="text-muted">(comma-separated)</small></label>
                                <input type="text" class="form-control" id="skills" name="skills" 
                                       placeholder="<?php echo $skill_examples; ?>" required>
                                <small class="form-text text-muted">Enter skills separated by commas. Example: cleaning, maintenance, customer_service</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date <small class="text-muted">(optional)</small></label>
                                        <input type="date" class="form-control" id="end_date" name="end_date">
                                        <small class="form-text text-muted">Leave blank for permanent employment</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Contract Details (Auto-filled but editable) -->
                            <h5 class="text-primary mb-3 mt-4"><i class="fas fa-file-contract"></i> Contract Details <small class="text-muted">(auto-filled, editable)</small></h5>
                                    
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="hourly_rate" class="form-label">Hourly Rate (€)</label>
                                        <input type="number" step="0.01" class="form-control" id="hourly_rate" 
                                               name="hourly_rate" value="13.00">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="max_hours_per_3weeks" class="form-label">Max Hours (3 weeks)</label>
                                        <input type="number" class="form-control" id="max_hours_per_3weeks" 
                                               name="max_hours_per_3weeks" value="90">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="trial_period_months" class="form-label">Trial Period (months)</label>
                                        <input type="number" class="form-control" id="trial_period_months" 
                                               name="trial_period_months" value="3">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pay_period" class="form-label">Pay Period</label>
                                        <select class="form-control" id="pay_period" name="pay_period">
                                            <option value="monthly">Monthly</option>
                                            <option value="bi-weekly">Bi-weekly</option>
                                            <option value="weekly">Weekly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="collective_agreement" class="form-label">Collective Agreement</label>
                                        <input type="text" class="form-control" id="collective_agreement" 
                                               name="collective_agreement" 
                                               value="Property Service Sector Collective Agreement">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="insurance" class="form-label">Insurance Coverage</label>
                                <textarea class="form-control" id="insurance" name="insurance" rows="2">Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits</textarea>
                            </div>

                            <div class="mb-3">
                                <label for="principal_duties" class="form-label">Principal Duties</label>
                                <textarea class="form-control" id="principal_duties" name="principal_duties" rows="2">Cleaning accommodations, Maintenance, Restaurant Staff</textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
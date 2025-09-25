<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';

if ($_POST) {
    $client_name = trim($_POST['client_name']);
    $service_type = $_POST['service_type'];
    $booking_date = $_POST['booking_date'];
    $time_slot = $_POST['time_slot'] ?? '09:00-11:00';
    $priority_level = $_POST['priority_level'] ?? 'medium';
    $estimated_duration = intval($_POST['estimated_duration'] ?? 120);
    $special_notes = $_POST['special_notes'] ?? '';

    if (!empty($client_name) && !empty($service_type) && !empty($booking_date) && !empty($time_slot)) {
        try {
            $db->beginTransaction();

            // Create standardized task according to specifications
            $task_title = "[{$service_type}] for {$client_name}";
            if (!empty($special_notes)) {
                $task_title .= " (Notes: " . substr($special_notes, 0, 50) . "...)";
            }

            // Create task with user-selected values
            $stmt = $db->prepare("INSERT INTO tasks (title, required_skills, estimated_duration, priority_level, scheduled_date, time_slot, location) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $task_title,
                json_encode(['cleaning']), // Standardized skills
                $estimated_duration,
                $priority_level,
                $booking_date,
                $time_slot,
                'External Client'
            ]);

            // GET THE NEW TASK ID
            $new_task_id = $db->lastInsertId();
            $new_task_ids = [$new_task_id]; // The scheduler expects an array of IDs

            $db->commit();

            // --- HybridScheduler assignment (LOGIC COPIED FROM admin_add_task.php) ---
            require_once 'HybridScheduler.php';
            $scheduler = new HybridScheduler();
            $result = $scheduler->runOptimizationForTasks($new_task_ids);

            // Build assignment summary for UI
            $assignment_html = '';
            if ($result['success'] && !empty($result['assignments'])) {
                $assignment_html .= '<div class="alert alert-info mt-3"><strong>Task Assignment:</strong><ul>';
                // Loop through assignments (even though there's only one, this is robust)
                foreach ($result['assignments'] as $assignment) {
                    if ($assignment['task_id'] == $new_task_id) {
                        $team_name = $assignment['team']['team_name'] ?? 'Unassigned';
                        $assignment_html .= '<li><b>' . htmlspecialchars($assignment['task']['title']) . '</b> &rarr; <span class="badge bg-primary">' .
                            htmlspecialchars($team_name) . '</span></li>';
                    }
                }
                $assignment_html .= '</ul></div>';
            } else {
                $assignment_html .= '<div class="alert alert-warning mt-3">The new task was created but could not be automatically assigned at this time. It will be assigned in the next optimization run.</div>';
            }

            // UPDATE THE SUCCESS MESSAGE TO INCLUDE THE ASSIGNMENT
            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Booking Confirmed!</strong><br>
                Service: ' . $service_type . '<br>
                Date: ' . date('M j, Y', strtotime($booking_date)) . '<br>
                Time: ' . $time_slot . '<br>
                A task has been created and assigned.
                </div>' . $assignment_html; // Append the assignment details


        } catch (PDOException $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Please fill in all required fields.</div>';
    }
}

$service_types = [
    'Daily Cleaning' => ['name' => 'Daily Cleaning', 'duration' => '2 hours', 'icon' => 'broom', 'desc' => 'Regular daily cleaning service'],
    'Deep Cleaning' => ['name' => 'Deep Cleaning', 'duration' => '2 hours', 'icon' => 'magic', 'desc' => 'Thorough deep cleaning service'],
    'Public/Common Area' => ['name' => 'Public/Common Area', 'duration' => '2 hours', 'icon' => 'building', 'desc' => 'Public and common area cleaning'],
    'Snow-Out' => ['name' => 'Snow-Out', 'duration' => '2 hours', 'icon' => 'snowflake', 'desc' => 'Snow removal and winter maintenance']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Booking - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .booking-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .service-card { 
            border: 2px solid #e9ecef; 
            border-radius: 10px; 
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .service-card:hover { 
            border-color: #007bff; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .service-card.selected { 
            border-color: #007bff; 
            background-color: #f8f9ff;
        }
        .navbar-brand { font-weight: bold; }
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
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-tasks"></i> Tasks
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php#tasks"><i class="fas fa-list"></i> View All</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="client_booking.php"><i class="fas fa-calendar-plus"></i> Client Booking</a></li>
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
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card booking-card">
                    <div class="card-header bg-primary text-white text-center">
                        <h3><i class="fas fa-calendar-plus"></i> Book Cleaning Service</h3>
                        <p class="mb-0">Professional cleaning services for your needs</p>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $message; ?>
                        
                        <form method="POST" id="bookingForm">
                            <!-- Client Information -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-user"></i> Client Information</h5>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="client_name" class="form-label">Client Name *</label>
                                            <input type="text" class="form-control form-control-lg" id="client_name" 
                                                   name="client_name" placeholder="Enter your full name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="booking_date" class="form-label">Preferred Date *</label>
                                            <input type="date" class="form-control form-control-lg" id="booking_date" 
                                                   name="booking_date" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Service Selection -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-concierge-bell"></i> Select Service</h5>
                                <div class="row">
                                    <?php foreach ($service_types as $service_key => $service_info): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="service-card p-3 h-100" onclick="selectService('<?php echo $service_key; ?>')">
                                            <input type="radio" name="service_type" value="<?php echo $service_key; ?>" 
                                                   id="service_<?php echo $service_key; ?>" style="display: none;" required>
                                            <div class="text-center">
                                                <i class="fas fa-<?php echo $service_info['icon']; ?> fa-3x text-primary mb-2"></i>
                                                <h6><?php echo $service_info['name']; ?></h6>
                                                <p class="text-muted mb-1"><?php echo $service_info['desc']; ?></p>
                                                <small class="text-success"><strong>Duration: <?php echo $service_info['duration']; ?></strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Time & Priority Selection -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-clock"></i> Schedule & Priority</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="time_slot" class="form-label">Preferred Time *</label>
                                            <select class="form-control form-control-lg" name="time_slot" required>
                                                <option value="">Select Time Slot</option>
                                                <option value="09:00-11:00">09:00 - 11:00 (Morning)</option>
                                                <option value="11:00-13:00">11:00 - 13:00 (Late Morning)</option>
                                                <option value="13:00-15:00">13:00 - 15:00 (Afternoon)</option>
                                                <option value="15:00-17:00">15:00 - 17:00 (Late Afternoon)</option>
                                                <option value="17:00-19:00">17:00 - 19:00 (Evening)</option>
                                                <option value="19:00-21:00">19:00 - 21:00 (Late Evening)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="priority_level" class="form-label">Priority Level *</label>
                                            <select class="form-control form-control-lg" name="priority_level" required>
                                                <option value="medium" selected>Medium (Standard)</option>
                                                <option value="high">High (Urgent)</option>
                                                <option value="low">Low (Flexible)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="estimated_duration" class="form-label">Estimated Duration *</label>
                                            <select class="form-control form-control-lg" name="estimated_duration" required>
                                                <option value="60">1 hour</option>
                                                <option value="120" selected>2 hours (Standard)</option>
                                                <option value="180">3 hours</option>
                                                <option value="240">4 hours</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Notes -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-sticky-note"></i> Special Notes</h5>
                                <textarea class="form-control" name="special_notes" rows="3" 
                                          placeholder="Any special requirements or notes for our cleaning team..."></textarea>
                            </div>

                            <!-- Booking Summary -->
                            <div class="bg-light p-3 rounded mb-4">
                                <h6 class="text-primary"><i class="fas fa-info-circle"></i> Booking Information</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Location:</strong> External Client</li>
                                    <li><strong>Team Assignment:</strong> Automatically optimized by AI algorithm</li>
                                    <li><strong>Flexibility:</strong> Choose your preferred time, priority, and duration above</li>
                                    <li><strong>Service Quality:</strong> Professional cleaning guaranteed</li>
                                    <li><strong>Booking Process:</strong> Instant task creation for immediate scheduling</li>
                                </ul>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-calendar-check"></i> Confirm Booking
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
        function selectService(serviceType) {
            // Remove selected class from all cards
            document.querySelectorAll('.service-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the hidden radio button
            document.getElementById('service_' + serviceType).checked = true;
        }

        // Set minimum date to today
        document.getElementById('booking_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
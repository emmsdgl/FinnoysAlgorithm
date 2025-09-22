<?php
require_once 'db.php';
session_start();

$db = getDB();
$message = '';

// Define recurring clients and their locations
$recurring_clients = [
    'Aikamatkat' => [
        'name' => 'Aikamatkat Travel Services',
        'locations' => [
            'Reception Area', 'Waiting Lounge', 'Office 1', 'Office 2', 'Office 3',
            'Meeting Room A', 'Meeting Room B', 'Kitchen Area', 'Common Areas',
            'Restrooms', 'Storage Room', 'Parking Area'
        ]
    ],
    'Kakslautanen' => [
        'name' => 'Kakslautanen Arctic Resort',
        'locations' => [
            'Cabin 1', 'Cabin 2', 'Cabin 3', 'Cabin 4', 'Cabin 5', 'Cabin 6',
            'Cabin 7', 'Cabin 8', 'Cabin 9', 'Cabin 10', 'Cabin 11', 'Cabin 12',
            'Reception', 'Restaurant', 'Sauna Area', 'Common Lodge', 'Spa Area',
            'Activity Center', 'Parking Area', 'Outdoor Areas'
        ]
    ],
    'Glass_Igloos' => [
        'name' => 'Glass Igloo Village',
        'locations' => [
            'Igloo 1', 'Igloo 2', 'Igloo 3', 'Igloo 4', 'Igloo 5', 'Igloo 6',
            'Igloo 7', 'Igloo 8', 'Igloo 9', 'Igloo 10', 'Igloo 11', 'Igloo 12',
            'Central Facility', 'Reception Igloo', 'Restaurant Igloo', 'Spa Igloo',
            'Common Areas', 'Outdoor Paths', 'Viewing Areas', 'Emergency Exits'
        ]
    ]
];

$service_types = [
    'Daily Cleaning' => ['name' => 'Daily Cleaning', 'skills' => ['cleaning'], 'duration' => 120, 'priority' => 'medium'],
    'Deep Cleaning' => ['name' => 'Deep Cleaning', 'skills' => ['cleaning'], 'duration' => 120, 'priority' => 'medium'],
    'Public/Common Area' => ['name' => 'Public/Common Area', 'skills' => ['cleaning'], 'duration' => 120, 'priority' => 'medium'],
    'Snow-Out' => ['name' => 'Snow-Out', 'skills' => ['cleaning'], 'duration' => 120, 'priority' => 'medium'],
    'Maintenance' => ['name' => 'Maintenance Check', 'skills' => ['cleaning'], 'duration' => 120, 'priority' => 'medium']
];

if ($_POST) {
    $client = $_POST['client'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $time_slot = $_POST['time_slot'] ?? '';
    $selected_locations = $_POST['locations'] ?? [];

    if (!empty($client) && !empty($service_type) && !empty($scheduled_date) && !empty($time_slot) && !empty($selected_locations)) {
        try {
            $service_info = $service_types[$service_type];
            $client_info = $recurring_clients[$client];
            $tasks_created = 0;

            $db->beginTransaction();

            foreach ($selected_locations as $location) {
                $task_title = "[{$service_info['name']}] {$client_info['name']} - {$location}";
                
                $stmt = $db->prepare("INSERT INTO tasks (title, required_skills, estimated_duration, priority_level, scheduled_date, time_slot, location) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $task_title,
                    json_encode($service_info['skills']), // Uses ['general'] skills
                    $service_info['duration'], // 120 minutes (2 hours)
                    $service_info['priority'], // medium priority
                    $scheduled_date,
                    $time_slot,
                    $location
                ]);
                $tasks_created++;
            }

            $db->commit();
            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <strong>Success!</strong> Created ' . $tasks_created . ' tasks for ' . $client_info['name'] . '
                <br>Service: ' . $service_info['name'] . ' on ' . date('M j, Y', strtotime($scheduled_date)) . '
            </div>';
        } catch (PDOException $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $missing_fields = [];
        if (empty($client)) $missing_fields[] = 'Client';
        if (empty($service_type)) $missing_fields[] = 'Service Type';
        if (empty($scheduled_date)) $missing_fields[] = 'Date';
        if (empty($time_slot)) $missing_fields[] = 'Time Slot';
        if (empty($selected_locations)) $missing_fields[] = 'Locations';
        
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Please fill in the following required fields: ' . implode(', ', $missing_fields) . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Client Tasks - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { background: white; border-radius: 15px; box-shadow: 0 0 30px rgba(0,0,0,0.1); }
        .cinema-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
            gap: 15px; 
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }
        .location-seat { 
            background: #28a745; 
            color: white;
            border: 2px solid #1e7e34;
            border-radius: 8px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            font-weight: bold;
        }
        .location-seat:hover { 
            background: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .location-seat.selected { 
            background: #dc3545;
            border-color: #c82333;
        }
        .location-seat.occupied { 
            background: #6c757d;
            border-color: #545b62;
            cursor: not-allowed;
        }
        .client-card { 
            border: 2px solid #e9ecef; 
            border-radius: 10px; 
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .client-card:hover { 
            border-color: #007bff; 
            transform: translateY(-2px);
        }
        .client-card.active { 
            border-color: #007bff; 
            background-color: #f8f9ff;
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
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h5><span id="selectedCount">0</span></h5>
                <small>Locations<br>Selected</small>
            </div>
        </div>
    </div>

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
                            <li><a class="dropdown-item" href="client_booking.php"><i class="fas fa-calendar-plus"></i> Client Booking</a></li>
                            <li><a class="dropdown-item active" href="admin_add_task.php"><i class="fas fa-building"></i> Recurring Tasks</a></li>
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

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card form-card">
                    <div class="card-header bg-gradient bg-primary text-white">
                        <h3><i class="fas fa-building"></i> Recurring Client Task Management</h3>
                        <p class="mb-0">Cinema-style location selection for bulk task creation</p>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" id="bulkTaskForm">
                            <div class="row mb-4">
                                <!-- Client Selection -->
                                <div class="col-md-3">
                                    <h5 class="text-primary mb-3"><i class="fas fa-building"></i> Select Client</h5>
                                    <?php foreach ($recurring_clients as $client_key => $client_info): ?>
                                    <div class="client-card p-3 mb-3" onclick="selectClient('<?php echo $client_key; ?>')">
                                        <input type="radio" name="client" value="<?php echo $client_key; ?>" 
                                               id="client_<?php echo $client_key; ?>" style="display: none;" required>
                                        <div class="text-center">
                                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                                            <h6><?php echo $client_info['name']; ?></h6>
                                            <small class="text-muted"><?php echo count($client_info['locations']); ?> locations</small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Service & Schedule -->
                                <div class="col-md-3">
                                    <h5 class="text-primary mb-3"><i class="fas fa-concierge-bell"></i> Service Details</h5>
                                    
                                    <div class="mb-3">
                                        <label for="service_type" class="form-label">Service Type *</label>
                                        <select class="form-control" name="service_type" required>
                                            <option value="">Select Service</option>
                                            <?php foreach ($service_types as $service_key => $service_info): ?>
                                            <option value="<?php echo $service_key; ?>">
                                                <?php echo $service_info['name']; ?> (2 hours)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="scheduled_date" class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="scheduled_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="time_slot" class="form-label">Time Slot *</label>
                                        <select class="form-control" name="time_slot" required>
                                            <option value="">Select Time</option>
                                            <option value="09:00-11:00">09:00 - 11:00 (Morning)</option>
                                            <option value="11:00-13:00">11:00 - 13:00 (Late Morning)</option>
                                            <option value="13:00-15:00">13:00 - 15:00 (Afternoon)</option>
                                            <option value="15:00-17:00">15:00 - 17:00 (Late Afternoon)</option>
                                            <option value="17:00-19:00">17:00 - 19:00 (Evening)</option>
                                            <option value="19:00-21:00">19:00 - 21:00 (Late Evening)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div class="col-md-6">
                                    <h5 class="text-primary mb-3"><i class="fas fa-info-circle"></i> Location Selection Guide</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="location-seat" style="width: 30px; height: 30px; padding: 5px; margin-right: 10px; font-size: 10px;">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                                <span>Available</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="location-seat selected" style="width: 30px; height: 30px; padding: 5px; margin-right: 10px; font-size: 10px;">
                                                    <i class="fas fa-times"></i>
                                                </div>
                                                <span>Selected</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="location-seat occupied" style="width: 30px; height: 30px; padding: 5px; margin-right: 10px; font-size: 10px;">
                                                    <i class="fas fa-ban"></i>
                                                </div>
                                                <span>Occupied</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-muted">Click locations below to select multiple areas for cleaning tasks.</p>
                                </div>
                            </div>

                            <!-- Cinema-style Location Grid -->
                            <div class="mb-4" id="locationGrid" style="display: none;">
                                <h5 class="text-primary mb-3"><i class="fas fa-map-marker-alt"></i> Select Locations</h5>
                                <div class="cinema-grid" id="cinemaGrid">
                                    <!-- Locations will be populated by JavaScript -->
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-success btn-lg" id="createTasksBtn" disabled>
                                    <i class="fas fa-plus-circle"></i> Create Tasks
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
        const clientLocations = <?php echo json_encode($recurring_clients); ?>;
        let selectedClient = null;
        let selectedLocations = [];

        function selectClient(clientKey) {
            // Remove active class from all client cards
            document.querySelectorAll('.client-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Add active class to selected card
            event.currentTarget.classList.add('active');
            
            // Check the radio button
            document.getElementById('client_' + clientKey).checked = true;
            
            selectedClient = clientKey;
            selectedLocations = [];
            updateCounter();
            showLocationGrid();
        }

        function showLocationGrid() {
            if (!selectedClient) return;
            
            const grid = document.getElementById('cinemaGrid');
            const gridContainer = document.getElementById('locationGrid');
            
            grid.innerHTML = '';
            
            const locations = clientLocations[selectedClient].locations;
            
            locations.forEach(location => {
                const seatDiv = document.createElement('div');
                seatDiv.className = 'location-seat';
                seatDiv.textContent = location;
                seatDiv.onclick = () => toggleLocation(location, seatDiv);
                
                // Add hidden input for form submission
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'locations[]';
                hiddenInput.value = location;
                hiddenInput.id = 'location_' + location.replace(/\s+/g, '_');
                hiddenInput.disabled = true;
                
                seatDiv.appendChild(hiddenInput);
                grid.appendChild(seatDiv);
            });
            
            gridContainer.style.display = 'block';
        }

        function toggleLocation(location, seatElement) {
            const hiddenInput = seatElement.querySelector('input');
            
            if (seatElement.classList.contains('selected')) {
                // Deselect
                seatElement.classList.remove('selected');
                hiddenInput.disabled = true;
                selectedLocations = selectedLocations.filter(loc => loc !== location);
            } else {
                // Select
                seatElement.classList.add('selected');
                hiddenInput.disabled = false;
                selectedLocations.push(location);
            }
            
            updateCounter();
        }

        function updateCounter() {
            document.getElementById('selectedCount').textContent = selectedLocations.length;
            const createBtn = document.getElementById('createTasksBtn');
            
            if (selectedLocations.length > 0) {
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
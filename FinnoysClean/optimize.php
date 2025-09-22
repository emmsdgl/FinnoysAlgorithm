<?php
require_once 'HybridScheduler.php';
session_start();

$scheduler = new HybridScheduler();
$results = null;
$systemStatus = $scheduler->getSystemStatus();
$validation = $scheduler->validateConfiguration();

// Handle optimization request
if ($_POST && isset($_POST['run_optimization'])) {
    $results = $scheduler->runOptimization();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Optimization - OptiCrew WMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .optimization-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .status-card { 
            border-radius: 15px; 
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .algorithm-badge {
            background: linear-gradient(45deg, #28a745, #007bff);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .phase-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .phase-1 { background: #007bff; }
        .phase-2 { background: #28a745; }
        .phase-3 { background: #17a2b8; }
        .results-section { display: none; }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4>Running AI Optimization...</h4>
            <p>Applying Rule-Based Algorithm and Genetic Algorithm</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-users"></i> OptiCrew WMS - Fin-noys</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a class="nav-link text-white" href="add_task.php"><i class="fas fa-plus"></i> Add Task</a>
                <a class="nav-link text-white" href="list_teams.php"><i class="fas fa-users-cog"></i> Teams</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card optimization-card">
                    <div class="card-header bg-gradient bg-dark text-white text-center py-4">
                        <h2><i class="fas fa-robot"></i> AI-Powered Task Optimization</h2>
                        <p class="mb-0">Hybrid Algorithm: Rule-Based Filtering + Genetic Algorithm Optimization</p>
                        <span class="algorithm-badge mt-2 d-inline-block">OptiCrew Intelligence Engine</span>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- System Status -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3"><i class="fas fa-tachometer-alt"></i> System Status</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card status-card bg-primary text-white text-center p-3">
                                        <h3><?php echo $systemStatus['unassigned_tasks']; ?></h3>
                                        <p class="mb-0">Unassigned Tasks</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card status-card bg-success text-white text-center p-3">
                                        <h3><?php echo $systemStatus['active_teams']; ?></h3>
                                        <p class="mb-0">Active Teams</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card status-card p-3">
                                        <h6 class="text-<?php echo $systemStatus['recommendation']['type']; ?> mb-2">
                                            <i class="fas fa-lightbulb"></i> System Recommendation
                                        </h6>
                                        <p class="mb-0"><?php echo $systemStatus['recommendation']['message']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuration Validation -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3"><i class="fas fa-check-circle"></i> Configuration Validation</h5>
                            <?php if ($validation['is_valid']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>System Ready</strong> - All validation checks passed
                            </div>
                            <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Configuration Issues Found:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($validation['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($validation['warnings'])): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Warnings:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($validation['warnings'] as $warning): ?>
                                    <li><?php echo htmlspecialchars($warning); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Algorithm Overview -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3"><i class="fas fa-cogs"></i> Hybrid Algorithm Process</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <div class="phase-indicator phase-1 mx-auto mb-2">1</div>
                                        <h6>Rule-Based Filtering</h6>
                                        <small class="text-muted">Apply business constraints: skills, availability, contracts</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <div class="phase-indicator phase-2 mx-auto mb-2">2</div>
                                        <h6>Genetic Algorithm</h6>
                                        <small class="text-muted">Evolutionary optimization for best assignment combination</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <div class="phase-indicator phase-3 mx-auto mb-2">3</div>
                                        <h6>Assignment Application</h6>
                                        <small class="text-muted">Update database with optimized assignments</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Optimization Controls -->
                        <div class="text-center mb-4">
                            <form method="POST" id="optimizationForm">
                                <input type="hidden" name="run_optimization" value="1">
                                <button type="submit" class="btn btn-danger btn-lg px-5" 
                                        <?php echo !$validation['ready_for_optimization'] ? 'disabled' : ''; ?>>
                                    <i class="fas fa-robot"></i> Run AI Optimization
                                </button>
                            </form>
                            <?php if (!$validation['ready_for_optimization']): ?>
                            <p class="text-muted mt-2">Fix configuration issues above to enable optimization</p>
                            <?php endif; ?>
                        </div>

                        <!-- Results Section -->
                        <?php if ($results): ?>
                        <div class="results-section" style="display: block;">
                            <hr class="my-4">
                            <h5 class="text-primary mb-4"><i class="fas fa-chart-bar"></i> Optimization Results</h5>
                            
                            <?php if ($results['success']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>Optimization Successful!</strong><br>
                                <?php echo $results['message']; ?>
                            </div>

                            <!-- Performance Metrics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-light text-center p-3">
                                        <h4 class="text-primary"><?php echo count($results['assignments']); ?></h4>
                                        <small>Tasks Assigned</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light text-center p-3">
                                        <h4 class="text-success"><?php echo number_format($results['statistics']['genetic_algorithm_phase']['coverage_rate'], 1); ?>%</h4>
                                        <small>Coverage Rate</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light text-center p-3">
                                        <h4 class="text-info"><?php echo number_format($results['statistics']['genetic_algorithm_phase']['average_fitness'], 3); ?></h4>
                                        <small>Avg Fitness Score</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light text-center p-3">
                                        <h4 class="text-warning"><?php echo number_format($results['execution_time'], 3); ?>s</h4>
                                        <small>Execution Time</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Statistics -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="fas fa-filter"></i> Rule-Based Phase</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-unstyled">
                                                <li>Tasks Processed: <strong><?php echo $results['statistics']['rule_based_phase']['tasks_processed']; ?></strong></li>
                                                <li>Valid Assignments: <strong><?php echo $results['statistics']['rule_based_phase']['valid_assignments_found']; ?></strong></li>
                                                <li>Avg Options/Task: <strong><?php echo $results['statistics']['rule_based_phase']['average_options_per_task']; ?></strong></li>
                                                <li>Filtering Efficiency: <strong><?php echo number_format($results['statistics']['rule_based_phase']['filtering_efficiency'], 1); ?>%</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="fas fa-dna"></i> Genetic Algorithm Phase</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php $priorityDist = $results['statistics']['genetic_algorithm_phase']['priority_distribution']; ?>
                                            <p><strong>Priority Distribution:</strong></p>
                                            <?php foreach ($priorityDist as $priority => $count): ?>
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo ucfirst($priority); ?>:</span>
                                                <strong><?php echo $count; ?> tasks</strong>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Optimization Failed!</strong><br>
                                <?php echo $results['message']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer bg-light text-center">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    Powered by OptiCrew AI<br>
                                    Hybrid Rule-Based + Genetic Algorithm
                                </small>
                            </div>
                            <div class="col-md-4">
                                <a href="team_tasks.php?team_id=1" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Assignments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('optimizationForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });

        // Hide loading overlay if results are shown
        <?php if ($results): ?>
        document.getElementById('loadingOverlay').style.display = 'none';
        <?php endif; ?>
    </script>
</body>
</html>
<?php
require_once 'RuleBasedAlgorithm.php';
require_once 'GeneticAlgorithm.php';

/**
 * Hybrid Scheduler combining Rule-Based and Genetic Algorithm approaches
 * 
 * This is the main scheduling engine that orchestrates the two-phase optimization:
 * Phase 1: Rule-Based filtering to ensure all assignments meet business constraints
 * Phase 2: Genetic Algorithm optimization to find the best overall assignment combination
 * 
 * The hybrid approach balances determinism (rule-based) with adaptability (GA),
 * ensuring constraint adherence while optimizing for multiple objectives.
 */
class HybridScheduler {
    private $db;
    private $ruleBasedAlgorithm;
    private $geneticAlgorithm;
    
    public function __construct() {
        $this->db = getDB();
        $this->ruleBasedAlgorithm = new RuleBasedAlgorithm();
        $this->geneticAlgorithm = new GeneticAlgorithm();
    }
    
    /**
     * Main scheduling method - orchestrates the hybrid optimization process
     * 
     * @return array Results of the scheduling operation including assignments and statistics
     */
    public function runOptimization() {
        $startTime = microtime(true);
        
        try {
            // Phase 1: Rule-Based Filtering
            $phase1Results = $this->phaseOneRuleBasedFiltering();
            
            if (empty($phase1Results['filtered_assignments'])) {
                return [
                    'success' => false,
                    'message' => 'No valid assignments found after rule-based filtering',
                    'phase1_results' => $phase1Results,
                    'assignments' => [],
                    'statistics' => [],
                    'execution_time' => microtime(true) - $startTime
                ];
            }
            
            // Phase 2: Genetic Algorithm Optimization
            $phase2Results = $this->phaseTwoGeneticOptimization($phase1Results['filtered_assignments']);
            
            // Phase 3: Apply assignments to database
            $applicationResults = $this->phaseThreeApplyAssignments($phase2Results['optimized_assignments']);
            
            $endTime = microtime(true);
            
            // Add detailed debug information
            $debugInfo = $this->generateDebugInfo($phase1Results, $phase2Results, $applicationResults);
            
            return [
                'success' => true,
                'message' => sprintf(
                    'Successfully optimized %d tasks across %d teams using hybrid algorithm',
                    $phase1Results['total_tasks'],
                    $phase1Results['total_teams']
                ),
                'phase1_results' => $phase1Results,
                'phase2_results' => $phase2Results,
                'application_results' => $applicationResults,
                'assignments' => $phase2Results['optimized_assignments'],
                'statistics' => $this->calculateStatistics($phase1Results, $phase2Results),
                'execution_time' => $endTime - $startTime,
                'debug_info' => $debugInfo
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Optimization failed: ' . $e->getMessage(),
                'error' => $e->getTraceAsString(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * Phase 1: Rule-Based Filtering
     * Apply business rules to filter valid task-team assignments
     */
    private function phaseOneRuleBasedFiltering() {
        // Get unassigned tasks and active teams
        $unassignedTasks = $this->ruleBasedAlgorithm->getUnassignedTasks();
        $activeTeams = $this->ruleBasedAlgorithm->getActiveTeams();
        
        if (empty($unassignedTasks)) {
            return [
                'filtered_assignments' => [],
                'total_tasks' => 0,
                'total_teams' => count($activeTeams),
                'filtering_stats' => [
                    'tasks_processed' => 0,
                    'valid_assignments_found' => 0,
                    'average_options_per_task' => 0
                ]
            ];
        }
        
        // Apply rule-based filtering
        $filteredAssignments = $this->ruleBasedAlgorithm->filterAssignments($unassignedTasks, $activeTeams);
        
        // Calculate filtering statistics
        $totalOptions = 0;
        $validAssignmentsFound = 0;
        
        foreach ($filteredAssignments as $taskAssignments) {
            $optionCount = $taskAssignments['total_options'];
            $totalOptions += $optionCount;
            if ($optionCount > 0) {
                $validAssignmentsFound++;
            }
        }
        
        $averageOptionsPerTask = count($unassignedTasks) > 0 ? $totalOptions / count($unassignedTasks) : 0;
        
        return [
            'filtered_assignments' => $filteredAssignments,
            'total_tasks' => count($unassignedTasks),
            'total_teams' => count($activeTeams),
            'filtering_stats' => [
                'tasks_processed' => count($unassignedTasks),
                'valid_assignments_found' => $validAssignmentsFound,
                'total_assignment_options' => $totalOptions,
                'average_options_per_task' => round($averageOptionsPerTask, 2)
            ]
        ];
    }
    
    /**
     * Phase 2: Genetic Algorithm Optimization
     * Find optimal assignment combination using evolutionary computation
     */
    private function phaseTwoGeneticOptimization($filteredAssignments) {
        // Configure GA parameters based on problem size
        $taskCount = count($filteredAssignments);
        $this->configureGAParameters($taskCount);
        
        // Run genetic algorithm
        $optimizedAssignments = $this->geneticAlgorithm->optimize($filteredAssignments);
        
        // Calculate optimization statistics
        $optimizationStats = $this->calculateOptimizationStats($optimizedAssignments, $filteredAssignments);
        
        return [
            'optimized_assignments' => $optimizedAssignments,
            'optimization_stats' => $optimizationStats
        ];
    }
    
    /**
     * Phase 3: Apply assignments to database
     * Update task assignments in the database
     */
    private function phaseThreeApplyAssignments($assignments) {
        $assignedTasks = 0;
        $errors = [];
        
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("UPDATE tasks SET team_id = ?, status = 'pending' WHERE id = ?");
            
            foreach ($assignments as $assignment) {
                $success = $stmt->execute([$assignment['team_id'], $assignment['task_id']]);
                
                if ($success) {
                    $assignedTasks++;
                } else {
                    $errors[] = "Failed to assign task {$assignment['task_id']} to team {$assignment['team_id']}";
                }
            }
            
            $this->db->commit();
            
            return [
                'assigned_tasks' => $assignedTasks,
                'total_assignments' => count($assignments),
                'errors' => $errors,
                'success_rate' => count($assignments) > 0 ? ($assignedTasks / count($assignments)) * 100 : 0
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Database error during assignment application: " . $e->getMessage());
        }
    }
    
    /**
     * Configure GA parameters based on problem complexity
     */
    private function configureGAParameters($taskCount) {
        // Adjust parameters based on problem size
        if ($taskCount <= 5) {
            $this->geneticAlgorithm->setParameters(30, 50, 0.15, 0.7); // Small problems
        } elseif ($taskCount <= 15) {
            $this->geneticAlgorithm->setParameters(50, 75, 0.1, 0.8); // Medium problems
        } else {
            $this->geneticAlgorithm->setParameters(80, 100, 0.08, 0.85); // Large problems
        }
    }
    
    /**
     * Calculate optimization statistics
     */
    private function calculateOptimizationStats($optimizedAssignments, $filteredAssignments) {
        if (empty($optimizedAssignments)) {
            return [
                'assignments_made' => 0,
                'coverage_rate' => 0,
                'average_fitness' => 0,
                'priority_distribution' => []
            ];
        }
        
        $assignmentsMade = count($optimizedAssignments);
        $totalTasks = count($filteredAssignments);
        $coverageRate = $totalTasks > 0 ? ($assignmentsMade / $totalTasks) * 100 : 0;
        
        $totalFitness = array_sum(array_column($optimizedAssignments, 'fitness_score'));
        $averageFitness = $assignmentsMade > 0 ? $totalFitness / $assignmentsMade : 0;
        
        // Priority distribution
        $priorityDistribution = [];
        foreach ($optimizedAssignments as $assignment) {
            $priority = $assignment['task']['priority_level'];
            $priorityDistribution[$priority] = ($priorityDistribution[$priority] ?? 0) + 1;
        }
        
        return [
            'assignments_made' => $assignmentsMade,
            'total_tasks' => $totalTasks,
            'coverage_rate' => round($coverageRate, 2),
            'average_fitness' => round($averageFitness, 4),
            'priority_distribution' => $priorityDistribution
        ];
    }
    
    /**
     * Calculate comprehensive statistics
     */
    private function calculateStatistics($phase1Results, $phase2Results) {
        $stats = [
            'rule_based_phase' => [
                'tasks_processed' => $phase1Results['filtering_stats']['tasks_processed'],
                'valid_assignments_found' => $phase1Results['filtering_stats']['valid_assignments_found'],
                'average_options_per_task' => $phase1Results['filtering_stats']['average_options_per_task'],
                'filtering_efficiency' => $phase1Results['total_tasks'] > 0 ? 
                    ($phase1Results['filtering_stats']['valid_assignments_found'] / $phase1Results['total_tasks']) * 100 : 0
            ],
            'genetic_algorithm_phase' => $phase2Results['optimization_stats'],
            'overall_performance' => [
                'total_execution_phases' => 3,
                'algorithm_combination' => 'Rule-Based + Genetic Algorithm',
                'optimization_approach' => 'Hybrid (Deterministic + Evolutionary)'
            ]
        ];
        
        return $stats;
    }
    
    /**
     * Get current system status for dashboard display
     */
    public function getSystemStatus() {
        $unassignedTasks = $this->ruleBasedAlgorithm->getUnassignedTasks();
        $activeTeams = $this->ruleBasedAlgorithm->getActiveTeams();
        
        // Get recent optimization history (if you want to track this)
        $recentOptimizations = $this->getRecentOptimizations();
        
        return [
            'unassigned_tasks' => count($unassignedTasks),
            'active_teams' => count($activeTeams),
            'system_ready' => count($activeTeams) > 0,
            'recommendation' => $this->generateRecommendation($unassignedTasks, $activeTeams),
            'recent_optimizations' => $recentOptimizations
        ];
    }
    
    /**
     * Generate system recommendation based on current state
     */
    private function generateRecommendation($unassignedTasks, $activeTeams) {
        if (empty($activeTeams)) {
            return [
                'type' => 'warning',
                'message' => 'No active teams available. Create teams before running optimization.',
                'action' => 'create_teams'
            ];
        }
        
        if (empty($unassignedTasks)) {
            return [
                'type' => 'success',
                'message' => 'All tasks are assigned. System is optimally configured.',
                'action' => 'none'
            ];
        }
        
        $urgentTasks = array_filter($unassignedTasks, function($task) {
            return $task['priority_level'] === 'urgent';
        });
        
        if (!empty($urgentTasks)) {
            return [
                'type' => 'urgent',
                'message' => sprintf('You have %d urgent tasks awaiting assignment. Run optimization immediately.', count($urgentTasks)),
                'action' => 'run_optimization'
            ];
        }
        
        return [
            'type' => 'info',
            'message' => sprintf('Ready to optimize %d tasks across %d teams.', count($unassignedTasks), count($activeTeams)),
            'action' => 'run_optimization'
        ];
    }
    
    /**
     * Get recent optimization runs (placeholder - you could implement this with a log table)
     */
    private function getRecentOptimizations($limit = 5) {
        // This is a placeholder - you could implement optimization logging
        return [];
    }
    
    /**
     * Validate system configuration before optimization
     */
    public function validateConfiguration() {
        $errors = [];
        $warnings = [];
        
        // Check teams
        $activeTeams = $this->ruleBasedAlgorithm->getActiveTeams();
        if (empty($activeTeams)) {
            $errors[] = 'No active teams found. Create at least one team with 3 members.';
        }
        
        // Check for teams with incomplete membership
        foreach ($activeTeams as $team) {
            if ($team['member_count'] < 3) {
                $warnings[] = "Team '{$team['team_name']}' has only {$team['member_count']} members. Optimal teams have 3 members.";
            }
        }
        
        // Check tasks
        $unassignedTasks = $this->ruleBasedAlgorithm->getUnassignedTasks();
        if (empty($unassignedTasks)) {
            $warnings[] = 'No unassigned tasks found. All tasks may already be optimally assigned.';
        }
        
        // Check employee availability
        $employeesWithoutAvailability = $this->checkEmployeeAvailability();
        if (!empty($employeesWithoutAvailability)) {
            $warnings[] = sprintf('%d employees have no availability data, which may limit optimization effectiveness.', count($employeesWithoutAvailability));
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'ready_for_optimization' => empty($errors) && !empty($activeTeams) && !empty($unassignedTasks)
        ];
    }
    
    /**
     * Check which employees lack availability data
     */
    private function checkEmployeeAvailability() {
        $stmt = $this->db->query("
            SELECT e.id, e.name
            FROM employees e
            LEFT JOIN employee_availability ea ON e.id = ea.employee_id
            WHERE (e.end_date IS NULL OR e.end_date >= CURRENT_DATE)
            AND ea.employee_id IS NULL
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate detailed debug information for testing and analysis
     */
    private function generateDebugInfo($phase1Results, $phase2Results, $applicationResults) {
        $debugInfo = [
            'rule_based_filtering' => [],
            'genetic_algorithm' => [],
            'final_assignments' => [],
            'unassigned_tasks' => []
        ];
        
        // Rule-Based Filtering Debug Info
        if (!empty($phase1Results['filtered_assignments'])) {
            foreach ($phase1Results['filtered_assignments'] as $taskId => $assignmentData) {
                $task = $assignmentData['task'];
                $debugInfo['rule_based_filtering'][] = [
                    'task_id' => $taskId,
                    'task_title' => $task['title'],
                    'required_skills' => json_decode($task['required_skills'], true),
                    'priority' => $task['priority_level'],
                    'scheduled_time' => $task['scheduled_date'] . ' ' . $task['time_slot'],
                    'feasible_teams_count' => count($assignmentData['valid_teams']),
                    'feasible_teams' => array_map(function($teamData) {
                        return [
                            'team_name' => $teamData['team']['team_name'],
                            'suitability_score' => $teamData['suitability_score'],
                            'skill_match' => $teamData['skills_match'],
                            'availability_score' => $teamData['availability_score']
                        ];
                    }, $assignmentData['valid_teams'])
                ];
            }
        }
        
        // GA Debug Info
        $debugInfo['genetic_algorithm'] = [
            'optimization_stats' => $phase2Results['optimization_stats'] ?? [],
            'population_analysis' => 'GA found optimal assignment combinations'
        ];
        
        // Final Assignments Debug Info
        if (!empty($phase2Results['optimized_assignments'])) {
            foreach ($phase2Results['optimized_assignments'] as $assignment) {
                $debugInfo['final_assignments'][] = [
                    'task_id' => $assignment['task_id'],
                    'task_title' => $assignment['task']['title'],
                    'assigned_team' => $assignment['team']['team_name'],
                    'fitness_score' => $assignment['fitness_score'],
                    'priority' => $assignment['task']['priority_level'],
                    'scheduled_time' => $assignment['task']['scheduled_date'] . ' ' . $assignment['task']['time_slot']
                ];
            }
        }
        
        // Find unassigned tasks
        $assignedTaskIds = array_column($phase2Results['optimized_assignments'] ?? [], 'task_id');
        $allTaskIds = array_keys($phase1Results['filtered_assignments'] ?? []);
        $unassignedTaskIds = array_diff($allTaskIds, $assignedTaskIds);
        
        foreach ($unassignedTaskIds as $taskId) {
            $assignmentData = $phase1Results['filtered_assignments'][$taskId];
            $task = $assignmentData['task'];
            
            $reason = 'Unknown';
            if (empty($assignmentData['valid_teams'])) {
                $reason = 'No teams available with required skills or meeting constraints';
            } else {
                $reason = 'Could not find optimal assignment (may conflict with higher priority tasks)';
            }
            
            $debugInfo['unassigned_tasks'][] = [
                'task_id' => $taskId,
                'task_title' => $task['title'],
                'priority' => $task['priority_level'],
                'required_skills' => json_decode($task['required_skills'], true),
                'scheduled_time' => $task['scheduled_date'] . ' ' . $task['time_slot'],
                'reason' => $reason,
                'feasible_teams_count' => count($assignmentData['valid_teams'])
            ];
        }
        
        return $debugInfo;
    }
}
?>
<?php
require_once 'RuleBasedAlgorithm.php';

/**
 * Genetic Algorithm for Task Assignment Optimization
 * This class implements a genetic algorithm that optimizes task assignments after the rule-based filtering.
 */
class GeneticAlgorithm {
    private $db;
    private $ruleBasedAlgorithm;

    // GA Parameters
    private $populationSize = 50;
    private $generations = 100;
    private $mutationRate = 0.1;
    private $crossoverRate = 0.8;
    private $elitismRate = 0.2;

    // Fitness weights
    private $weights = [
        // 'workload_balance' => 0.38,      // Increased from 30%
        // 'priority_satisfaction' => 0.31, // Increased from 25%
        // 'availability_match' => 0.19,    // Increased from 15%
        // 'time_constraints' => 0.12       // Increased from 10%

        'workload_balance' => 0.30,
        'priority_satisfaction' => 0.25,
        'availability_match' => 0.15,
        'time_constraints' => 0.10,

        'skill_utilization' => 0.20
    ];

    public function __construct() {
        $this->db = getDB();
        $this->ruleBasedAlgorithm = new RuleBasedAlgorithm();
    }

    /**
     * Main genetic algorithm optimization method
     */
    public function optimize($filteredAssignments) {
        if (empty($filteredAssignments)) {
            return [];
        }

        // Initialize population
        $population = $this->initializePopulation($filteredAssignments);
        $bestFitness = -PHP_FLOAT_MAX;
        $stagnationCounter = 0;
        $maxStagnation = 20;

        for ($generation = 0; $generation < $this->generations; $generation++) {
            // Evaluate fitness for all individuals
            $fitnessScores = [];
            foreach ($population as $individual) {
                $fitnessScores[] = $this->evaluateFitness($individual, $filteredAssignments);
            }

            // Check for improvement
            $currentBest = max($fitnessScores);
            if ($currentBest > $bestFitness) {
                $bestFitness = $currentBest;
                $stagnationCounter = 0;
            } else {
                $stagnationCounter++;
            }

            // Early termination if stagnated
            if ($stagnationCounter >= $maxStagnation) {
                break;
            }

            // Selection and reproduction
            $population = $this->evolvePopulation($population, $fitnessScores, $filteredAssignments);
        }

        // Return best solution
        $finalFitnessScores = [];
        foreach ($population as $individual) {
            $finalFitnessScores[] = $this->evaluateFitness($individual, $filteredAssignments);
        }
        $bestIndex = array_search(max($finalFitnessScores), $finalFitnessScores);
        $bestSolution = $population[$bestIndex];

        return $this->convertToAssignments($bestSolution, $filteredAssignments);
    }
    
    // --- GA Core Logic (Population, Fitness, Evolution) ---
    // Note: The rest of the functions in this file are mostly PHP logic and do not need changes,
    // except for getCurrentTeamWorkload.

    private function initializePopulation($filteredAssignments) {
        $population = [];
        for ($i = 0; $i < $this->populationSize; $i++) {
            $individual = [];
            foreach ($filteredAssignments as $taskId => $assignmentData) {
                if (!empty($assignmentData['valid_teams'])) {
                    $validTeams = $assignmentData['valid_teams'];
                    $selectedTeam = $this->selectTeamWithBias($validTeams);
                    if ($selectedTeam) {
                        $individual[$taskId] = $selectedTeam['team']['id'];
                    }
                }
            }
            $population[] = $individual;
        }
        return $population;
    }

    private function selectTeamWithBias($validTeams) {
        if (empty($validTeams)) return null;
        $totalScore = array_sum(array_column($validTeams, 'suitability_score'));
        if ($totalScore == 0) return $validTeams[array_rand($validTeams)];

        $random = mt_rand() / mt_getrandmax() * $totalScore;
        $cumulativeScore = 0;
        foreach ($validTeams as $teamData) {
            $cumulativeScore += $teamData['suitability_score'];
            if ($random <= $cumulativeScore) return $teamData;
        }
        return $validTeams[0]; // Fallback
    }

    private function evaluateFitness($individual, $filteredAssignments) {
        $overlapPenalty = $this->calculateOverlapPenalty($individual, $filteredAssignments);
        if ($overlapPenalty > 0) return -1000 * $overlapPenalty;

        $fitness = 0;
        $fitness += $this->calculateWorkloadBalance($individual) * $this->weights['workload_balance'];
        $fitness += $this->calculatePrioritySatisfaction($individual, $filteredAssignments) * $this->weights['priority_satisfaction'];
        $fitness += $this->calculateSkillUtilization($individual, $filteredAssignments) * $this->weights['skill_utilization'];
        $fitness += $this->calculateAvailabilityMatch($individual, $filteredAssignments) * $this->weights['availability_match'];
        $fitness += $this->calculateTimeConstraints($individual, $filteredAssignments) * $this->weights['time_constraints'];
        
        return $fitness;
    }
    
    /**
     * Calculate workload balance score
     * Penalizes solutions where teams have very different workloads
     */
    private function calculateWorkloadBalance($individual) {
        $teamWorkloads = [];
        
        // Get current workload for each team
        $teams = $this->ruleBasedAlgorithm->getActiveTeams();
        foreach ($teams as $team) {
            $teamWorkloads[$team['id']] = $this->getCurrentTeamWorkload($team['id']);
        }
        
        // Add workload from this assignment
        foreach ($individual as $taskId => $teamId) {
            $task = $this->getTaskById($taskId);
            if ($task) {
                $teamWorkloads[$teamId] = ($teamWorkloads[$teamId] ?? 0) + $task['estimated_duration'];
            }
        }
        
        if (empty($teamWorkloads)) {
            return 1.0;
        }
        
        $workloads = array_values($teamWorkloads);
        $mean = array_sum($workloads) / count($workloads);
        $variance = array_sum(array_map(function($x) use ($mean) { 
            return pow($x - $mean, 2); 
        }, $workloads)) / count($workloads);
        
        // Lower variance = better balance = higher score
        $maxVariance = 100; // Assume maximum reasonable variance
        return max(0, 1 - ($variance / $maxVariance));
    }
        
    /**
     * Calculate priority satisfaction score
     * Ensures high-priority tasks are handled by the most suitable teams
     */
    private function calculatePrioritySatisfaction($individual, $filteredAssignments) {
        $totalPriorityScore = 0;
        $maxPossibleScore = 0;
        
        $priorityWeights = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        
        foreach ($individual as $taskId => $assignedTeamId) {
            $assignmentData = $filteredAssignments[$taskId];
            $task = $assignmentData['task'];
            
            $priorityWeight = $priorityWeights[$task['priority_level']];
            $maxPossibleScore += $priorityWeight;
            
            // Find the assigned team's suitability score
            foreach ($assignmentData['valid_teams'] as $teamData) {
                if ($teamData['team']['id'] == $assignedTeamId) {
                    $suitabilityScore = $teamData['suitability_score'] / 100; // Normalize to 0-1
                    $totalPriorityScore += $priorityWeight * $suitabilityScore;
                    break;
                }
            }
        }
        
        return $maxPossibleScore > 0 ? $totalPriorityScore / $maxPossibleScore : 1.0;
    }
        
    /**
     * Calculate skill utilization efficiency
     * Rewards using teams with the best skill matches
     */
    private function calculateSkillUtilization($individual, $filteredAssignments) {
        $totalSkillScore = 0;
        $taskCount = count($individual);
        
        foreach ($individual as $taskId => $assignedTeamId) {
            $assignmentData = $filteredAssignments[$taskId];
            
            // Find skill match score for assigned team
            foreach ($assignmentData['valid_teams'] as $teamData) {
                if ($teamData['team']['id'] == $assignedTeamId) {
                    $skillsMatch = $teamData['skills_match'];
                    $requiredCount = count($skillsMatch['required']);
                    $matchingCount = $requiredCount - count($skillsMatch['missing']);
                    
                    $skillScore = $requiredCount > 0 ? $matchingCount / $requiredCount : 1.0;
                    $totalSkillScore += $skillScore;
                    break;
                }
            }
        }
        
        return $taskCount > 0 ? $totalSkillScore / $taskCount : 1.0;
    }
        
    /**
     * Calculate availability match score
     * Ensures teams are available when tasks are scheduled
     */
    private function calculateAvailabilityMatch($individual, $filteredAssignments) {
        $totalAvailabilityScore = 0;
        $taskCount = count($individual);
        
        foreach ($individual as $taskId => $assignedTeamId) {
            $assignmentData = $filteredAssignments[$taskId];
            
            // Find availability score for assigned team
            foreach ($assignmentData['valid_teams'] as $teamData) {
                if ($teamData['team']['id'] == $assignedTeamId) {
                    $availabilityScore = $teamData['availability_score'];
                    $totalAvailabilityScore += $availabilityScore;
                    break;
                }
            }
        }
        
        return $taskCount > 0 ? $totalAvailabilityScore / $taskCount : 1.0;
    }

    /**
     * Calculate time constraints satisfaction
     * Ensures scheduled tasks don't conflict
     */
    private function calculateTimeConstraints($individual, $filteredAssignments) {
        $conflicts = 0;
        $totalConstraints = 0;
        
        // Group tasks by team and check for time conflicts
        $teamSchedules = [];
        foreach ($individual as $taskId => $teamId) {
            $task = $filteredAssignments[$taskId]['task'];
            if ($task['scheduled_date'] && $task['time_slot']) {
                $teamSchedules[$teamId][] = [
                    'date' => $task['scheduled_date'],
                    'time_slot' => $task['time_slot'],
                    'task_id' => $taskId
                ];
                $totalConstraints++;
            }
        }
        
        foreach ($teamSchedules as $schedule) {
            for ($i = 0; $i < count($schedule); $i++) {
                for ($j = $i + 1; $j < count($schedule); $j++) {
                    if ($this->hasTimeConflict($schedule[$i], $schedule[$j])) {
                        $conflicts++;
                    }
                }
            }
        }
        
        return $totalConstraints > 0 ? max(0, 1 - ($conflicts / $totalConstraints)) : 1.0;
    }

    private function hasTimeConflict($task1, $task2) {
        // If the tasks are on different dates, they cannot conflict.
        if ($task1['date'] !== $task2['date']) {
            return false;
        }

        // If they are on the same date, they conflict only if they share the same time slot.
        return $task1['time_slot'] === $task2['time_slot'];
    }

    /**
     * Calculate overlap penalty for an individual (CRITICAL CONSTRAINT)
     * Returns number of overlapping assignments that violate the no-overlap rule
     * This method ensures teams aren't double-booked in the same time slot
     */
    private function calculateOverlapPenalty($individual, $filteredAssignments) {
        $conflicts = 0;
        $teamSchedules = [];
        
        // Group tasks by team and time slot
        foreach ($individual as $taskId => $teamId) {
            $task = $filteredAssignments[$taskId]['task'];
            if ($task['scheduled_date'] && $task['time_slot']) {
                $key = $teamId . '_' . $task['scheduled_date'] . '_' . $task['time_slot'];
                
                if (!isset($teamSchedules[$key])) {
                    $teamSchedules[$key] = [];
                }
                
                $teamSchedules[$key][] = $taskId;
            }
        }
        
        // Count conflicts (any slot with more than 1 task assigned to same team)
        foreach ($teamSchedules as $slot => $taskIds) {
            if (count($taskIds) > 1) {
                // Multiple tasks assigned to same team in same time slot = conflict
                $conflicts += count($taskIds) - 1; // Penalty proportional to number of conflicts
            }
        }
        
        return $conflicts;
    }

    /**
     * Evolve population through selection, crossover, and mutation
     */
    private function evolvePopulation($population, $fitnessScores, $filteredAssignments) {
        $populationSize = count($population);
        $eliteCount = (int)($populationSize * $this->elitismRate);
        
        // Sort by fitness (descending)
        $indexed = array_map(function($i, $fitness) use ($population) {
            return ['individual' => $population[$i], 'fitness' => $fitness, 'index' => $i];
        }, array_keys($population), $fitnessScores);
        
        usort($indexed, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });
        
        $newPopulation = [];
        
        // Elitism: Keep best individuals
        for ($i = 0; $i < $eliteCount; $i++) {
            $newPopulation[] = $indexed[$i]['individual'];
        }
        
        // Fill rest of population with offspring
        while (count($newPopulation) < $populationSize) {
            // Tournament selection
            $parent1 = $this->tournamentSelection($population, $fitnessScores);
            $parent2 = $this->tournamentSelection($population, $fitnessScores);
            
            // Crossover
            if (mt_rand() / mt_getrandmax() < $this->crossoverRate) {
                list($child1, $child2) = $this->crossover($parent1, $parent2, $filteredAssignments);
                $newPopulation[] = $child1;
                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $child2;
                }
            } else {
                $newPopulation[] = $parent1;
                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $parent2;
                }
            }
        }
        
        // Mutation
        for ($i = $eliteCount; $i < count($newPopulation); $i++) {
            if (mt_rand() / mt_getrandmax() < $this->mutationRate) {
                $newPopulation[$i] = $this->mutate($newPopulation[$i], $filteredAssignments);
            }
        }
        
        return array_slice($newPopulation, 0, $populationSize);
    }

    /**
     * Tournament selection for parent selection
     */
    private function tournamentSelection($population, $fitnessScores, $tournamentSize = 3) {
        $tournamentSize = min($tournamentSize, count($population));
        $competitors = [];
        
        for ($i = 0; $i < $tournamentSize; $i++) {
            $index = mt_rand(0, count($population) - 1);
            $competitors[] = ['individual' => $population[$index], 'fitness' => $fitnessScores[$index]];
        }
        
        usort($competitors, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });
        
        return $competitors[0]['individual'];
    }

    /**
     * Single-point crossover
     */
    private function crossover($parent1, $parent2, $filteredAssignments) {
        $taskIds = array_keys($parent1);
        if (empty($taskIds)) {
            return [$parent1, $parent2];
        }
        
        $crossoverPoint = mt_rand(1, count($taskIds) - 1);
        
        $child1 = [];
        $child2 = [];
        
        for ($i = 0; $i < count($taskIds); $i++) {
            $taskId = $taskIds[$i];
            
            if ($i < $crossoverPoint) {
                $child1[$taskId] = $parent1[$taskId];
                $child2[$taskId] = $parent2[$taskId];
            } else {
                $child1[$taskId] = $parent2[$taskId];
                $child2[$taskId] = $parent1[$taskId];
            }
        }
        
        // Ensure validity
        $child1 = $this->ensureValidAssignment($child1, $filteredAssignments);
        $child2 = $this->ensureValidAssignment($child2, $filteredAssignments);
        
        return [$child1, $child2];
    }

    /**
     * Mutation operation
     */
    private function mutate($individual, $filteredAssignments) {
        $taskIds = array_keys($individual);
        if (empty($taskIds)) {
            return $individual;
        }
        
        // Select random task to mutate
        $taskId = $taskIds[mt_rand(0, count($taskIds) - 1)];
        
        // Assign to different valid team
        $assignmentData = $filteredAssignments[$taskId];
        if (!empty($assignmentData['valid_teams'])) {
            $validTeams = $assignmentData['valid_teams'];
            $newTeam = $validTeams[mt_rand(0, count($validTeams) - 1)];
            $individual[$taskId] = $newTeam['team']['id'];
        }
        
        return $individual;
    }

    /**
     * Ensure assignment is valid according to filtered assignments
     */
    private function ensureValidAssignment($individual, $filteredAssignments) {
        foreach ($individual as $taskId => $teamId) {
            $assignmentData = $filteredAssignments[$taskId];
            $validTeamIds = array_column(array_column($assignmentData['valid_teams'], 'team'), 'id');
            
            if (!in_array($teamId, $validTeamIds)) {
                // Assign to first valid team if current assignment is invalid
                if (!empty($assignmentData['valid_teams'])) {
                    $individual[$taskId] = $assignmentData['valid_teams'][0]['team']['id'];
                }
            }
        }
        
        return $individual;
    }

    // THIS IS THE NEW, CORRECTED AND COMPATIBLE CODE
    private function convertToAssignments($solution, $filteredAssignments) {
        $assignments = [];
        $teamSlotUsage = []; // This will track which team is busy at which time slot.

        // Sort the tasks by priority to ensure urgent tasks are processed and assigned first.
        $sortedTaskIds = array_keys($solution);
        usort($sortedTaskIds, function($a, $b) use ($filteredAssignments) {
            $priorityOrder = ['urgent' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
            $taskA = $filteredAssignments[$a]['task'];
            $taskB = $filteredAssignments[$b]['task'];
            return $priorityOrder[$taskA['priority_level']] <=> $priorityOrder[$taskB['priority_level']];
        });

        // Loop through the GA's recommended assignments, in order of task priority.
        foreach ($sortedTaskIds as $taskId) {
            if (!isset($solution[$taskId])) continue; // Skip if GA provided no solution for this task

            $teamId = $solution[$taskId];
            $task = $filteredAssignments[$taskId]['task'];
            $team = $this->getTeamById($teamId);

            if ($task && $team) {
                // Create a unique key for the team, date, and time slot.
                $slotKey = $teamId . '_' . $task['scheduled_date'] . '_' . $task['time_slot'];

                // CRITICAL LOGIC: Only assign the task if the team is not already busy at this time.
                if (!isset($teamSlotUsage[$slotKey])) {
                    // This slot is free! Assign the task.
                    $assignments[] = [
                        'task_id' => $taskId,
                        'team_id' => $teamId,
                        'task' => $task,
                        'team' => $team,
                        // Re-evaluate fitness for the final solution for accurate display
                        'fitness_score' => $this->evaluateFitness($solution, $filteredAssignments)
                    ];

                    // Mark this slot as "taken" so no other tasks can be assigned to this team at this time.
                    $teamSlotUsage[$slotKey] = $taskId;
                }
                // If the slot IS taken, we do nothing. The higher-priority task already has it,
                // so this current, lower-priority task will correctly remain unassigned.
            }
        }
        
        return $assignments;
    }

    private function getCurrentTeamWorkload($teamId) {
        // --- CHANGED: Swapped PostgreSQL interval syntax for MySQL DATE_SUB function ---
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(estimated_duration), 0) as total_hours
            FROM tasks
            WHERE team_id = ?
            AND status != 'completed'
            AND scheduled_date >= DATE_SUB(CURRENT_DATE, INTERVAL 21 DAY)
        ");
        $stmt->execute([$teamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_hours'];
    }

    private function getTaskById($taskId) {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getTeamById($teamId) {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // --- Public setters ---

    public function setParameters($populationSize = null, $generations = null, $mutationRate = null, $crossoverRate = null) {
        if ($populationSize !== null) $this->populationSize = $populationSize;
        if ($generations !== null) $this->generations = $generations;
        if ($mutationRate !== null) $this->mutationRate = $mutationRate;
        if ($crossoverRate !== null) $this->crossoverRate = $crossoverRate;
    }

    public function setWeights($weights) {
        $this->weights = array_merge($this->weights, $weights);
    }
}
?>
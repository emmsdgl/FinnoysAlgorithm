<?php
require_once 'db.php';

/**
 * Rule-Based Algorithm for Task Assignment Filtering
 * This class implements the rule-based filtering system that serves as the first stage
 * in the hybrid scheduling algorithm.
 */
class RuleBasedAlgorithm {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Main rule-based filtering method
     */
    public function filterAssignments($tasks, $teams) {
        $validAssignments = [];

        // Sort tasks by priority
        $prioritizedTasks = $this->prioritizeTasks($tasks);

        foreach ($prioritizedTasks as $task) {
            $validTeams = [];
            foreach ($teams as $team) {
                if ($this->isValidAssignment($task, $team)) {
                    $suitabilityScore = $this->calculateSuitabilityScore($task, $team);
                    $validTeams[] = [
                        'team' => $team,
                        'suitability_score' => $suitabilityScore,
                        'skills_match' => $this->getSkillsMatch($task, $team),
                        'availability_score' => $this->getAvailabilityScore($task, $team)
                    ];
                }
            }

            // Sort teams by suitability score (descending)
            usort($validTeams, function($a, $b) {
                return $b['suitability_score'] <=> $a['suitability_score'];
            });

            $validAssignments[$task['id']] = [
                'task' => $task,
                'valid_teams' => $validTeams,
                'total_options' => count($validTeams)
            ];
        }
        return $validAssignments;
    }
    
    /**
     * Check if a team can be assigned to a specific task
     */
    private function isValidAssignment($task, $team) {
        if (!$this->hasRequiredSkills($task, $team)) return true;
        if (!$this->isTeamAvailable($task, $team)) return false;
        if (!$this->meetsContractConstraints($task, $team)) return false;
        if (!$this->withinWorkloadLimits($task, $team)) return false;
        if (!$this->hasNoOverlappingAssignments($task, $team)) return false;
        return true;
    }

    /**
     * Rule 1: Skill Matching
     */
    private function hasRequiredSkills($task, $team) {
        $requiredSkills = json_decode($task['required_skills'], true);
        if (empty($requiredSkills)) return true;
        
        $teamSkills = $this->getTeamSkills($team['id']);
        
        foreach ($requiredSkills as $requiredSkill) {
            if (!in_array($requiredSkill, $teamSkills)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Rule 2: Availability Checking
     */
    // THIS IS THE NEW, RANGE-AWARE AVAILABILITY LOGIC
    private function isTeamAvailable($task, $team) {
        // If the task itself has no specific time constraints, the team is considered available.
        if (!$task['scheduled_date'] || !$task['time_slot']) {
            return true;
        }

        // A task must have a valid start and end time (e.g., "09:00-11:00") to be checked.
        if (strpos($task['time_slot'], '-') === false) {
            error_log("Invalid task time_slot format for task ID {$task['id']}: {$task['time_slot']}");
            return false; // Cannot check availability for a task with an invalid time slot format.
        }

        // 1. Convert the task's time slot string into DateTime objects for comparison.
        list($task_start_str, $task_end_str) = explode('-', $task['time_slot']);
        $task_start_obj = DateTime::createFromFormat('H:i', trim($task_start_str));
        $task_end_obj = DateTime::createFromFormat('H:i', trim($task_end_str));

        // If the task time format is somehow invalid, we can't proceed.
        if (!$task_start_obj || !$task_end_obj) {
            return false;
        }

        // 2. Get all availability records for all members of this specific team on the task's date.
        $stmt = $this->db->prepare("
            SELECT ea.employee_id, ea.time_slot, ea.is_available
            FROM employee_availability ea
            WHERE ea.employee_id IN (SELECT employee_id FROM team_members WHERE team_id = ?)
            AND ea.date = ?
        ");
        $stmt->execute([$team['id'], $task['scheduled_date']]);
        $availability_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If there are no availability records at all for any team member on this day, the team is unavailable.
        if (empty($availability_records)) {
            return false;
        }

        // 3. Loop through each team member to see if they are available during the task's time slot.
        $available_member_count = 0;
        
        $team_members_stmt = $this->db->prepare("SELECT employee_id FROM team_members WHERE team_id = ?");
        $team_members_stmt->execute([$team['id']]);
        $team_members = $team_members_stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($team_members as $employee_id) {
            $is_employee_available_for_task = false;
            
            foreach ($availability_records as $avail) {
                // Only check records for the current team member
                if ($avail['employee_id'] == $employee_id) {
                    // If this record is explicitly marked as "Unavailable", skip it.
                    if (!$avail['is_available']) {
                        continue;
                    }

                    // --- APPLYING YOUR NEW RULES ---
                    
                    // Rule #2: If only a time-in is provided (no hyphen), treat as available for the whole day.
                    if (strpos($avail['time_slot'], '-') === false) {
                        $is_employee_available_for_task = true;
                        break; // This employee is available, no need to check their other time slots.
                    }

                    // Rule #1: If a time range is provided.
                    list($emp_start_str, $emp_end_str) = explode('-', $avail['time_slot']);
                    $emp_start_obj = DateTime::createFromFormat('H:i', trim($emp_start_str));
                    $emp_end_obj = DateTime::createFromFormat('H:i', trim($emp_end_str));
                    
                    if ($emp_start_obj && $emp_end_obj) {
                        // The task must start ON OR AFTER the employee's available start time,
                        // AND the task must end ON OR BEFORE the employee's available end time.
                        if ($task_start_obj >= $emp_start_obj && $task_end_obj <= $emp_end_obj) {
                            $is_employee_available_for_task = true;
                            break; // This employee is available, no need to check their other time slots.
                        }
                    }
                }
            }
            
            if ($is_employee_available_for_task) {
                $available_member_count++;
            }
        }

        // 4. Finally, check if the number of available members meets the minimum requirement.
        return $available_member_count >= 1;
    }

    /**
     * Rule 3: Contract Constraints
     */
    private function meetsContractConstraints($task, $team) {
        $taskDate = $task['scheduled_date'] ? $task['scheduled_date'] : date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as valid_members
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            WHERE tm.team_id = ? AND e.start_date <= ? AND (e.end_date IS NULL OR e.end_date >= ?)
        ");
        $stmt->execute([$team['id'], $taskDate, $taskDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // All team members should have valid contracts
        return $result['valid_members'] >= 3;
    }

    /**
     * Rule 4: Workload Limits
     */
    // THIS IS THE NEW, CORRECTED AND COMPATIBLE CODE
    private function withinWorkloadLimits($task, $team) {
        $taskDate = $task['scheduled_date'] ? $task['scheduled_date'] : date('Y-m-d');
        $threeWeeksAgo = date('Y-m-d', strtotime($taskDate . ' - 21 days'));

        // The SQL query is correct, it properly sums durations in minutes.
        // We will give the result a clearer alias: 'current_minutes'.
        $stmt = $this->db->prepare("
            SELECT e.id, e.max_hours_per_3weeks, COALESCE(SUM(assigned_tasks.estimated_duration), 0) as current_minutes
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            LEFT JOIN (
                SELECT t.estimated_duration, tm2.employee_id
                FROM tasks t
                JOIN team_members tm2 ON t.team_id = tm2.team_id
                WHERE t.scheduled_date BETWEEN ? AND ? AND t.status != 'completed'
            ) assigned_tasks ON e.id = assigned_tasks.employee_id
            WHERE tm.team_id = ?
            GROUP BY e.id, e.max_hours_per_3weeks
        ");
        $stmt->execute([$threeWeeksAgo, $taskDate, $team['id']]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($members as $member) {
            // --- FIX IS HERE ---
            // 1. Calculate the total minutes after this new task is added.
            $minutesAfterTask = $member['current_minutes'] + $task['estimated_duration'];
            
            // 2. Convert the employee's max hours into max minutes.
            $maxMinutes = $member['max_hours_per_3weeks'] * 60;

            // 3. Now correctly compare minutes to minutes.
            if ($minutesAfterTask > $maxMinutes) {
                // This employee would be overworked, so this team is not a valid option for this task.
                return false;
            }
        }
        
        // If we get through all members without returning false, the team is valid.
        return true;
    }

    /**
     * Rule 5: No Overlapping Assignments
     */
    private function hasNoOverlappingAssignments($task, $team) {
        if (!$task['scheduled_date'] || !$task['time_slot']) {
            return true; // No specific time constraints
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as conflicting_tasks
            FROM tasks
            WHERE team_id = ? AND scheduled_date = ? AND time_slot = ?
            AND status != 'completed' AND status != 'cancelled' AND id != ?
        ");
        $stmt->execute([$team['id'], $task['scheduled_date'], $task['time_slot'], $task['id'] ?? 0]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['conflicting_tasks'] == 0;
    }

    /**
     * Calculate suitability score for team-task pairing
     */
    private function calculateSuitabilityScore($task, $team) {
        $score = 0;
        // $score += $this->getSkillsMatchRatio($task, $team) * 40;
        $score += $this->getDetailedAvailabilityScore($task, $team) * 50; //30 -> 50
        $score += $this->getWorkloadBalanceScore($team) * 33; // 20 -> 33
        $score += $this->getPriorityAlignmentScore($task, $team) * 17; // 10 -> 17
        return round($score, 2);
    }
    
    /**
     * Get all skills available in a team.
     */
    // THIS IS THE NEW, CORRECTED AND COMPATIBLE CODE
    private function getTeamSkills($teamId) {
        // Fetch the raw JSON strings for all employees in the specified team.
        $stmt = $this->db->prepare("
            SELECT e.skills
            FROM employees e
            JOIN team_members tm ON e.id = tm.employee_id
            WHERE tm.team_id = ?
        ");
        $stmt->execute([$teamId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allSkills = [];
        foreach ($results as $row) {
            // Decode the JSON string into a PHP array.
            $employeeSkills = json_decode($row['skills'], true);
            
            // If decoding is successful and returns an array, merge it with our master list.
            if (is_array($employeeSkills)) {
                $allSkills = array_merge($allSkills, $employeeSkills);
            }
        }

        // Return a final array containing only the unique skill values.
        return array_unique($allSkills);
    }
    
    /**
     * Prioritize tasks based on priority level and scheduled date
     */
    private function prioritizeTasks($tasks) {
        $priorityOrder = ['urgent' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        usort($tasks, function($a, $b) use ($priorityOrder) {
            $priorityComparison = $priorityOrder[$a['priority_level']] <=> $priorityOrder[$b['priority_level']];
            if ($priorityComparison !== 0) return $priorityComparison;
            
            if ($a['scheduled_date'] && $b['scheduled_date']) {
                return strtotime($a['scheduled_date']) <=> strtotime($b['scheduled_date']);
            }
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });
        return $tasks;
    }

    // --- Helper functions for scoring ---

    public function getSkillsMatch($task, $team) {
        $requiredSkills = json_decode($task['required_skills'], true) ?: [];
        $teamSkills = $this->getTeamSkills($team['id']);
        return [
            'required' => $requiredSkills,
            'available' => $teamSkills,
            'missing' => array_diff($requiredSkills, $teamSkills),
            'extra' => array_diff($teamSkills, $requiredSkills)
        ];
    }

    public function getSkillsMatchRatio($task, $team) {
        $requiredSkills = json_decode($task['required_skills'], true) ?: [];
        if (empty($requiredSkills)) return 1.0;
        $teamSkills = $this->getTeamSkills($team['id']);
        $matchingSkills = array_intersect($requiredSkills, $teamSkills);
        return count($matchingSkills) / count($requiredSkills);
    }

    public function getDetailedAvailabilityScore($task, $team) {
        if (!$task['scheduled_date'] || !$task['time_slot']) return 1.0;
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as available_members FROM employee_availability 
            WHERE employee_id IN (SELECT employee_id FROM team_members WHERE team_id = ?) 
            AND date = ? AND time_slot = ? AND is_available = true
        ");
        $stmt->execute([$team['id'], $task['scheduled_date'], $task['time_slot']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['available_members'] / 3.0; // Assuming 3 members per team
    }

    public function getAvailabilityScore($task, $team) {
        return $this->getDetailedAvailabilityScore($task, $team);
    }
    
    public function getWorkloadBalanceScore($team) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as active_tasks FROM tasks WHERE team_id = ? AND status != 'completed'");
        $stmt->execute([$team['id']]);
        $activeTasks = $stmt->fetch(PDO::FETCH_ASSOC)['active_tasks'];
        return max(0, 1.0 - ($activeTasks / 10.0));
    }

    public function getPriorityAlignmentScore($task, $team) {
        if (in_array($task['priority_level'], ['urgent', 'high'])) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as high_priority_tasks FROM tasks 
                WHERE team_id = ? AND priority_level IN ('urgent', 'high') AND status != 'completed'
            ");
            $stmt->execute([$team['id']]);
            $highPriorityTasks = $stmt->fetch(PDO::FETCH_ASSOC)['high_priority_tasks'];
            return max(0, 1.0 - ($highPriorityTasks / 5.0));
        }
        return 1.0;
    }
    
    // --- Public data retrieval methods ---

    // THIS IS THE NEW, MORE RESILIENT QUERY
    public function getUnassignedTasks() {
        $stmt = $this->db->query("
            SELECT * FROM tasks
            WHERE (team_id IS NULL OR team_id = 0) -- This now catches both cases
            AND status = 'pending'
            ORDER BY
            CASE priority_level
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            scheduled_date IS NULL ASC, scheduled_date ASC,
            created_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveTeams() {
        $stmt = $this->db->query("
            SELECT t.*, COUNT(tm.employee_id) as member_count
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id
            GROUP BY t.id, t.team_name, t.created_at
            HAVING COUNT(tm.employee_id) = 3
            ORDER BY t.team_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
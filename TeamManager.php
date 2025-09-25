<?php

class TeamManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function autoGroupUngroupedEmployees() {
        $messages = [];

        // --- PHASE 1: RE-BALANCE (Handles returning a temporary partner) ---
        $tempAssignment = $this->getTemporaryAssignment();
        $ungroupedEmployees = $this->getUngroupedEmployees();
        
        if ($tempAssignment && !empty($ungroupedEmployees)) {
            $this->returnAndDissolveTemporaryTeam($tempAssignment);
            $messages[] = "<strong>Team Re-balanced:</strong> A temporary partnership was dissolved to form a stable team.";
            $ungroupedEmployees = $this->getUngroupedEmployees();
        }

        // --- NEW PHASE 2: FILL INCOMPLETE TEAMS ---
        // Prioritize filling teams of 2 before creating new ones.
        if (!empty($ungroupedEmployees)) {
            $incompleteTeams = $this->findIncompleteTeams();
            foreach ($incompleteTeams as $team) {
                if (empty($ungroupedEmployees)) {
                    break; // Stop if we run out of new employees.
                }
                // Take the next available employee and add them to this team.
                $newMember = array_shift($ungroupedEmployees);
                $this->addMemberToTeam($newMember['id'], $team['id']);
                $messages[] = "<strong>Team Completed:</strong> {$newMember['name']} was added to team '{$team['team_name']}' to form a full team of 3.";
            }
        }

        // --- PHASE 3: GROUP REMAINING EMPLOYEES ---
        // Take any remaining ungrouped employees and form new teams.
        while (count($ungroupedEmployees) >= 2) {
            $membersToGroup = [];
            if (count($ungroupedEmployees) >= 3) {
                $membersToGroup = array_splice($ungroupedEmployees, 0, 3);
            } else {
                $membersToGroup = array_splice($ungroupedEmployees, 0, 2);
            }
            
            if (!empty($membersToGroup)) {
                $newTeamName = 'Auto-Team ' . date('Y-m-d H:i');
                $this->createTeamWithMembers($newTeamName, $membersToGroup);
                $memberNames = array_column($membersToGroup, 'name');
                $messages[] = "<strong>New Team Formed:</strong> A new team '{$newTeamName}' was created with " . count($membersToGroup) . " members: " . implode(', ', $memberNames) . ".";
            }
        }
        
        // --- PHASE 4: HANDLE LONE EMPLOYEE ---
        // If, after all the above, one person is still left, borrow a partner.
        if (count($ungroupedEmployees) === 1) {
            $loneEmployee = $ungroupedEmployees[0];
            $partner = $this->findTemporaryPartner();

            if ($partner) {
                $newTeamName = 'Temp-Team ' . date('Y-m-d H:i');
                $this->removeEmployeeFromTeam($partner['id']);
                $this->createTeamWithMembers($newTeamName, [$loneEmployee, $partner]);
                $this->recordTemporaryAssignment($partner['id'], $partner['team_id']);
                
                $messages[] = "<strong>Temporary Team Formed:</strong> To ensure no one works alone, {$partner['name']} from team '{$partner['team_name']}' has been temporarily partnered with {$loneEmployee['name']}.";
            } else {
                $messages[] = "<strong>Action Needed:</strong> A single employee ({$loneEmployee['name']}) is unassigned, but no stable team of 3 could be found to provide a temporary partner.";
            }
        }
        
        return $messages;
    }

    private function getUngroupedEmployees() {
        $sql = "SELECT e.* FROM employees e LEFT JOIN team_members tm ON e.id = tm.employee_id WHERE tm.employee_id IS NULL AND (e.end_date IS NULL OR e.end_date >= CURRENT_DATE)";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createTeamWithMembers($teamName, $members) {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO teams (team_name) VALUES (?)");
            $stmt->execute([$teamName]);
            $team_id = $this->db->lastInsertId();
            $member_stmt = $this->db->prepare("INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)");
            foreach ($members as $employee) {
                $member_stmt->execute([$team_id, $employee['id']]);
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Team creation failed: " . $e->getMessage());
        }
    }

    private function findTemporaryPartner() {
        $sql = "SELECT e.id, e.name, t.id as team_id, t.team_name FROM employees e JOIN team_members tm ON e.id = tm.employee_id JOIN teams t ON tm.team_id = t.id WHERE t.id IN (SELECT team_id FROM team_members GROUP BY team_id HAVING COUNT(id) = 3) ORDER BY t.id DESC, e.start_date DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function removeEmployeeFromTeam($employeeId) {
        $stmt = $this->db->prepare("DELETE FROM team_members WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
    }

    private function recordTemporaryAssignment($employeeId, $originalTeamId) {
        $stmt = $this->db->prepare("INSERT INTO temporary_assignments (employee_id, original_team_id) VALUES (?, ?)");
        $stmt->execute([$employeeId, $originalTeamId]);
    }

    private function getTemporaryAssignment() {
        $stmt = $this->db->query("SELECT * FROM temporary_assignments LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function returnAndDissolveTemporaryTeam($tempAssignment) {
        $borrowedEmployeeId = $tempAssignment['employee_id'];
        $originalTeamId = $tempAssignment['original_team_id'];

        try {
            $this->db->beginTransaction();
            
            // 1. Find the temporary team ID using the borrowed employee
            $stmt = $this->db->prepare("SELECT team_id FROM team_members WHERE employee_id = ?");
            $stmt->execute([$borrowedEmployeeId]);
            $tempTeamId = $stmt->fetchColumn();

            if ($tempTeamId) {
                // 2. Find ALL members of that temporary team (will be 2 people)
                $stmt = $this->db->prepare("SELECT employee_id FROM team_members WHERE team_id = ?");
                $stmt->execute([$tempTeamId]);
                $allTempMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // 3. Remove all members from the team_members table, freeing them up
                $stmt = $this->db->prepare("DELETE FROM team_members WHERE team_id = ?");
                $stmt->execute([$tempTeamId]);

                // 4. Delete the temporary team itself from the teams table
                $stmt = $this->db->prepare("DELETE FROM teams WHERE id = ?");
                $stmt->execute([$tempTeamId]);
            }
            
            // 5. Add the borrowed employee back to their original team
            $stmt = $this->db->prepare("INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)");
            $stmt->execute([$originalTeamId, $borrowedEmployeeId]);
            
            // 6. Clear the temporary assignment record
            $stmt = $this->db->prepare("DELETE FROM temporary_assignments WHERE id = ?");
            $stmt->execute([$tempAssignment['id']]);
            
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to return and dissolve temporary team: " . $e->getMessage());
        }
    }

        /**
     * NEW HELPER FUNCTION
     * Finds teams that have exactly 2 members, making them candidates to be filled.
     * @return array List of incomplete team records.
     */
    private function findIncompleteTeams() {
        $sql = "SELECT t.id, t.team_name 
                FROM teams t 
                JOIN team_members tm ON t.id = tm.team_id 
                GROUP BY t.id, t.team_name 
                HAVING COUNT(tm.id) = 2 
                ORDER BY t.created_at ASC"; // Fill oldest teams first
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * NEW HELPER FUNCTION
     * Adds a single employee to an existing team.
     */
    private function addMemberToTeam($employeeId, $teamId) {
        $stmt = $this->db->prepare("INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)");
        $stmt->execute([$teamId, $employeeId]);
    }
}
<?php
// security-monitor.php - Run as cron job
require_once 'config/database.php';
require_once 'classes/Database.php';

class SecurityMonitor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function checkSuspiciousActivity() {
        // Check for multiple failed logins
        $failedLogins = $this->db->fetchAll(
            "SELECT ip_address, COUNT(*) as attempts 
             FROM rate_limits 
             WHERE action = 'failed_login' 
             AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY ip_address 
             HAVING attempts > 10"
        );
        
        foreach ($failedLogins as $login) {
            $this->logSecurityEvent('multiple_failed_logins', $login['ip_address'], $login['attempts']);
        }
        
        // Check for unusual upload patterns
        $unusualUploads = $this->db->fetchAll(
            "SELECT user_id, COUNT(*) as uploads 
             FROM documents 
             WHERE upload_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY user_id 
             HAVING uploads > 20"
        );
        
        foreach ($upload in $unusualUploads) {
            $this->logSecurityEvent('unusual_upload_pattern', $upload['user_id'], $upload['uploads']);
        }
        
        // Check for report spikes
        $reportSpikes = $this->db->fetchAll(
            "SELECT user_id, COUNT(*) as reports 
             FROM reports 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY user_id 
             HAVING reports > 5"
        );
        
        foreach ($report in $reportSpikes) {
            $this->logSecurityEvent('report_spike', $report['user_id'], $report['reports']);
        }
    }
    
    public function cleanupOldData() {
        // Clean old rate limits
        $this->db->preparedQuery(
            "DELETE FROM rate_limits 
             WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Clean old sessions
        $this->db->preparedQuery(
            "DELETE FROM sessions 
             WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Clean old chat logs (keep 30 days)
        $this->db->preparedQuery(
            "DELETE FROM chat_logs 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
    
    public function checkFileIntegrity() {
        // Check for uploaded files that don't have database records
        $uploadedFiles = glob('uploads/documents/*');
        foreach ($uploadedFiles as $file) {
            $filename = basename($file);
            $exists = $this->db->fetchOne(
                "SELECT id FROM documents WHERE filename = ?",
                [$filename]
            );
            
            if (!$exists) {
                // Orphaned file - log and optionally delete
                $this->logSecurityEvent('orphaned_file', $file, 'No database record found');
            }
        }
    }
    
    private function logSecurityEvent($type, $identifier, $details) {
        $log = date('Y-m-d H:i:s') . " | $type | $identifier | " . json_encode($details) . "\n";
        file_put_contents('logs/security-monitor.log', $log, FILE_APPEND);
        
        // Send email alert for critical events
        if (in_array($type, ['multiple_failed_logins', 'unusual_upload_pattern'])) {
            $this->sendAlertEmail($type, $identifier, $details);
        }
    }
    
    private function sendAlertEmail($type, $identifier, $details) {
        $subject = "WCH Security Alert: $type";
        $message = "Security event detected:\n\n";
        $message .= "Type: $type\n";
        $message .= "Identifier: $identifier\n";
        $message .= "Details: " . json_encode($details) . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        // Send to admin
        mail('admin@wezocampushub.com', $subject, $message);
    }
}

// Run monitor
$monitor = new SecurityMonitor();
$monitor->checkSuspiciousActivity();
$monitor->cleanupOldData();
$monitor->checkFileIntegrity();
?>
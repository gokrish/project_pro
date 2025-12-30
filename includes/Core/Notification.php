// includes/Core/Notification.php
class Notification {
    public static function submissionCreated($submission) {
        // Email to manager
        self::send(
            $manager['email'],
            "New Submission Needs Approval",
            "submission_pending_approval_template",
            $submission
        );
    }
    
    public static function submissionApproved($submission) {
        // Email to recruiter
        self::send(
            $recruiter['email'],
            "Submission Approved",
            "submission_approved_template",
            $submission
        );
    }
    
    public static function submissionSentToClient($submission) {
        // Email to client
        self::send(
            $client['email'],
            "Candidate Submission: {$submission['job_title']}",
            "client_submission_template",
            $submission
        );
    }
}
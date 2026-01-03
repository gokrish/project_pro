<?php
/**
 * Email Template: Job Approval Request
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">
        
        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            
            <h2 style="color: #667eea; margin-top: 0;">New Job Awaiting Your Approval</h2>
            
            <p>Hi <?= htmlspecialchars($manager_name) ?>,</p>
            
            <p>A new job has been submitted for your approval:</p>
            
            <div style="background: #f5f7fa; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;">
                <p style="margin: 5px 0;"><strong>Job Title:</strong> <?= htmlspecialchars($job_title) ?></p>
                <p style="margin: 5px 0;"><strong>Job Code:</strong> <?= htmlspecialchars($job_code) ?></p>
                <p style="margin: 5px 0;"><strong>Client:</strong> <?= htmlspecialchars($client_name) ?></p>
                <p style="margin: 5px 0;"><strong>Location:</strong> <?= htmlspecialchars($location) ?></p>
                <p style="margin: 5px 0;"><strong>Submitted By:</strong> <?= htmlspecialchars($submitted_by) ?></p>
                <p style="margin: 5px 0;"><strong>Submitted At:</strong> <?= htmlspecialchars($submitted_at) ?></p>
            </div>
            
            <p style="margin-top: 30px;">
                <a href="<?= htmlspecialchars($approval_url) ?>" 
                   style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    Review Job
                </a>
            </p>
            
            <p style="color: #666; font-size: 14px; margin-top: 30px;">
                This is an automated notification from ProConsultancy.
            </p>
            
        </div>
        
    </div>
</body>
</html>
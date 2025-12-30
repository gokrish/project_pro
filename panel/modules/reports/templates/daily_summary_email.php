<?php
/**
 * Daily Summary Email Template
 * Used by the Mailer class to send a daily recruitment summary.
 * 
 * Variables available:
 * $summary_date (string)
 * $total_candidates_added (int)
 * $recruiter_breakdown (array)
 * $kpis (array)
 * $followup_table_html (string)
 * $view_report_url (string)
 */

$system_name = Core\Settings::getInstance()->get('system_name', 'Recruitment System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Recruitment Summary - <?php echo $summary_date; ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { width: 90%; max-width: 600px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .header { text-align: center; padding-bottom: 10px; border-bottom: 2px solid #007bff; }
        .header h1 { color: #007bff; font-size: 24px; }
        .content { margin-top: 20px; }
        .kpi-card { background: #e9f7ff; border-left: 4px solid #007bff; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
        .kpi-card strong { color: #007bff; }
        .table-summary { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-summary th, .table-summary td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table-summary th { background-color: #f2f2f2; }
        .cta-button { display: block; width: 80%; margin: 20px auto; padding: 10px 0; background-color: #28a745; color: #ffffff !important; text-align: center; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Daily Recruitment Summary</h1>
            <p>Report for: <strong><?php echo $summary_date; ?></strong></p>
        </div>
        <div class="content">
            <p>Dear Manager,</p>
            <p>Here is the automated daily summary of recruitment activity for <strong><?php echo $system_name; ?></strong>.</p>

            <h2>Key Performance Indicators</h2>
            <?php foreach ($kpis as $label => $value): ?>
                <div class="kpi-card">
                    <strong><?php echo htmlspecialchars($label); ?>:</strong> <?php echo htmlspecialchars($value); ?>
                </div>
            <?php endforeach; ?>

            <h2>Recruiter Activity Breakdown</h2>
            <table class="table-summary">
                <thead>
                    <tr>
                        <th>Recruiter</th>
                        <th>Candidates Added</th>
                        <th>Calls Logged</th>
                        <th>Follow-ups Done</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recruiter_breakdown)): ?>
                        <?php foreach ($recruiter_breakdown as $recruiter): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($recruiter['name']); ?></td>
                                <td><?php echo htmlspecialchars($recruiter['candidates_added']); ?></td>
                                <td><?php echo htmlspecialchars($recruiter['calls_logged']); ?></td>
                                <td><?php echo htmlspecialchars($recruiter['followups_done']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">No significant activity recorded today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($followup_table_html)): ?>
                <h2>Urgent Follow-ups Pending</h2>
                <?php echo $followup_table_html; ?>
            <?php endif; ?>

            <a href="<?php echo $view_report_url; ?>" class="cta-button">View Full Interactive Report</a>
        </div>
        <div class="footer">
            <p>This email was sent automatically by the <?php echo $system_name; ?>. Please do not reply.</p>
        </div>
    </div>
</body>
</html>

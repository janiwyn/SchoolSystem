<?php
/**
 * SYSTEM / SERVER STATUS CONTROL
 * 
 * DEVELOPER TOGGLE SWITCH:
 * Change $ENABLE_SERVER_ERROR to true to activate the error page and block access.
 * Change $ENABLE_SERVER_ERROR to false to turn off the error page and resume normal access.
 */

$ENABLE_SERVER_ERROR = true; // <-- SET TO true TO ACTIVATE ERROR PAGE, false FOR NORMAL SYSTEM

if ($ENABLE_SERVER_ERROR) {
    http_response_code(500);
    if (ob_get_level()) {
        ob_end_clean();
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - Unable to Communicate with Server</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background-color: #f1f1f1;
            color: #444;
            margin: 50px auto;
            max-width: 600px;
            padding: 25px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border-radius: 4px;
        }
        h1 {
            font-size: 18px;
            color: #d9534f;
            border-bottom: 1px solid #dadada;
            padding-bottom: 12px;
            margin-top: 0;
            font-weight: 600;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #555;
        }
        .details-link {
            color: #0066cc;
            text-decoration: underline;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
            margin-top: 6px;
        }
        .details-link:hover {
            color: #004499;
        }
        .details-content {
            display: none;
            margin-top: 12px;
            padding-left: 14px;
            border-left: 2px solid #e0e0e0;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }
        .footer-status {
            margin-top: 25px;
            font-size: 12px;
            color: #888;
            font-family: monospace;
            border-top: 1px solid #eee;
            padding-top: 12px;
        }
    </style>
</head>
<body>
    <h1>Server Error</h1>
    <p><strong>Can't communicate with server.</strong></p>
    <p>The request could not be processed due to unresolved server connectivity and hosting status issues. Access to the application is currently restricted.</p>

    <a href="javascript:void(0)" class="details-link" id="toggleBtn" onclick="toggleDetails()">See details</a>

    <div class="details-content" id="detailsBox">
        <p style="margin: 0 0 8px 0; color: #cc0000; font-weight: 600;">Pending Server Payments Notice:</p>
        <p style="margin: 0 0 6px 0;">Access is restricted due to outstanding server hosting balance and unpaid maintenance fees:</p>
        <ul style="margin: 6px 0 10px 0; padding-left: 20px; line-height: 1.8;">
            <li>June Server Fee: 10 dollars</li>
            <li>July Server Fee: 10 dollars</li>
            <li>August Server Fee: 10 dollars</li>
            <li>September Server Fee: 10 dollars</li>
            <li>Taxes &amp; Service Fees: 8 dollars</li>
        </ul>
        <p style="margin: 8px 0 0 0; font-weight: 600; color: #333;">Total Pending Balance: 48 dollars</p>
    </div>

    <div class="footer-status">
        [HTTP STATUS 500] Internal Server Error: Hosting fee status verification failed. Please contact your system administrator to clear pending fees.
    </div>

    <script>
        function toggleDetails() {
            var box = document.getElementById('detailsBox');
            var btn = document.getElementById('toggleBtn');
            if (box.style.display === 'block') {
                box.style.display = 'none';
                btn.textContent = 'See details';
            } else {
                box.style.display = 'block';
                btn.textContent = 'Hide details';
            }
        }
    </script>
</body>
</html>
<?php
exit();
}

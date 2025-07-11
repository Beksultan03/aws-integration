<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMA Keys Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        .container {
            margin: 0 auto;
            padding: 20px;
            max-width: 600px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
        }
        .content {
            margin-top: 20px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #aaa;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>RMA Keys Alert</h2>
    </div>
    <div class="content">
        <p>Dear Team,</p>
        <p>We have {{$windowsKeysCount}} RMA keys that need to be sent back to the manufacturer. Please take the necessary actions to handle these keys.</p>
        <p>Best regards,</p>
        <p>Kit Builder</p>
    </div>
    <div class="footer">
        <p>This email was generated automatically. Please do not reply.</p>
    </div>
</div>
</body>
</html>

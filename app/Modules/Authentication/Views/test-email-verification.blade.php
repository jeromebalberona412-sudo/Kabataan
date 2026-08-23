<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Verification - SK Kabataan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .test-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 4px;
        }

        .info-box p {
            color: #1565c0;
            margin: 8px 0;
            font-size: 14px;
        }

        .test-form {
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            margin-right: 12px;
            margin-bottom: 12px;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .routes-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
        }

        .routes-list h3 {
            color: #333;
            margin-bottom: 16px;
            font-size: 18px;
        }

        .routes-list ul {
            list-style: none;
        }

        .routes-list li {
            padding: 8px 0;
            color: #555;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }

        .routes-list li strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>?? Email Verification Test Page</h1>
        
        <div class="info-box">
            <p><strong>Note:</strong> This is a prototype. No actual emails will be sent.</p>
            <p><strong>Demo behavior:</strong> Verification will complete automatically after 15 seconds.</p>
        </div>

        <div class="test-form">
            <form action="{{ route('verification.notice') }}" method="GET">
                <div class="form-group">
                    <label for="email">Enter Email Address (for display only)</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="user@example.com"
                        value="youth@skportal.com"
                    >
                </div>
                <button type="submit" class="btn">Start Email Verification Flow</button>
            </form>
        </div>

        <div style="margin-bottom: 24px;">
            <a href="{{ route('verification.verify', ['token' => 'demo-token-123']) }}" class="btn btn-secondary">
                Test Success Page (Email Link)
            </a>
        </div>

        <div class="routes-list">
            <h3>Available Routes:</h3>
            <ul>
                <li><strong>GET</strong> /email/verify - Verification waiting page</li>
                <li><strong>POST</strong> /email/verification-notification - Send verification</li>
                <li><strong>POST</strong> /email/resend - Resend verification</li>
                <li><strong>GET</strong> /email/verify/{token} - Verify email (success page)</li>
                <li><strong>GET</strong> /email/check-status - Check verification status</li>
            </ul>
        </div>

        <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #e0e0e0;">
            <a href="{{ route('sign-in') }}" class="btn btn-secondary">Back to Sign In</a>
        </div>
    </div>

    <script>
        // Store email in session storage for the verification page
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            if (email) {
                // Add email as query parameter
                const form = e.target;
                const url = new URL(form.action);
                url.searchParams.set('email', email);
                form.action = url.toString();
            }
        });
    </script>
</body>
</html>

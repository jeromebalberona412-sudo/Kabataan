<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified - SK Kabataan Portal</title>
    @vite(['app/Modules/Authentication/assets/css/sign-in.css'])
    <style>
        .success-container {
            background: white;
            border-radius: 16px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            animation: slideUp 0.5s ease-out;
            position: relative;
            z-index: 10;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon {
            margin-bottom: 24px;
            animation: successPop 0.6s ease;
        }

        @keyframes successPop {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .checkmark {
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }

        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke: #4CAF50;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke: #4CAF50;
            stroke-width: 3;
            fill: none;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        h1 {
            color: #4CAF50;
            font-size: 28px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .redirect-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #e0e0e0;
            border-top-color: #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .redirect-info span {
            color: #666;
            font-size: 14px;
        }

        .manual-link {
            display: inline-block;
            padding: 14px 32px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .manual-link:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 600px) {
            .success-container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 24px;
            }

            p {
                font-size: 15px;
            }
        }
    </style>
</head>
<body class="youth-signin-page">
    <!-- Animated Background (same as signin page) -->
    <div class="youth-bg-wrapper">
        <div class="youth-bg-image"></div>
        <div class="youth-gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <main style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative; z-index: 1;">
        <div class="success-container">
        <div class="success-icon">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        
        <h1>Verification Successful!</h1>
        
        <p>
            Your email has been verified successfully. You can now access all features of the SK Kabataan Portal.
        </p>

    
    </div>
    </main>

</body>
</html>

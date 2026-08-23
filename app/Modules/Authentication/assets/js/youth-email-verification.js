// Email Verification Page Logic
document.addEventListener('DOMContentLoaded', function() {
    // Timer configuration (10 minutes = 600 seconds)
    let timeRemaining = 600;
    let timerInterval;
    let checkInterval;
    
    const countdownElement = document.getElementById('countdown-timer');
    const resendBtn = document.getElementById('resend-btn');
    const successModal = document.getElementById('success-modal');
    
    // Format time as MM:SS
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
    
    // Update countdown timer
    function updateTimer() {
        if (timeRemaining > 0) {
            timeRemaining--;
            countdownElement.textContent = formatTime(timeRemaining);
            
            // Change color when time is running out
            if (timeRemaining <= 60) {
                countdownElement.style.color = '#d32f2f';
            } else if (timeRemaining <= 180) {
                countdownElement.style.color = '#f57c00';
            }
        } else {
            clearInterval(timerInterval);
            countdownElement.textContent = 'Expired';
            countdownElement.style.color = '#d32f2f';
            showExpiredMessage();
        }
    }
    
    // Start the countdown timer
    function startTimer() {
        timerInterval = setInterval(updateTimer, 1000);
    }
    
    // Show expired message
    function showExpiredMessage() {
        const waitingIndicator = document.querySelector('.waiting-indicator');
        waitingIndicator.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="#d32f2f"/>
            </svg>
            <span style="color: #d32f2f;">Verification link expired</span>
        `;
    }
    
    // Simulate checking verification status (prototype)
    function checkVerificationStatus() {
        // In a real implementation, this would make an AJAX call to check if email is verified
        // For prototype, we'll simulate verification after 10 seconds
        
        const verificationTime = 10000; // 10 seconds for demo
        
        setTimeout(() => {
            // Call verify endpoint to create session (without redirecting)
            fetch('/email/verify/prototype-token-' + Date.now(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(response => response.json())
              .then(data => {
                  // Show success modal after session is created
                  showSuccessModal();
              })
              .catch(error => {
                  // If fetch fails, still show modal (for prototype)
                  showSuccessModal();
              });
        }, verificationTime);
    }
    
    // Show success modal
    function showSuccessModal() {
        clearInterval(timerInterval);
        clearInterval(checkInterval);
        
        // Show success modal
        successModal.classList.add('show');
        
        // After 5 seconds, redirect to dashboard
        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 5000);
    }
    
    // Handle resend button click
    resendBtn.addEventListener('click', async function() {
        resendBtn.disabled = true;
        resendBtn.textContent = 'Sending...';

        const dataEl = document.getElementById('email-verify-data');
        const resendUrl = dataEl?.dataset.resendUrl || '/email/resend';
        const email = dataEl?.dataset.email || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        try {
            let turnstileToken = '';
            if (window.kabataanTurnstileChallenge) {
                turnstileToken = await window.kabataanTurnstileChallenge();
            } else if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                turnstileToken = await window.KabataanTurnstileGate.challenge();
            }

            const response = await fetch(resendUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email,
                    'cf-turnstile-response': turnstileToken,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showNotification(data.message || 'Unable to resend verification email. Please try again.');
                return;
            }

            timeRemaining = 600;
            countdownElement.textContent = formatTime(timeRemaining);
            countdownElement.style.color = '#d32f2f';
            showNotification(data.message || 'Verification email resent successfully!');
            clearInterval(timerInterval);
            startTimer();
        } catch (error) {
            if (error && error.message && error.message !== 'Verification cancelled.') {
                showNotification('Unable to resend verification email. Please try again.');
            }
        } finally {
            resendBtn.disabled = false;
            resendBtn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4C7.58 4 4.01 7.58 4.01 12C4.01 16.42 7.58 20 12 20C15.73 20 18.84 17.45 19.73 14H17.65C16.83 16.33 14.61 18 12 18C8.69 18 6 15.31 6 12C6 8.69 8.69 6 12 6C13.66 6 15.14 6.69 16.22 7.78L13 11H20V4L17.65 6.35Z" fill="currentColor"/>
                </svg>
                Resend Verification Email
            `;
        }
    });
    
    // Show notification
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 2000;
            animation: slideInRight 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Initialize
    startTimer();
    checkVerificationStatus();
    
    // Simulate periodic checking (every 3 seconds)
    checkInterval = setInterval(() => {
        // In real implementation, this would check server for verification status
        console.log('Checking verification status...');
    }, 3000);
});

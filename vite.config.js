import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Core
                'resources/css/app.css',
                'resources/js/app.js',

                // Authentication
                'app/Modules/Authentication/assets/css/sign-in.css',
                'app/Modules/Authentication/assets/css/turnstile-gate.css',
                'app/Modules/Authentication/assets/css/auth-legal.css',
                'app/Modules/Authentication/assets/css/youth-fp-verify-email.css',
                'app/Modules/Authentication/assets/css/youth-register.css',
                'app/Modules/Authentication/assets/css/youth-email-verification.css',
                'app/Modules/Authentication/assets/js/sign-in.js',
                'app/Modules/Authentication/assets/js/auth-legal.js',
                'app/Modules/Authentication/assets/js/youth-fp-verify-email.js',
                'app/Modules/Authentication/assets/js/turnstile-gate.js',
                'app/Modules/Authentication/assets/js/youth-register.js',
                'app/Modules/Authentication/assets/js/youth-email-verification.js',

                // Layout (shared header & footer)
                'app/Modules/Layout/assets/css/kabataan-header.css',
                'app/Modules/Layout/assets/css/kabataan-header-messages.css',
                'app/Modules/Communications/assets/css/chat-modal.css',
                'app/Modules/Communications/assets/js/chat-modal.js',
                'app/Modules/Layout/assets/css/kabataan-messages.css',
                'app/Modules/Layout/assets/css/kabataan-call.css',
                'app/Modules/Layout/assets/css/programs-drawer.css',
                'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
                'app/Modules/Layout/assets/css/kabataan-responsive.css',
                'app/Modules/Layout/assets/css/kabataan-logout.css',
                'app/Modules/Layout/assets/js/kabataan-header.js',
                'app/Modules/Layout/assets/js/kabataan-logout.js',
                'app/Modules/Layout/assets/js/kabataan-session-timeout.js',
                'app/Modules/Homepage/assets/css/kabataan-footer.css',

                // Tutorial Guide
                'app/Modules/Tutorial_Guide/assets/css/tutorial-guide.css',
                'app/Modules/Tutorial_Guide/assets/js/tutorial-guide.js',

                // Dashboard
                'app/Modules/Dashboard/assets/css/dashboard.css',
                'app/Modules/Dashboard/assets/css/feed-videos.css',
                'app/Modules/Dashboard/assets/css/community-feed-comment-preview.css',
                'app/Modules/Dashboard/assets/css/barangay-profile.css',
                'app/Modules/Dashboard/assets/css/notif.css',
                'app/Modules/Dashboard/assets/js/dashboard.js',
                'app/Modules/Dashboard/assets/js/feed-videos.js',
                'app/Modules/Dashboard/assets/js/community-feed-comment-preview.js',
                'app/Modules/Dashboard/assets/js/prohibited-words.js',
                'app/Modules/Dashboard/assets/js/comment-spam-guard.js',
                'app/Modules/Dashboard/assets/js/barangay-profile.js',
                'app/Modules/Dashboard/assets/js/notif.js',

                // Notifications
                'app/Modules/Notifications/assets/css/notifications.css',
                'app/Modules/Notifications/assets/js/notifications.js',

                // Communications
                'app/Modules/Communications/assets/css/communication.css',
                'app/Modules/Communications/assets/js/communication.js',
                'app/Modules/Communications/assets/js/chat.js',
                'app/Modules/Communications/assets/js/realtime.js',
                'app/Modules/Communications/assets/js/webrtc.js',

                // Programs
                'app/Modules/Programs/assets/css/scholarship_landing.css',
                'app/Modules/Programs/assets/css/scholarship_application_preview.css',
                'app/Modules/Programs/assets/css/scholarship_application.css',
                'app/Modules/Programs/assets/css/scholarship-quick-guidelines.css',
                'app/Modules/Programs/assets/css/scholarship-data-privacy.css',
                'app/Modules/Programs/assets/css/sports_landing.css',
                'app/Modules/Programs/assets/css/sports-applications-history.css',
                'app/Modules/Programs/assets/css/sports-registration.css',
                'app/Modules/Programs/assets/css/programs-pre-survey.css',
                'app/Modules/Programs/assets/js/scholarship-quick-guidelines.js',
                'app/Modules/Programs/assets/js/scholarship-data-privacy.js',
                'app/Modules/Programs/assets/js/scholarship-system-fields.js',
                'app/Modules/Programs/assets/js/scholarship_application_preview.js',
                'app/Modules/Programs/assets/js/scholarship_apply_wizard.js',
                'app/Modules/Programs/assets/js/scholarship_landing.js',
                'app/Modules/Programs/assets/js/sports_landing.js',
                'app/Modules/Programs/assets/js/sports-applications-history.js',
                'app/Modules/Programs/assets/js/sports_apply_wizard.js',
                'app/Modules/Programs/assets/js/programs.js',
                'app/Modules/Programs/assets/js/kabataan-programs.js',
                'app/Modules/Programs/assets/js/program_survey_landing.js',
                'app/Modules/Programs/assets/js/program_survey_form.js',
                'app/Modules/Programs/assets/js/program_evaluation_form.js',
                'app/Modules/Programs/assets/js/program-evaluation-prompt.js',
                'app/Modules/Programs/assets/js/programs-pre-survey.js',

                // Profile
                'app/Modules/Profile/assets/css/profile.css',
                'app/Modules/Profile/assets/css/profile-personal-info.css',
                'app/Modules/Profile/assets/css/profile-personal-info-responsive.css',
                'app/Modules/Profile/assets/css/change-email.css',
                'app/Modules/Profile/assets/css/change-password.css',
                'app/Modules/Profile/assets/js/profile.js',
                'app/Modules/Profile/assets/js/profile-document-lightbox.js',
                'app/Modules/Profile/assets/js/profile-participation.js',
                'app/Modules/Profile/assets/js/change-email.js',
                'app/Modules/Profile/assets/js/change-email-verify.js',
                'app/Modules/Profile/assets/js/change-password.js',
                'app/Modules/Profile/assets/js/change-password-verify.js',
                'app/Modules/Profile/assets/js/set-password.js',

                // Homepage
                'app/Modules/Homepage/assets/css/homepage-bootstrap.css',
                'app/Modules/Homepage/assets/css/homepage.css',
                'app/Modules/Homepage/assets/css/homepage-landing.css',
                'app/Modules/Homepage/assets/css/about.css',
                'app/Modules/Homepage/assets/css/pages.css',
                'app/Modules/Homepage/assets/css/faqs.css',
                'app/Modules/Homepage/assets/css/homepage-interactions.css',
                'app/Modules/Homepage/assets/css/homepage-responsive.css',
                'app/Modules/Homepage/assets/js/homepage.js',
                'app/Modules/Homepage/assets/js/faqs.js',

                // Program Accomplishments
                'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishments.css',
                'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishment-show.css',
                'app/Modules/Program_Accomplishments/assets/css/program-card-expand.css',
                'app/Modules/Program_Accomplishments/assets/js/barangay-accomplishments.js',
                'app/Modules/Program_Accomplishments/assets/js/program-card-expand.js',

                // Barangay ABYIP
                'app/Modules/Baranggay_ABYIP/assets/css/baranggay_abyip.css',
                'app/Modules/Baranggay_ABYIP/assets/js/baranggay_abyip.js',

                // KK Profiling
                'app/Modules/KKProfiling/assets/css/kkprofiling.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-form-body.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-signature.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-responsive.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-account.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-signup.css',
                'app/Modules/KKProfiling/assets/css/kk-profiling-update.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-wizard.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-wizard-docs.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-optional-email.css',
                'app/Modules/KKProfiling/assets/js/kkprofiling.js',
                'app/Modules/KKProfiling/assets/js/kkprofiling-signup.js',
                'app/Modules/KKProfiling/assets/js/kk-profiling-update.js',
                'app/Modules/KKProfiling/assets/js/kkprofiling-wizard.js',
                'app/Modules/KKProfiling/assets/js/kkprofiling-id-camera.js',
            ],
            refresh: true,
        }),
    ],

    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name || '';
                    const ext = name.includes('.') ? name.split('.').pop() : 'asset';
                    if (/png|jpe?g|svg|gif|tiff|bmp|ico|webp/i.test(ext)) {
                        return 'assets/images/[name]-[hash][extname]';
                    }
                    return `assets/${ext}/[name]-[hash][extname]`;
                },
                manualChunks(id) {
                    if (id.includes('node_modules/@vladmandic/face-api') || id.includes('node_modules/@tensorflow')) {
                        return 'vendor-face-api';
                    }
                },
            },
        },
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

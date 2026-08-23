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
                'app/Modules/Authentication/assets/js/sign-in.js',
                'app/Modules/Authentication/assets/js/auth-legal.js',
                'app/Modules/Authentication/assets/js/youth-fp-verify-email.js',
                'app/Modules/Authentication/assets/js/turnstile-gate.js',
                'app/Modules/Authentication/assets/css/youth-register.css',
                'app/Modules/Authentication/assets/js/youth-register.js',
                'app/Modules/Authentication/assets/css/youth-email-verification.css',
                'app/Modules/Authentication/assets/js/youth-email-verification.js',

                // Layout (shared header & footer)
                'app/Modules/Layout/assets/css/kabataan-header.css',
                'app/Modules/Layout/assets/css/programs-drawer.css',
                'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
                'app/Modules/Layout/assets/css/kabataan-responsive.css',
                'app/Modules/Layout/assets/css/kabataan-logout.css',
                'app/Modules/Layout/assets/js/kabataan-header.js',
                'app/Modules/Layout/assets/js/kabataan-logout.js',
                'app/Modules/Homepage/assets/css/kabataan-footer.css',

                // Dashboard
                'app/Modules/Dashboard/assets/css/dashboard.css',
                'app/Modules/Dashboard/assets/css/community-feed-comment-preview.css',
                'app/Modules/Dashboard/assets/js/dashboard.js',
                'app/Modules/Dashboard/assets/js/community-feed-comment-preview.js',
                'app/Modules/Dashboard/assets/css/barangay-profile.css',
                'app/Modules/Dashboard/assets/js/barangay-profile.js',
                'app/Modules/Dashboard/assets/css/chatbot.css',
                'app/Modules/Dashboard/assets/js/chatbot.js',
                'app/Modules/Dashboard/assets/css/notif.css',
                'app/Modules/Dashboard/assets/js/notif.js',
                'app/Modules/Notifications/assets/css/notifications.css',
                'app/Modules/Notifications/assets/js/notifications.js',

                // Programs
                'app/Modules/Programs/assets/css/scholarship_landing.css',
                'app/Modules/Programs/assets/css/scholarship_application_preview.css',
                'app/Modules/Programs/assets/css/scholarship_application.css',
                'app/Modules/Programs/assets/css/scholarship-quick-guidelines.css',
                'app/Modules/Programs/assets/js/scholarship-quick-guidelines.js',
                'app/Modules/Programs/assets/css/scholarship-data-privacy.css',
                'app/Modules/Programs/assets/js/scholarship-data-privacy.js',
                'app/Modules/Programs/assets/js/scholarship-system-fields.js',
                'app/Modules/Programs/assets/js/scholarship_application_preview.js',
                'app/Modules/Programs/assets/js/scholarship_apply_wizard.js',
                'app/Modules/Programs/assets/js/scholarship_landing.js',
                'app/Modules/Programs/assets/css/sports_landing.css',
                'app/Modules/Programs/assets/js/sports_landing.js',
                'app/Modules/Programs/assets/css/sports-applications-history.css',
                'app/Modules/Programs/assets/js/sports-applications-history.js',
                'app/Modules/Programs/assets/css/scholarship_application.css',
                'app/Modules/Programs/assets/css/sports-registration.css',
                'app/Modules/Programs/assets/js/sports_apply_wizard.js',
                'app/Modules/Programs/assets/js/programs.js',
                'app/Modules/Programs/assets/css/scholarship-quick-guidelines.css',
        'app/Modules/Programs/assets/js/scholarship-quick-guidelines.js',
        'app/Modules/Programs/assets/js/kabataan-programs.js',
                'app/Modules/Programs/assets/js/program_survey_landing.js',
                'app/Modules/Programs/assets/js/program_survey_form.js',
                'app/Modules/Programs/assets/js/program_evaluation_form.js',
                'app/Modules/Programs/assets/js/program-evaluation-prompt.js',

                // Profile
                'app/Modules/Profile/assets/css/profile.css',
                'app/Modules/Profile/assets/js/profile.js',
                'app/Modules/Profile/assets/js/profile-participation.js',
                'app/Modules/Profile/assets/css/change-email.css',
                'app/Modules/Profile/assets/js/change-email.js',
                'app/Modules/Profile/assets/js/change-email-verify.js',
                'app/Modules/Profile/assets/css/change-password.css',
                'app/Modules/Profile/assets/js/change-password.js',
                'app/Modules/Profile/assets/js/change-password-verify.js',
                'app/Modules/Profile/assets/js/set-password.js',

                // Homepage
                'app/Modules/Homepage/assets/css/homepage-bootstrap.css',
                'app/Modules/Homepage/assets/css/homepage.css',
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
                'app/Modules/KKProfiling/assets/js/kkprofiling.js',
                'app/Modules/KKProfiling/assets/css/kkprofiling-signup.css',
                'app/Modules/KKProfiling/assets/js/kkprofiling-signup.js',
                'app/Modules/KKProfiling/assets/css/kk-profiling-update.css',
                'app/Modules/KKProfiling/assets/js/kk-profiling-update.js',
                'app/Modules/KKProfiling/assets/css/kkprofiling-wizard.css',
                'app/Modules/KKProfiling/assets/css/kkprofiling-optional-email.css',
                'app/Modules/KKProfiling/assets/js/kkprofiling-wizard.js',
                'app/Modules/KKProfiling/assets/js/kkprofiling-optional-email.js',
            ],
            refresh: true,
        }),
    ],

    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    let extType = assetInfo.name.split('.').at(1);
                    if (/png|jpe?g|svg|gif|tiff|bmp|ico|webp/i.test(extType)) {
                        extType = 'images';
                    }
                    return `assets/${extType}/[name]-[hash][extname]`;
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

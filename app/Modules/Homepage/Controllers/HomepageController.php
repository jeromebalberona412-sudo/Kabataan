<?php

namespace App\Modules\Homepage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class HomepageController extends Controller
{
    public function index()
    {
        $isAuthenticated = Auth::check();
        $homepageProgramsAnchor = route('homepage').'#transparency';
        $dashboardUrl = route('dashboard');
        $registerUrl = route('register');
        $signInUrl = route('sign-in');

        $kkProfilingUrl = $isAuthenticated
            ? (Route::has('kkprofiling.update.show') ? route('kkprofiling.update.show') : $dashboardUrl)
            : $registerUrl;

        return view('homepage::homepage', [
            'municipality' => [
                'name' => 'Santa Cruz, Laguna',
                'portal' => 'SK OnePortal Kabataan',
            ],
            'isAuthenticated' => $isAuthenticated,
            'primaryCta' => $isAuthenticated
                ? ['label' => 'Go to Dashboard', 'href' => $dashboardUrl]
                : ['label' => 'Get Started', 'href' => $registerUrl],
            'secondaryCta' => $isAuthenticated
                ? ['label' => 'View Public Records', 'href' => $homepageProgramsAnchor]
                : ['label' => 'Explore Programs', 'href' => $homepageProgramsAnchor],
            'finalPrimaryCta' => $isAuthenticated
                ? ['label' => 'Go to Dashboard', 'href' => $dashboardUrl]
                : ['label' => 'Create an Account', 'href' => $registerUrl],
            'kkProfilingUrl' => $kkProfilingUrl,
            'programsBrowseUrl' => $homepageProgramsAnchor,
            'programsUrl' => $isAuthenticated ? $dashboardUrl : $signInUrl,
            'faqs' => $this->getFaqs(),
        ]);
    }

    public function programs()
    {
        return view('homepage::programs');
    }

    private function getFaqs(): array
    {
        return cache()->remember('kabataan_faqs_v9', 3600, function () {
            return [
                [
                    'id' => 1,
                    'category' => 'general',
                    'question' => 'What is SK OnePortal?',
                    'answer' => 'SK OnePortal is the official digital platform of the Municipality of Santa Cruz that connects Kabataan, Sangguniang Kabataan (SK) Officials, SK Federation, and the Local Youth Development Office (LYDO). It provides online access to youth programs, scholarship applications, events, announcements, surveys, profiling, and other SK-related services in one centralized system.',
                ],
                [
                    'id' => 2,
                    'category' => 'account',
                    'question' => 'How do I create an account?',
                    'answer' => 'Click the Sign Up button on the homepage and complete the registration form with accurate personal information. Verify your email address if required, then submit the form. Once your registration is approved or verified, you can sign in and access the system.',
                ],
                [
                    'id' => 3,
                    'category' => 'account',
                    'question' => 'How do I sign in to my account?',
                    'answer' => 'Click the Sign In button, enter your registered email address and password, then click Login. If you forget your password, use the Forgot Password option to receive a reset link by email.',
                ],
                [
                    'id' => 4,
                    'category' => 'account',
                    'question' => 'What is KK Profiling?',
                    'answer' => 'KK Profiling is the official youth profile form for Katipunan ng Kabataan members aged 15–30 in Santa Cruz. After you create a Kabataan account, complete KK Profiling so your barangay SK has an accurate youth record. Approved profile details can also be used when you apply for programs such as scholarships.',
                ],
                [
                    'id' => 5,
                    'category' => 'services',
                    'question' => 'What services can I access through SK OnePortal?',
                    'answer' => 'Registered users can complete KK Profiling, discover available SK programs and activities, register for supported programs, submit supported requirements online when a program allows it, receive announcements, answer surveys, and access other youth-related services offered through SK OnePortal. Online registration and document submission depend on the specific program. Anyone can also view public Barangay ABYIP documents and program accomplishments without signing in.',
                ],
                [
                    'id' => 6,
                    'category' => 'general',
                    'question' => 'Who can use SK OnePortal?',
                    'answer' => 'SK OnePortal is intended for Kabataan residing in the Municipality of Santa Cruz, SK Officials, SK Federation members, the Local Youth Development Office (LYDO), and other authorized municipal personnel, depending on their assigned roles and permissions.',
                ],
            ];
        });
    }
}

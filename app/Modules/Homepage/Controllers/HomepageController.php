<?php

namespace App\Modules\Homepage\Controllers;

use App\Http\Controllers\Controller;

class HomepageController extends Controller
{
    public function index()
    {
        $municipality = [
            'name' => 'Santa Cruz, Laguna',
            'portal' => 'SK OnePortal Kabataan',
            'description' => 'View-only youth community updates from barangay SK officials.',
        ];

        $valueProps = [
            [
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.6 8.7-8 9-4.4-.3-8-4-8-9V7l8-4z"/><path d="M9 12l2 2 4-5"/></svg>',
                'title' => 'Discover programs',
                'description' => 'Browse education, health, sports, and livelihood activities from your barangay SK.',
            ],
            [
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 12h10M7 8h10M7 16h6"/><path d="M4 6h16v12H4z"/></svg>',
                'title' => 'Join and participate',
                'description' => 'Apply to open programs, follow announcements, and stay updated on schedules.',
            ],
            [
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M15 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>',
                'title' => 'See program details',
                'description' => 'Read public ABYIP and accomplishment records published by barangay SK offices.',
            ],
            [
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l3 6 7 .8-5.2 4.6 1.6 6.8L12 16.9 5.6 20.2l1.6-6.8L2 8.8 9 8l3-6z"/></svg>',
                'title' => 'Keep your record',
                'description' => 'Sign in to track applications, participation, and your Kabataan profile.',
            ],
        ];

        $publicLinks = [
            [
                'title' => 'Barangay ABYIP',
                'text' => 'Read Annual Barangay Youth Investment Program documents from SK offices.',
                'href' => route('baranggay_abyip.index'),
                'label' => 'View ABYIP',
            ],
            [
                'title' => 'Program Accomplishments',
                'text' => 'See youth programs reported across Santa Cruz barangays.',
                'href' => route('program_accomplishments.barangays'),
                'label' => 'View accomplishments',
            ],
            [
                'title' => 'Help & FAQs',
                'text' => 'Answers about registration, sign-in, and who can use the portal.',
                'href' => route('homepage') . '#faq',
                'label' => 'Read FAQs',
            ],
        ];

        return view('homepage::homepage', [
            'municipality' => $municipality,
            'valueProps'   => $valueProps,
            'publicLinks'  => $publicLinks,
            'faqs'         => $this->getFaqs(),
        ]);
    }

    public function programs()
    {
        return view('homepage::programs');
    }

    private function getFaqs(): array
    {
        return cache()->remember('kabataan_faqs_v5', 3600, function () {
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
                    'answer' => 'Registered users can complete the KK Profiling Form, apply for scholarship programs, join events and activities, receive announcements, answer surveys, submit required documents, track application status, and access other youth-related services offered by the Municipality of Santa Cruz.',
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

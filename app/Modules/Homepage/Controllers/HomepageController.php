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
        return cache()->remember('kabataan_faqs_v10', 3600, function () {
            return [
                [
                    'id' => 1,
                    'category' => 'general',
                    'question' => 'What is SK OnePortal?',
                    'questionTagalog' => 'Ano ang SK OnePortal?',
                    'answer' => 'SK OnePortal is the official digital platform of the Municipality of Santa Cruz that connects Kabataan, Sangguniang Kabataan (SK) Officials, SK Federation, and the Local Youth Development Office (LYDO). It provides online access to youth programs, scholarship applications, events, announcements, surveys, profiling, and other SK-related services in one centralized system.',
                    'answerTagalog' => 'Ang SK OnePortal ay ang opisyal na digital platform ng Munisipyo ng Santa Cruz na nagkokonekta sa Kabataan, mga Opisyal ng Sangguniang Kabataan (SK), SK Federation, at ang Local Youth Development Office (LYDO). Nagbibigay ito ng online access sa mga programa para sa kabataan, aplikasyon ng scholarship, mga gawain, anunsyo, survey, profiling, at iba pang serbisyong kaugnay ng SK sa iisang sentralisadong sistema.',
                ],
                [
                    'id' => 2,
                    'category' => 'account',
                    'question' => 'How do I create an account?',
                    'questionTagalog' => 'Paano ako makapag-create ng account?',
                    'answer' => 'Click the Sign Up button on the homepage and complete the registration form with accurate personal information. Verify your email address if required, then submit the form. Once your registration is approved or verified, you can sign in and access the system.',
                    'answerTagalog' => 'Pindutin ang Sign Up na button sa homepage at kumpletuhin ang registration form gamit ang iyong tumpak na personal na impormasyon. Beripikahin ang iyong email address kung kailangan, pagkatapos ay ipadala ang form. Kapag naaprubahan o naberipika na ang iyong pagpaparehistro, maaari ka nang mag-sign in at mag-access ng sistema.',
                ],
                [
                    'id' => 3,
                    'category' => 'account',
                    'question' => 'How do I sign in to my account?',
                    'questionTagalog' => 'Paano ako mag-sign in sa aking account?',
                    'answer' => 'Click the Sign In button, enter your registered email address and password, then click Login. If you forget your password, use the Forgot Password option to receive a reset link by email.',
                    'answerTagalog' => 'Pindutin ang Sign In na button, ilagay ang iyong nakarehistrong email address at password, pagkatapos ay pindutin ang Login. Kung nakalimutan mo ang iyong password, gamitin ang Forgot Password na option upang makatanggap ng link sa pag-reset sa email.',
                ],
                [
                    'id' => 4,
                    'category' => 'account',
                    'question' => 'What is KK Profiling?',
                    'questionTagalog' => 'Ano ang KK Profiling?',
                    'answer' => 'KK Profiling is the official youth profile form for Katipunan ng Kabataan members aged 15–30 in Santa Cruz. After you create a Kabataan account, complete KK Profiling so your barangay SK has an accurate youth record. Approved profile details can also be used when you apply for programs such as scholarships.',
                    'answerTagalog' => 'Ang KK Profiling ay ang opisyal na youth profile form para sa mga Katipunan ng Kabataan na 15–30 taong gulang sa Santa Cruz. Pagkatapos mong gawin ang iyong Kabataan account, kumpletuhin ang KK Profiling para mayroon ang iyong barangay SK ng tumpak na tala ng kabataan. Maaari ring gamitin ang naaprubahan mong profile kapag nag-aapply ka sa mga programa tulad ng scholarship.',
                ],
                [
                    'id' => 5,
                    'category' => 'services',
                    'question' => 'What services can I access through SK OnePortal?',
                    'questionTagalog' => 'Anong mga serbisyo ang maaari kong i-access sa SK OnePortal?',
                    'answer' => 'Registered users can complete KK Profiling, discover available SK programs and activities, register for supported programs, submit supported requirements online when a program allows it, receive announcements, answer surveys, and access other youth-related services offered through SK OnePortal. Online registration and document submission depend on the specific program. Anyone can also view public Barangay ABYIP documents and program accomplishments without signing in.',
                    'answerTagalog' => 'Ang mga nakarehistrong gumagamit ay maaaring kumpletuhin ang KK Profiling, makita ang mga available na SK program at aktibidad, mag-register para sa mga suportadong programa, mag-submit ng mga suportadong requirements online kapag pinapayagan ng programa, tumanggap ng anunsyo, sumagot ng survey, at mag-access ng iba pang serbisyong kaugnay ng kabataan na inaalok ng SK OnePortal. Nakadepende sa tiyak na programa ang availability ng online registration at document submission. Makikita rin ng sinuman ang mga dokumento ng Barangay ABYIP at program accomplishments nang hindi nag-sign in.',
                ],
                [
                    'id' => 6,
                    'category' => 'general',
                    'question' => 'Who can use SK OnePortal?',
                    'questionTagalog' => 'Sino ang maaaring gumamit ng SK OnePortal?',
                    'answer' => 'SK OnePortal is intended for Kabataan residing in the Municipality of Santa Cruz, SK Officials, SK Federation members, the Local Youth Development Office (LYDO), and other authorized municipal personnel, depending on their assigned roles and permissions.',
                    'answerTagalog' => 'Ang SK OnePortal ay para sa mga Kabataan na naninirahan sa Munisipyo ng Santa Cruz, mga Opisyal ng SK, mga miyembro ng SK Federation, ang Local Youth Development Office (LYDO), at iba pang awtorisadong tauhan ng munisipyo, depende sa kanilang tungkulin at mga pahintulot.',
                ],
            ];
        });
    }
}

<?php

use App\Modules\Programs\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/api/kabataan/programs', [ProgramController::class, 'index'])->name('kabataan.programs.index');
    Route::get('/api/kabataan/programs/schedule/{id}', [ProgramController::class, 'showSchedule'])->name('kabataan.programs.schedule.show');
    Route::get('/api/kabataan/programs/applications', [ProgramController::class, 'listApplications'])->name('kabataan.programs.applications.index');
    Route::get('/api/kabataan/programs/applications/{id}', [ProgramController::class, 'showApplication'])->name('kabataan.programs.applications.show');
    Route::post('/api/kabataan/programs/applications', [ProgramController::class, 'submitApplication'])->name('kabataan.programs.applications.store');
    Route::post('/api/kabataan/programs/applications/{id}/cancel', [ProgramController::class, 'cancelApplication'])->name('kabataan.programs.applications.cancel');
    Route::post('/api/kabataan/programs/documents/upload', [ProgramController::class, 'uploadDocument'])->name('kabataan.programs.documents.upload');
    Route::get('/api/kabataan/programs/documents/{scheduleProgramId}/{questionId}', [ProgramController::class, 'showDocument'])->name('kabataan.programs.documents.show');

    Route::get('/api/kabataan/programs/surveys/{id}', [ProgramController::class, 'showSurvey'])->name('kabataan.programs.surveys.show')->whereNumber('id');
    Route::get('/api/kabataan/programs/surveys/by-program/{abyipProgramId}', [ProgramController::class, 'showSurveyByProgram'])->name('kabataan.programs.surveys.by-program')->whereNumber('abyipProgramId');
    Route::get('/api/kabataan/programs/survey-responses', [ProgramController::class, 'listSurveyResponses'])->name('kabataan.programs.survey-responses.index');
    Route::get('/api/kabataan/programs/survey-responses/{id}', [ProgramController::class, 'showSurveyResponse'])->name('kabataan.programs.survey-responses.show')->whereNumber('id');
    Route::post('/api/kabataan/programs/survey-responses', [ProgramController::class, 'submitSurveyResponse'])->name('kabataan.programs.survey-responses.store');

    Route::get('/api/kabataan/programs/evaluations/{id}', [ProgramController::class, 'showEvaluation'])->name('kabataan.programs.evaluations.show')->whereNumber('id');
    Route::get('/api/kabataan/programs/evaluations/by-program/{abyipProgramId}', [ProgramController::class, 'showEvaluationByProgram'])->name('kabataan.programs.evaluations.by-program')->whereNumber('abyipProgramId');
    Route::post('/api/kabataan/programs/evaluation-responses', [ProgramController::class, 'submitEvaluationResponse'])->name('kabataan.programs.evaluation-responses.store');

    Route::get('/scholarship/apply', [ProgramController::class, 'scholarshipLanding'])->name('scholarship.apply');
    Route::get('/scholarship/apply/form', [ProgramController::class, 'scholarshipForm'])->name('scholarship.apply.form');

    // Secured survey page routes (path params + auth + ownership checks in controller)
    Route::get('/programs/survey/form', [ProgramController::class, 'surveyFormLegacy'])->name('programs.survey.form.legacy');
    Route::get('/programs/survey/responses/{response}', [ProgramController::class, 'surveyResponse'])->name('programs.survey.response')->whereNumber('response');
    Route::get('/programs/survey/{survey}/answer', [ProgramController::class, 'surveyForm'])->name('programs.survey.form')->whereNumber('survey');
    Route::get('/programs/survey/{program}', [ProgramController::class, 'surveyLanding'])->name('programs.survey.landing')->whereNumber('program');
    Route::get('/programs/survey', [ProgramController::class, 'surveyLandingHome'])->name('programs.survey.home');

    Route::get('/programs/evaluation/form', [ProgramController::class, 'evaluationForm'])->name('programs.evaluation.form');

    Route::get('/sports/apply', [ProgramController::class, 'sportsLanding'])->name('sports.apply');
    Route::get('/sports/apply/form', [ProgramController::class, 'sportsForm'])->name('sports.apply.form');

    Route::redirect('/presurvey/{slug}', '/programs/survey')->name('programs.presurvey');
});

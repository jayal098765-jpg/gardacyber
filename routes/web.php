<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResolvedController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\VulnerabilityController;
use App\Http\Middleware\EnsureTermsAccepted;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, '__invoke'])->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/signup', [RegisterController::class, 'create'])->name('signup');
    Route::post('/signup', [RegisterController::class, 'store'])->middleware('throttle:signup');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::get('/terms/accept', [TermsController::class, 'accept'])->name('terms.accept');
    Route::post('/terms/accept', [TermsController::class, 'store']);
});

Route::middleware(['auth', EnsureTermsAccepted::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/storyboard', [GroupController::class, 'storyboard'])->name('groups.storyboard');
    Route::post('/groups/{group}/ai-summary', [GroupController::class, 'aiSummary'])->name('groups.ai-summary');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    Route::get('/vulnerabilities', [VulnerabilityController::class, 'index'])->name('vulnerabilities.index');
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/findings/{finding}', [IssueController::class, 'show'])->name('findings.show');
    Route::post('/findings/{finding}/ai-explain', [IssueController::class, 'aiExplain'])->name('findings.ai-explain');
    Route::patch('/findings/{finding}', [IssueController::class, 'update'])->name('findings.update');
    Route::get('/resolved', [ResolvedController::class, 'index'])->name('resolved.index');
    Route::post('/findings/{finding}/restore', [ResolvedController::class, 'restore'])->name('findings.restore');
    Route::delete('/findings/{finding}', [ResolvedController::class, 'destroy'])->name('findings.destroy');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::post('/scans/{scan}/stop', [ActivityController::class, 'stop'])->name('activity.scans.stop');
    Route::delete('/scans/{scan}', [ActivityController::class, 'destroy'])->name('activity.scans.destroy');

    Route::get('/scan/new', [ScanController::class, 'create'])->name('scans.new');
    Route::post('/scan/new', [ScanController::class, 'store'])->name('scans.store');
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::get('/scans/{scan}/progress', [ScanController::class, 'progress'])->name('scans.progress');
    Route::post('/scans/{scan}/stop', [ScanController::class, 'stop'])->name('scans.stop');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/telegram-test', [ProfileController::class, 'telegramTest'])->name('profile.telegram-test');

    Route::get('/ai-settings', [AiSettingsController::class, 'edit'])->name('ai-settings');
    Route::patch('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.store');
    Route::post('/ai-settings/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');

    Route::get('/ai-chat', [AiChatController::class, 'index'])->name('ai-chat');
    Route::post('/ai-chat', [AiChatController::class, 'store'])->name('ai-chat.store');
    Route::post('/ai-chat/clear', [AiChatController::class, 'clear'])->name('ai-chat.clear');
});

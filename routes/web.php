<?php

use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogImportController;
use App\Http\Controllers\BookRequestController;
use App\Http\Controllers\BookReviewController;
use App\Http\Controllers\BookReviewModerationController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\CourseReadingListController;
use App\Http\Controllers\PurchasePlanningController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\LibraryPolicyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');

// Job Application Routes
Route::get('/apply',   [JobApplicationController::class, 'create'])->name('job.create');
Route::post('/apply',  [JobApplicationController::class, 'store'])->name('job.store');
Route::get('/success', [JobApplicationController::class, 'success'])->name('job.success');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/suggestions', [CatalogController::class, 'suggestions'])->name('catalog.suggestions');
Route::get('/catalog/{book}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth', 'audit'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::middleware('role:member')->group(function () {
        Route::get('/my-dashboard', [MemberDashboardController::class, 'index'])->name('dashboard.member');
        Route::get('/reading-lists', [CourseReadingListController::class, 'index'])->name('reading-lists.index');
        Route::get('/reading-lists/{readingList}', [CourseReadingListController::class, 'show'])->name('reading-lists.show');
        Route::post('/reading-lists', [CourseReadingListController::class, 'store'])->name('reading-lists.store');
        Route::put('/reading-lists/{readingList}', [CourseReadingListController::class, 'update'])->name('reading-lists.update');
        Route::delete('/reading-lists/{readingList}', [CourseReadingListController::class, 'destroy'])->name('reading-lists.destroy');
        Route::post('/reading-lists/{readingList}/items', [CourseReadingListController::class, 'addBook'])->name('reading-lists.items.store');
        Route::delete('/reading-lists/{readingList}/items/{item}', [CourseReadingListController::class, 'removeBook'])->name('reading-lists.items.destroy');
        Route::get('/book-requests', [BookRequestController::class, 'mine'])->name('book-requests.mine');
        Route::post('/book-requests', [BookRequestController::class, 'store'])->name('book-requests.store');
        Route::get('/book-requests/book-suggestions', [CatalogController::class, 'bookSuggestions'])->name('book-requests.book-suggestions');
        Route::put('/catalog/{book}/reviews', [BookReviewController::class, 'store'])->name('book-reviews.store');
        Route::delete('/catalog/{book}/reviews', [BookReviewController::class, 'destroy'])->name('book-reviews.destroy');
        Route::post('/book-reviews/{bookReview}/reports', [BookReviewModerationController::class, 'report'])->name('book-reviews.report');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/my-loans', [LoanController::class, 'mine'])->name('loans.mine');
    Route::patch('/my-loans/{loan}/renew', [LoanController::class, 'renew'])->name('loans.renew');
    Route::get('/my-reservations', [ReservationController::class, 'index'])->name('reservations.mine');
    Route::post('/catalog/{book}/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/admin/library-policies', [LibraryPolicyController::class, 'edit'])->name('library-policies.edit');
        Route::put('/admin/library-policies', [LibraryPolicyController::class, 'update'])->name('library-policies.update');
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/admin/catalog', [CatalogController::class, 'adminIndex'])->name('catalog.admin.index');
        Route::get('/admin/inventory-audits', [InventoryAuditController::class, 'index'])->name('inventory-audits.index');
        Route::post('/admin/inventory-audits', [InventoryAuditController::class, 'store'])->name('inventory-audits.store');
        Route::get('/admin/inventory-audits/{inventoryAudit}', [InventoryAuditController::class, 'show'])->name('inventory-audits.show');
        Route::post('/admin/inventory-audits/{inventoryAudit}/scans', [InventoryAuditController::class, 'scan'])->name('inventory-audits.scan');
        Route::patch('/admin/inventory-audits/{inventoryAudit}/complete', [InventoryAuditController::class, 'complete'])->name('inventory-audits.complete');
        Route::patch('/admin/inventory-audits/{inventoryAudit}/items/{item}/lost', [InventoryAuditController::class, 'markLost'])->name('inventory-audits.items.lost');
        Route::get('/admin/catalog/import', [CatalogImportController::class, 'create'])->name('catalog.admin.import-form');
        Route::get('/admin/catalog/import-template', [CatalogImportController::class, 'template'])->name('catalog.admin.import-template');
        Route::post('/admin/catalog/import', [CatalogImportController::class, 'store'])->name('catalog.admin.import');
        Route::get('/admin/catalog/create', [CatalogController::class, 'create'])->name('catalog.admin.create');
        Route::get('/admin/catalog/book-suggestions', [CatalogController::class, 'bookSuggestions'])->name('catalog.admin.book-suggestions');
        Route::post('/admin/catalog', [CatalogController::class, 'store'])->name('catalog.admin.store');
        Route::get('/admin/catalog/{book}/edit', [CatalogController::class, 'edit'])->name('catalog.admin.edit');
        Route::get('/admin/catalog/{book}/copies', [CatalogController::class, 'copies'])->name('catalog.admin.copies');
        Route::patch('/admin/catalog/{book}/copies/{copy}', [CatalogController::class, 'updateCopyStatus'])->name('catalog.admin.copies.update');
        Route::put('/admin/catalog/{book}', [CatalogController::class, 'update'])->name('catalog.admin.update');
        Route::delete('/admin/catalog/{book}', [CatalogController::class, 'destroy'])->name('catalog.admin.destroy');
        Route::get('/admin/book-requests', [BookRequestController::class, 'adminIndex'])->name('book-requests.admin');
        Route::patch('/admin/book-requests/{bookRequest}', [BookRequestController::class, 'update'])->name('book-requests.update');
        Route::get('/admin/book-reviews/reports', [BookReviewModerationController::class, 'index'])->name('book-reviews.reports');
        Route::patch('/admin/book-reviews/reports/{report}', [BookReviewModerationController::class, 'resolve'])->name('book-reviews.reports.resolve');
        Route::patch('/admin/book-reviews/{bookReview}', [BookReviewModerationController::class, 'visibility'])->name('book-reviews.moderate');
        Route::get('/admin/categories', [CatalogController::class, 'categories'])->name('catalog.categories.index');
        Route::post('/admin/categories', [CatalogController::class, 'storeCategory'])->name('catalog.categories.store');
        Route::put('/admin/categories/{category}', [CatalogController::class, 'updateCategory'])->name('catalog.categories.update');
        Route::patch('/admin/categories/{category}/merge', [CatalogController::class, 'mergeCategory'])->name('catalog.categories.merge');

        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::patch('/members/{member}/membership', [MemberController::class, 'updateMembership'])->name('members.membership.update');

        Route::get('/admin/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/admin/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/admin/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/admin/reports/purchase-planning', [PurchasePlanningController::class, 'index'])->name('reports.purchase-planning');
        Route::get('/admin/reports/loans.csv', [ReportsController::class, 'exportLoans'])->name('reports.loans.export');
        Route::get('/admin/reports/fines', [ReportsController::class, 'fineReport'])->name('reports.fines');
        Route::get('/admin/reports/fines.csv', [ReportsController::class, 'exportFines'])->name('reports.fines.export');
        Route::get('/admin/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/admin/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::patch('/admin/loans/{loan}/return', [LoanController::class, 'return'])->name('loans.return');
        Route::patch('/admin/fines/{fine}/pay', [FineController::class, 'recordPayment'])->name('fines.pay');
        Route::patch('/admin/fines/{fine}/adjust', [FineController::class, 'adjust'])->name('fines.adjust');
    });
});

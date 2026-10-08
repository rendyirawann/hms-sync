<?php

use Illuminate\Support\Facades\Route;

// Dashboard
use App\Http\Controllers\Backend\Dashboard\DashboardAdminController;

// My profile
use App\Http\Controllers\Backend\MyProfile\AccountController;
use App\Http\Controllers\Backend\MyProfile\ActivityController;
use App\Http\Controllers\Backend\MyProfile\LoginSessionController;
use App\Http\Controllers\Backend\MyProfile\ProfileController;
use App\Http\Controllers\Backend\MyProfile\SecurityController;

// User management
use App\Http\Controllers\Backend\UserManagement\RoleController;
use App\Http\Controllers\Backend\UserManagement\UserController;

// Help / system
use App\Http\Controllers\Backend\Help\LogActivityController;
use App\Http\Controllers\Backend\Settings\SettingController;

// Hotel management
use App\Http\Controllers\Backend\Hms\BookingController;
use App\Http\Controllers\Backend\Hms\CalendarController;
use App\Http\Controllers\Backend\Hms\GalleryController;
use App\Http\Controllers\Backend\Hms\HousekeepingController;
use App\Http\Controllers\Backend\Hms\PaymentController;
use App\Http\Controllers\Backend\Hms\PromoCodeController;
use App\Http\Controllers\Backend\Hms\RateRuleController;
use App\Http\Controllers\Backend\Hms\ReportController;
use App\Http\Controllers\Backend\Hms\ReviewController;
use App\Http\Controllers\Backend\Hms\HotelController;
use App\Http\Controllers\Backend\Hms\MyShiftController;
use App\Http\Controllers\Backend\Hms\RoomController;
use App\Http\Controllers\Backend\Hms\RoomTypeController;
use App\Http\Controllers\Backend\Hms\RosterController;
use App\Http\Controllers\Backend\Hms\ShiftController;
use App\Http\Controllers\Backend\Hms\StaffController;

// Public site
use App\Http\Controllers\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\TripayCallbackController;
use App\Http\Controllers\FrontendController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Public site & guest (customer) area
|--------------------------------------------------------------------------
*/
Route::get('/', [FrontendController::class, 'index'])->name('landing');
Route::get('/kamar', [FrontendController::class, 'search'])->name('rooms.search');
Route::get('/kamar/{roomType}', [FrontendController::class, 'roomType'])->name('rooms.show');

// Tripay server-to-server callback (signature-verified, CSRF exempt).
Route::post('/payment/tripay/callback', TripayCallbackController::class)
    ->middleware('throttle:api')->name('payment.tripay.callback');

Route::middleware(['auth', 'verified', 'forbid-banned-user', 'role:Customer'])->group(function () {
    Route::get('/reservasi', [CustomerBookingController::class, 'index'])->name('customer.bookings.index');
    Route::get('/reservasi/baru/{roomType}', [CustomerBookingController::class, 'create'])->name('customer.bookings.create');
    Route::get('/reservasi/{booking}', [CustomerBookingController::class, 'show'])->name('customer.bookings.show');
    Route::get('/reservasi/{booking}/invoice', [CustomerBookingController::class, 'invoice'])->name('customer.bookings.invoice');
    Route::get('/profil', [CustomerProfileController::class, 'edit'])->name('customer.profile');

    Route::middleware('throttle:write')->group(function () {
        Route::post('/reservasi/baru/{roomType}', [CustomerBookingController::class, 'store'])->name('customer.bookings.store');
        Route::post('/reservasi/{booking}/batal', [CustomerBookingController::class, 'cancel'])->name('customer.bookings.cancel');
        Route::post('/reservasi/{booking}/bayar', [CustomerBookingController::class, 'payTripay'])->name('customer.bookings.pay');
        Route::post('/reservasi/{booking}/bukti-transfer', [CustomerBookingController::class, 'uploadProof'])->name('customer.bookings.proof');
        Route::post('/reservasi/{booking}/ulasan', [CustomerBookingController::class, 'review'])->name('customer.bookings.review');
        Route::put('/profil', [CustomerProfileController::class, 'update'])->name('customer.profile.update');
        Route::put('/profil/password', [CustomerProfileController::class, 'password'])->middleware('throttle:password')->name('customer.profile.password');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel — hotel staff only (customers are kept out by role)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'forbid-banned-user', 'role:Superadmin|superadmin|admin|Pegawai'])->group(function () {

    // --- HMS: master data ---
    Route::middleware('can:hms.master')->prefix('admin/hms')->group(function () {
        Route::get('hotel', [HotelController::class, 'edit'])->name('hms.hotel.edit');
        Route::put('hotel', [HotelController::class, 'update'])->middleware('throttle:write')->name('hms.hotel.update');

        Route::resource('room-types', RoomTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
            ->names('hms.room-types');
        Route::resource('rooms', RoomController::class)->only(['index', 'store', 'update', 'destroy'])
            ->names('hms.rooms');
        Route::post('rooms/{room}/available', [RoomController::class, 'markAvailable'])->name('hms.rooms.available');
        Route::resource('shifts', ShiftController::class)->only(['index', 'store', 'update', 'destroy'])
            ->names('hms.shifts');
        Route::resource('staff', StaffController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['staff' => 'staff'])->names('hms.staff');

        // Pricing: seasonal / weekend rules + promo codes
        Route::get('pricing', [RateRuleController::class, 'index'])->name('hms.pricing.index');
        Route::resource('rate-rules', RateRuleController::class)->only(['store', 'update', 'destroy'])->names('hms.rate-rules');
        Route::resource('promo-codes', PromoCodeController::class)->only(['store', 'update', 'destroy'])->names('hms.promo-codes');

        // Galleries
        Route::get('gallery', [GalleryController::class, 'index'])->name('hms.gallery.index');
        Route::post('gallery/room-types/{roomType}', [GalleryController::class, 'storeRoomType'])->name('hms.gallery.room-type.store');
        Route::delete('gallery/room-types/images/{image}', [GalleryController::class, 'destroyRoomType'])->name('hms.gallery.room-type.destroy');
        Route::post('gallery/hotel', [GalleryController::class, 'storeHotel'])->name('hms.gallery.hotel.store');
        Route::delete('gallery/hotel/{image}', [GalleryController::class, 'destroyHotel'])->name('hms.gallery.hotel.destroy');

        // Guest reviews moderation
        Route::get('reviews', [ReviewController::class, 'index'])->name('hms.reviews.index');
        Route::post('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('hms.reviews.toggle');
    });

    // --- HMS: reports ---
    Route::middleware('can:hms.reports')->prefix('admin/hms/reports')->name('hms.reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('shifts', [ReportController::class, 'shifts'])->name('shifts');
    });

    // --- HMS: housekeeping ---
    Route::middleware('can:hms.housekeeping')->prefix('admin/hms/housekeeping')->name('hms.housekeeping.')->group(function () {
        Route::get('/', [HousekeepingController::class, 'index'])->name('index');
        Route::post('/', [HousekeepingController::class, 'store'])->name('store');
        Route::post('{task}/assign', [HousekeepingController::class, 'assign'])->name('assign');
        Route::post('{task}/start', [HousekeepingController::class, 'start'])->name('start');
        Route::post('{task}/finish', [HousekeepingController::class, 'finish'])->name('finish');
    });

    // --- HMS: shift roster ---
    Route::middleware('can:hms.roster')->prefix('admin/hms')->group(function () {
        Route::get('roster', [RosterController::class, 'index'])->name('hms.roster.index');
        Route::post('roster', [RosterController::class, 'store'])->name('hms.roster.store');
        Route::delete('roster/{employeeShift}', [RosterController::class, 'destroy'])->name('hms.roster.destroy');
    });

    // --- HMS: my shift (clock in / out) ---
    Route::middleware('can:hms.my_shift')->prefix('admin/hms')->group(function () {
        Route::get('my-shift', [MyShiftController::class, 'index'])->name('hms.my-shift.index');
        Route::post('my-shift/{employeeShift}/start', [MyShiftController::class, 'start'])->name('hms.my-shift.start');
        Route::post('my-shift/{employeeShift}/finish', [MyShiftController::class, 'finish'])->name('hms.my-shift.finish');
    });

    // --- HMS: bookings & front desk ---
    Route::middleware('can:hms.booking')->prefix('admin/hms/bookings')->name('hms.bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::get('payments/{payment}/proof', [PaymentController::class, 'proof'])->name('payments.proof');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
        Route::get('{booking}', [BookingController::class, 'show'])->name('show');
        Route::get('{booking}/invoice', [BookingController::class, 'invoice'])->name('invoice');
        Route::post('{booking}/extend', [BookingController::class, 'extend'])->name('extend');
        Route::post('{booking}/move', [BookingController::class, 'move'])->name('move');
        Route::post('{booking}/charges', [BookingController::class, 'addCharge'])->name('charges.store');
        Route::delete('{booking}/charges/{charge}', [BookingController::class, 'removeCharge'])->name('charges.destroy');
        Route::post('{booking}/paid', [BookingController::class, 'markPaid'])->name('paid');
        Route::post('{booking}/confirm', [BookingController::class, 'confirm'])->name('confirm');
        Route::post('{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
        Route::post('{booking}/check-in', [BookingController::class, 'checkIn'])->name('check-in');
        Route::post('{booking}/check-out', [BookingController::class, 'checkOut'])->name('check-out');
        Route::post('{booking}/passcode', [BookingController::class, 'regeneratePasscode'])->name('passcode');
    });

    // --- Dashboard (every authenticated role) ---
    Route::get('/admin/dashboard', [DashboardAdminController::class, 'index'])->name('dashboard');

    // --- My account (every authenticated user) ---
    Route::get('/admin/my-account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/admin/my-account/{id}/avatar', [AccountController::class, 'editAvatar'])->name('avatar-edit');

    Route::get('/admin/my-activity', [ActivityController::class, 'index'])->name('my-activity.index');
    Route::get('/admin/mget-my-activity', [ActivityController::class, 'getActivity'])->name('get-my-activity');

    Route::get('/admin/mmy-login-session', [LoginSessionController::class, 'index'])->name('my-login-session.index');
    Route::get('/admin/mget-my-login-session', [LoginSessionController::class, 'getLoginSession'])->name('get-my-login-session');

    // --- Activity log ---
    // Accessible to every authenticated user; the controller scopes the query
    // so a non-Superadmin only ever sees the entries they caused.
    Route::get('/admin/log-activity', [LogActivityController::class, 'index'])->name('log-activity.index');
    Route::get('/admin/get-datalogactivity', [LogActivityController::class, 'getDataLogActivity'])->name('get-datalogactivity');
    Route::get('/admin/log-activity/{id}/detail', [LogActivityController::class, 'detail'])->name('log-activity.detail');
    Route::get('/admin/log-activity/{id}', [LogActivityController::class, 'show'])->name('log-activity.show');

    // Shared helpers used by the role/user forms.
    Route::get('/admin/select/role', [RoleController::class, 'select'])->name('role.select');

    /*
    |----------------------------------------------------------------------
    | Write operations — tighter rate limit than plain page reads.
    |----------------------------------------------------------------------
    */
    Route::middleware('throttle:write')->group(function () {

        Route::post('/admin/my-account/{id}/update-avatar', [AccountController::class, 'updateAvatar'])->name('avatar-update');
        Route::post('/admin/my-security', [SecurityController::class, 'store'])->name('change.password');
        Route::post('/admin/my-security/logout-other-devices', [SecurityController::class, 'logoutOtherDevices'])
            ->name('security.logout-other-devices');

        // --- Settings (Superadmin) ---
        Route::middleware('can:view_resources')->group(function () {
            Route::post('/admin/settings/update', [SettingController::class, 'update'])->name('settings.update');
            Route::post('/admin/settings/regenerate-branding', [SettingController::class, 'regenerateBranding'])
                ->name('settings.branding');

            Route::post('/admin/roles/generate-permissions', [RoleController::class, 'generatePermissions'])->name('roles.generate');
            Route::post('/admin/users/mass-delete', [UserController::class, 'massDelete'])->name('users.mass-delete');
            Route::post('/admin/users/{id}/ban', [UserController::class, 'ban'])->name('users.ban');
            Route::post('/admin/users/{id}/unban', [UserController::class, 'unban'])->name('users.unban');
            Route::post('/admin/roles/mass-delete', [RoleController::class, 'massDelete'])->name('roles.mass-delete');
        });
    });

    // --- Profile & security pages (resource controllers keep their own verbs) ---
    Route::resource('/admin/my-profile', ProfileController::class);
    Route::resource('/admin/my-security', SecurityController::class);

    // --- Settings page (read) ---
    Route::get('/admin/settings', [SettingController::class, 'index'])
        ->middleware('can:view_resources')
        ->name('settings.index');

    /*
    |----------------------------------------------------------------------
    | User & role management — Superadmin only.
    |----------------------------------------------------------------------
    */
    Route::middleware('can:view_resources')->group(function () {
        Route::resource('/admin/users', UserController::class);
        Route::get('/admin/get-datauser', [UserController::class, 'getDataUsers'])->name('get-users');
        Route::get('/admin/get-user-show-log/{id}', [UserController::class, 'getLoginSession'])->name('get-user-show-log');
        Route::get('/admin/get-user-show-log-activity/{id}', [UserController::class, 'getActivity'])->name('get-user-show-log-activity');

        Route::resource('/admin/roles', RoleController::class);
        Route::get('/admin/get-datarole', [RoleController::class, 'getDataRoles'])->name('get-datarole');
    });
});

require __DIR__ . '/auth.php';

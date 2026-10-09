{{-- Horizontal primary navigation (desktop only; mobile uses the drawer). --}}
<nav class="menu menu-rounded menu-column menu-lg-row menu-root-here-bg-desktop menu-active-bg menu-state-primary menu-title-gray-800 menu-arrow-gray-500 align-items-stretch fw-semibold fs-6 py-1"
    data-kt-menu="true" aria-label="Navigasi utama">

    <div class="menu-item me-lg-2 {{ request()->routeIs('dashboard') ? 'here show menu-here-bg' : '' }}">
        <a class="menu-link py-3" href="{{ route('dashboard') }}">
            <span class="menu-icon">
                <i class="ki-duotone ki-element-11 fs-3">
                    <span class="path1"></span><span class="path2"></span>
                    <span class="path3"></span><span class="path4"></span>
                </i>
            </span>
            <span class="menu-title">Dashboard</span>
        </a>
    </div>

    @role('Superadmin')
        <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
            class="menu-item menu-lg-down-accordion me-lg-2 {{ request()->routeIs('platform.*') ? 'here show menu-here-bg' : '' }}">
            <span class="menu-link py-3">
                <span class="menu-icon"><i class="ki-duotone ki-abstract-26 fs-3"><span class="path1"></span><span class="path2"></span></i></span>
                <span class="menu-title">Platform</span>
                @php $pendingHotels = \App\Models\Hotel::where('approval_status', 'pending')->count(); @endphp
                @if ($pendingHotels)
                    <span class="badge badge-circle badge-warning ms-2">{{ $pendingHotels }}</span>
                @endif
                <span class="menu-arrow d-lg-none"></span>
            </span>
            <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-250px">
                @foreach ([
                    ['platform.hotels.index', 'platform.hotels.*', 'ki-bank', 'Hotel Mitra'],
                    ['platform.payments.index', 'platform.payments.*', 'ki-wallet', 'Verifikasi Transfer'],
                    ['platform.settlements.index', 'platform.settlements.*', 'ki-dollar', 'Setoran ke Hotel'],
                    ['platform.settings.edit', 'platform.settings.*', 'ki-setting-3', 'Pengaturan Platform'],
                ] as [$route, $pattern, $icon, $label])
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
                            <span class="menu-icon"><i class="ki-outline {{ $icon }} fs-4"></i></span>
                            <span class="menu-title">{{ $label }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endrole

    @if (\App\Models\Hotel::current())
    @canany(['hms.booking', 'hms.housekeeping', 'hms.reports'])
        <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
            class="menu-item menu-lg-down-accordion me-lg-2 {{ request()->routeIs('hms.bookings.*', 'hms.housekeeping.*', 'hms.reports.*') ? 'here show menu-here-bg' : '' }}">
            <span class="menu-link py-3">
                <span class="menu-icon"><i class="ki-duotone ki-calendar-tick fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i></span>
                <span class="menu-title">Operasional</span>
                <span class="menu-arrow d-lg-none"></span>
            </span>
            <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-225px">
                @foreach ([
                    ['hms.bookings.index', 'hms.bookings.index', 'ki-calendar-tick', 'Reservasi', 'hms.booking'],
                    ['hms.bookings.calendar', 'hms.bookings.calendar', 'ki-calendar-8', 'Kalender Okupansi', 'hms.booking'],
                    ['hms.housekeeping.index', 'hms.housekeeping.*', 'ki-brush', 'Housekeeping', 'hms.housekeeping'],
                    ['hms.reports.index', 'hms.reports.index', 'ki-chart-line-up', 'Laporan Hotel', 'hms.reports'],
                    ['hms.reports.shifts', 'hms.reports.shifts', 'ki-time', 'Rekap Shift', 'hms.reports'],
                    ['hms.settlements.index', 'hms.settlements.*', 'ki-dollar', 'Setoran dari Platform', 'hms.reports'],
                ] as [$route, $pattern, $icon, $label, $ability])
                    @can($ability)
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
                                <span class="menu-icon"><i class="ki-outline {{ $icon }} fs-4"></i></span>
                                <span class="menu-title">{{ $label }}</span>
                            </a>
                        </div>
                    @endcan
                @endforeach
            </div>
        </div>
    @endcanany

    @can('hms.master')
        <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
            class="menu-item menu-lg-down-accordion me-lg-2 {{ request()->routeIs('hms.hotel.*', 'hms.room-types.*', 'hms.rooms.*', 'hms.staff.*', 'hms.shifts.*', 'hms.pricing.*', 'hms.gallery.*', 'hms.reviews.*') ? 'here show menu-here-bg' : '' }}">
            <span class="menu-link py-3">
                <span class="menu-icon"><i class="ki-duotone ki-home-2 fs-3"><span class="path1"></span><span class="path2"></span></i></span>
                <span class="menu-title">Data Master</span>
                <span class="menu-arrow d-lg-none"></span>
            </span>
            <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-225px">
                @foreach ([
                    ['hms.hotel.edit', 'hms.hotel.*', 'ki-bank', 'Profil Hotel'],
                    ['hms.room-types.index', 'hms.room-types.*', 'ki-category', 'Tipe Kamar'],
                    ['hms.rooms.index', 'hms.rooms.*', 'ki-key', 'Kamar'],
                    ['hms.pricing.index', 'hms.pricing.*', 'ki-price-tag', 'Harga & Promo'],
                    ['hms.gallery.index', 'hms.gallery.*', 'ki-picture', 'Galeri Foto'],
                    ['hms.reviews.index', 'hms.reviews.*', 'ki-star', 'Ulasan Tamu'],
                    ['hms.staff.index', 'hms.staff.*', 'ki-profile-user', 'Pegawai'],
                    ['hms.shifts.index', 'hms.shifts.*', 'ki-time', 'Master Shift'],
                ] as [$route, $pattern, $icon, $label])
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
                            <span class="menu-icon"><i class="ki-outline {{ $icon }} fs-4"></i></span>
                            <span class="menu-title">{{ $label }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endcan

    @canany(['hms.roster', 'hms.my_shift'])
        <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
            class="menu-item menu-lg-down-accordion me-lg-2 {{ request()->routeIs('hms.roster.*', 'hms.my-shift.*') ? 'here show menu-here-bg' : '' }}">
            <span class="menu-link py-3">
                <span class="menu-icon"><i class="ki-duotone ki-timer fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></span>
                <span class="menu-title">Shift</span>
                <span class="menu-arrow d-lg-none"></span>
            </span>
            <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-225px">
                @can('hms.my_shift')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('hms.my-shift.*') ? 'active' : '' }}" href="{{ route('hms.my-shift.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-check-circle fs-4"></i></span>
                            <span class="menu-title">Shift Saya</span>
                        </a>
                    </div>
                @endcan
                @can('hms.roster')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('hms.roster.*') ? 'active' : '' }}" href="{{ route('hms.roster.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-calendar fs-4"></i></span>
                            <span class="menu-title">Jadwal Pegawai</span>
                        </a>
                    </div>
                @endcan
            </div>
        </div>
    @endcanany
    @endif

    @role('Superadmin|superadmin')
        <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
            class="menu-item menu-lg-down-accordion me-lg-2 {{ request()->is('admin/users*') || request()->is('admin/roles*') ? 'here show menu-here-bg' : '' }}">
            <span class="menu-link py-3">
                <span class="menu-icon">
                    <i class="ki-duotone ki-people fs-3">
                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        <span class="path4"></span><span class="path5"></span>
                    </i>
                </span>
                <span class="menu-title">User Management</span>
                <span class="menu-arrow d-lg-none"></span>
            </span>
            <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown py-4 w-225px">
                <div class="menu-item">
                    <a class="menu-link {{ request()->is('admin/users*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <span class="menu-icon"><i class="ki-duotone ki-user fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                        <span class="menu-title">Users</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link {{ request()->is('admin/roles*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                        <span class="menu-icon"><i class="ki-duotone ki-shield-tick fs-4"><span class="path1"></span><span class="path2"></span></i></span>
                        <span class="menu-title">Roles &amp; Permissions</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="menu-item me-lg-2 {{ request()->routeIs('settings.*') ? 'here show menu-here-bg' : '' }}">
            <a class="menu-link py-3" href="{{ route('settings.index') }}">
                <span class="menu-icon">
                    <i class="ki-duotone ki-setting-2 fs-3"><span class="path1"></span><span class="path2"></span></i>
                </span>
                <span class="menu-title">Settings</span>
            </a>
        </div>
    @endrole

    {{-- Every role can open this; the controller scopes what they see. --}}
    <div class="menu-item me-lg-2 {{ request()->is('admin/log-activity*') ? 'here show menu-here-bg' : '' }}">
        <a class="menu-link py-3" href="{{ route('log-activity.index') }}">
            <span class="menu-icon">
                <i class="ki-duotone ki-notepad fs-3">
                    <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                    <span class="path4"></span><span class="path5"></span>
                </i>
            </span>
            <span class="menu-title">Log Aktivitas</span>
        </a>
    </div>

</nav>

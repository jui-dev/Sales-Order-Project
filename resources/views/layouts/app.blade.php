<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sales Order System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    {{-- Page-specific styles declared via @section('styles') in child views --}}
    @yield('styles')
    {{-- Page-specific styles pushed via @push('styles') --}}
    @stack('styles')

    <!-- Load jQuery early to ensure it's available -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // Test jQuery loading
        if (typeof jQuery !== 'undefined') {
            console.log('jQuery loaded successfully in head');
        } else {
            console.error('jQuery failed to load in head');
        }
    </script>

    <style>
        /* Multi-level dropdown styles */
        .dropdown-submenu {
            position: relative;
        }
        
        .dropdown-submenu .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -1px;
            border-radius: 0 6px 6px 6px;
        }
        
        .dropdown-submenu:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-submenu .dropdown-toggle::after {
            transform: rotate(-90deg);
            position: absolute;
            right: 6px;
            top: 50%;
            margin-top: -3px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .dropdown-submenu .dropdown-menu {
                position: static !important;
                float: none;
                width: auto;
                margin-top: 0;
                background-color: transparent;
                border: 0;
                box-shadow: none;
                padding-left: 1rem;
            }
        }

        /* ===== Sidebar Navigation ===== */
        .sidebar-nav {
            /* Bootstrap sizes .offcanvas-start from this custom property (default 400px) */
            --bs-offcanvas-width: 250px;
            width: 250px;
            background: var(--primary);
        }
        .sidebar-nav .nav-link {
            color: var(--light-text);
            padding: 0.5rem 1rem;
            /* custom.css puts `transition: all` on every .nav-link, which lets padding and
               margin animate too; only the colours should move here. */
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: var(--light-text);
        }
        /* Menus open and close instantly. Bootstrap animates the panel height over 0.35s,
           and because the sidebar is one accordion it closes another menu at the same
           time, so every item below the two of them slid up and down on each click. */
        .sidebar-nav .collapsing {
            transition: none;
        }
        .sidebar-nav .collapse .nav-link {
            padding-left: 2rem;
            font-size: 0.9rem;
        }
        .sidebar-brand {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            min-height: var(--navbar-height);
        }
        /* Keep the signed-in user block below the navigation rather than floating
           mid-sidebar: the body takes the slack so the footer sits at the bottom
           on short menus, and is simply pushed along on tall ones. */
        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }
        .sidebar-nav .offcanvas-body {
            flex: 1 0 auto;
        }
        .sidebar-user {
            flex-shrink: 0;
        }
        /* custom.css forces .btn { width: 100% } under 576px; keep the toggle compact */
        .navbar .btn {
            width: auto;
        }
        /* Content is full-width next to the sidebar; longhand so padding-top rules survive */
        .main-content {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        @media (max-width: 576px) {
            .main-content {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        /* Permanent full-height sidebar on desktop (no header bar there) */
        @media (min-width: 992px) {
            .sidebar-nav {
                position: fixed;
                top: 0;
                left: 0;
                width: 250px;
                height: 100vh;
                z-index: 1035; /* offcanvas-lg drops z-index at >=lg; set it explicitly */
                transform: none !important;
                visibility: visible !important;
                overflow-y: auto;
                /* Reserve the scrollbar track so opening a long menu doesn't narrow the
                   sidebar and reflow every label sideways */
                scrollbar-gutter: stable;
                background: var(--primary) !important;
            }
            .offcanvas-lg.sidebar-nav {
                background: var(--primary) !important;
            }
            /* Hide backdrop when sidebar is static */
            .offcanvas-backdrop.show {
                display: none;
            }
            /* Bootstrap hides .offcanvas-header at >=lg; we need it for the brand */
            .sidebar-nav .offcanvas-header.sidebar-brand {
                display: flex;
            }

            body {
                padding-left: 250px; /* sidebar is fixed / out of flow */
                padding-top: 0;      /* overrides the old header offset in custom.css */
            }
            /* footer needs no margin-left: body padding-left already shifts it */
            .main-content {
                margin-left: 0;
                padding-top: 1.5rem; /* overrides the old 80px header clearance */
            }
        }
        /* Prevent brand text wrapping on very small screens */
        .navbar-brand {
            white-space: nowrap;
        }

        /* === Sticky footer setup ===
           min-height, not height: a fixed height lets .main-content fill the
           viewport and then pushes the footer's margin + height past it, so
           short pages scroll for no reason. Appearance lives in custom.css —
           this block is emitted after that stylesheet, so declaring colours
           here would override it. */
        html { height: 100%; }
        body {
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }
        .main-content { flex: 1 0 auto; }
        footer {
            flex-shrink: 0;
            margin-left: 0 !important;
        }
    </style>
</head>
<body>
    <!-- BEGIN: Sidebar Navigation Layout -->
    <!-- Mobile-only bar: hosts the sidebar toggle. Hidden on desktop, where the sidebar is permanent. -->
    <nav class="navbar navbar-dark shadow-sm fixed-top d-lg-none">
        <div class="container-fluid d-flex align-items-center">
            <button class="btn btn-outline-light me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand fw-semibold mb-0" href="{{ route('dashboard') }}">
                <i class="bi bi-graph-up me-2"></i>Sales Order System
            </a>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="offcanvas offcanvas-start offcanvas-lg sidebar-nav text-white" tabindex="-1" id="sidebar">
        <div class="offcanvas-header sidebar-brand">
            <a class="navbar-brand fw-semibold text-white mb-0" href="{{ route('dashboard') }}">
                <i class="bi bi-graph-up me-2"></i>Sales Order System
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <nav class="navbar-dark">
                @php
                    // Sidebar highlighting is matched on route names, not on the URL path, so
                    // that links whose paths overlap (e.g. /payments and /supplier-bill-payments)
                    // cannot light each other up.
                    $navActive = fn (...$routes) => request()->routeIs(...$routes) ? ' active' : '';

                    // The Returns sub-items all point at returns.index and are told apart by ?type;
                    // "All Returns" (type null) is the one that wins when no type is filtered.
                    $onReturns = request()->routeIs('returns.*');
                    $returnType = $onReturns ? request()->query('type') : false;
                    $returnActive = fn ($type) => $onReturns && $returnType === $type ? ' active' : '';

                    $procurementOpen = request()->routeIs('purchase-orders.*', 'supplies.*', 'grns.*', 'supplier-bills.*', 'supplier-bill-payments.*');
                    $pickingOpen = request()->routeIs('vendor-to-warehouse-picking.*', 'stock-transfers.*', 'warehouse-to-customer-picking.*', 'retailer-to-customer-picking.*', 'picking-lists.*');
                    $returnsOpen = request()->routeIs('returns.*', 'credit-notes.*', 'debit-notes.*');
                    $stockOpen = request()->routeIs('stock-management.*', 'stock-locations.*', 'picking.transaction-flow');
                    $salesOrderOpen = request()->routeIs('orders.*', 'invoices.*', 'payments.*');
                    $accountingOpen = request()->routeIs('accounting.*', 'journal-entries.*', 'audit-logs.*', 'reports.trial-balance', 'reports.income-statement', 'reports.balance-sheet', 'reports.cash-flow');
                    $adminOpen = request()->routeIs('users.*');
                @endphp
                <ul class="navbar-nav flex-column" id="sidebarAccordion">
                    <!-- Dashboard -->
                    @can('dashboard.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('dashboard') }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </li>
                    @endcan

                    <!-- Master Data -->
                    @can('products.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('products.*') }}" href="{{ route('products.index') }}"><i class="bi bi-box me-2"></i>Products<x-nav-badge for="products" /></a>
                    </li>
                    @endcan
                    @can('vendors.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('vendors.*') }}" href="{{ route('vendors.index') }}"><i class="bi bi-building me-2"></i>Vendors<x-nav-badge for="vendors" /></a>
                    </li>
                    @endcan
                    @can('customers.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('customers.*') }}" href="{{ route('customers.index') }}"><i class="bi bi-people me-2"></i>Customers<x-nav-badge for="customers" /></a>
                    </li>
                    @endcan

                    <!-- Procurement -->
                    @can('purchase-orders.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#procurementMenu" role="button" aria-expanded="{{ $procurementOpen ? 'true' : 'false' }}" aria-controls="procurementMenu">
                            <span><i class="bi bi-cart-check me-2"></i>Procurement<x-nav-badge for="procurement" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $procurementOpen ? ' show' : '' }}" id="procurementMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('purchase-orders.*') }}" href="{{ route('purchase-orders.index') }}"><i class="bi bi-clipboard-check me-2"></i>Purchase Orders<x-nav-badge for="procurement.purchase-orders" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplies.*') }}" href="{{ route('supplies.index') }}"><i class="bi bi-truck me-2"></i>Supplies<x-nav-badge for="procurement.supplies" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('grns.*') }}" href="{{ route('grns.index') }}"><i class="bi bi-receipt me-2"></i>Good Receipt Notes (GRNs)<x-nav-badge for="procurement.grns" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplier-bills.*') }}" href="{{ route('supplier-bills.index') }}"><i class="bi bi-file-earmark-text me-2"></i>Supplier Bills<x-nav-badge for="procurement.supplier-bills" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplier-bill-payments.*') }}" href="{{ route('supplier-bill-payments.index') }}"><i class="bi bi-credit-card me-2"></i>Supplier Bills Payment<x-nav-badge for="procurement.supplier-bill-payments" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Picking & Transfers -->
                    @can('picking.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#pickingMenu" role="button" aria-expanded="{{ $pickingOpen ? 'true' : 'false' }}" aria-controls="pickingMenu">
                            <span><i class="bi bi-list-check me-2"></i>Picking & Transfers<x-nav-badge for="picking" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $pickingOpen ? ' show' : '' }}" id="pickingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('vendor-to-warehouse-picking.*') }}" href="{{ route('vendor-to-warehouse-picking.index') }}"><i class="bi bi-truck-arrow-right me-2"></i>Vendors → Warehouse<x-nav-badge for="picking.vendor-to-warehouse" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('stock-transfers.*') }}" href="{{ route('stock-transfers.warehouse-to-retailer') }}"><i class="bi bi-building-arrow-right me-2"></i>Warehouse → Retailers<x-nav-badge for="picking.warehouse-to-retailers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('warehouse-to-customer-picking.*') }}" href="{{ route('warehouse-to-customer-picking.index') }}"><i class="bi bi-house-arrow-right me-2"></i>Warehouse → Customers<x-nav-badge for="picking.warehouse-to-customers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('retailer-to-customer-picking.*') }}" href="{{ route('retailer-to-customer-picking.index') }}"><i class="bi bi-people-arrow-right me-2"></i>Retailers → Customers<x-nav-badge for="picking.retailer-to-customers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('picking-lists.*') }}" href="{{ route('picking-lists.index') }}"><i class="bi bi-list-ul me-2"></i>All Picking Lists<x-nav-badge for="picking.all" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Returns -->
                    @can('returns.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#returnsMenu" role="button" aria-expanded="{{ $returnsOpen ? 'true' : 'false' }}" aria-controls="returnsMenu">
                            <span><i class="bi bi-arrow-return-left me-2"></i>Returns<x-nav-badge for="returns" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $returnsOpen ? ' show' : '' }}" id="returnsMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $returnActive(null) }}" href="{{ route('returns.index') }}"><i class="bi bi-list me-2"></i>All Returns<x-nav-badge for="returns.all" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('credit-notes.*') }}" href="{{ route('credit-notes.index') }}"><i class="bi bi-receipt me-2"></i>Credit Notes<x-nav-badge for="returns.credit-notes" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('debit-notes.*') }}" href="{{ route('debit-notes.index') }}"><i class="bi bi-receipt me-2"></i>Debit Notes<x-nav-badge for="returns.debit-notes" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('customer_return') }}" href="{{ route('returns.index', ['type' => 'customer_return']) }}"><i class="bi bi-arrow-return-left text-danger me-2"></i>Customer Returns<x-nav-badge for="returns.customer_return" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('vendor_return') }}" href="{{ route('returns.index', ['type' => 'vendor_return']) }}"><i class="bi bi-arrow-return-right text-info me-2"></i>Vendor Returns<x-nav-badge for="returns.vendor_return" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('retailer_return') }}" href="{{ route('returns.index', ['type' => 'retailer_return']) }}"><i class="bi bi-arrow-return-left text-warning me-2"></i>Retailer Returns<x-nav-badge for="returns.retailer_return" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Stock Management -->
                    @can('stock-management.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#stockMenu" role="button" aria-expanded="{{ $stockOpen ? 'true' : 'false' }}" aria-controls="stockMenu">
                            <span><i class="bi bi-boxes me-2"></i>Stock Management<x-nav-badge for="stock" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $stockOpen ? ' show' : '' }}" id="stockMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('stock-management.*') }}" href="{{ route('stock-management.index') }}"><i class="bi bi-boxes me-2"></i>Stock Management<x-nav-badge for="stock.stock-management" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('stock-locations.*') }}" href="{{ route('stock-locations.index') }}"><i class="bi bi-geo-alt me-2"></i>Stock Locations<x-nav-badge for="stock.stock-locations" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('picking.transaction-flow') }}" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 me-2"></i>Transaction Flow<x-nav-badge for="stock.transaction-flow" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Sales Order -->
                    @can('orders.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#salesOrderMenu" role="button" aria-expanded="{{ $salesOrderOpen ? 'true' : 'false' }}" aria-controls="salesOrderMenu">
                            <span><i class="bi bi-cart me-2"></i>Sales Order<x-nav-badge for="sales" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $salesOrderOpen ? ' show' : '' }}" id="salesOrderMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('orders.*') }}" href="{{ route('orders.index') }}"><i class="bi bi-cart me-2"></i>Orders<x-nav-badge for="sales.orders" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('invoices.*') }}" href="{{ route('invoices.index') }}"><i class="bi bi-receipt me-2"></i>Invoices<x-nav-badge for="sales.invoices" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('payments.*') }}" href="{{ route('payments.index') }}"><i class="bi bi-credit-card me-2"></i>Invoice Payments<x-nav-badge for="sales.payments" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Accounting -->
                    @can('accounting.view')
                    <li class="nav-item mt-3">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#accountingMenu" role="button" aria-expanded="{{ $accountingOpen ? 'true' : 'false' }}" aria-controls="accountingMenu">
                            <span><i class="bi bi-journal-bookmark me-2"></i>Accounting<x-nav-badge for="accounting" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $accountingOpen ? ' show' : '' }}" id="accountingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('accounting.chart-of-accounts', 'accounting.chart-of-accounts.*') }}" href="{{ route('accounting.chart-of-accounts') }}"><i class="bi bi-journal me-2"></i>Chart of Accounts<x-nav-badge for="accounting.chart-of-accounts" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.trial-balance') }}" href="{{ route('reports.trial-balance') }}"><i class="bi bi-calculator me-2"></i>Trial Balance</a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.income-statement') }}" href="{{ route('reports.income-statement') }}"><i class="bi bi-clipboard-data me-2"></i>Income Statement</a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.balance-sheet') }}" href="{{ route('reports.balance-sheet') }}"><i class="bi bi-columns-gap me-2"></i>Balance Sheet</a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.cash-flow') }}" href="{{ route('reports.cash-flow') }}"><i class="bi bi-cash-stack me-2"></i>Cash Flow Statement</a></li>
                                <li><hr></li>
                                <li><a class="nav-link px-3{{ $navActive('journal-entries.*') }}" href="{{ route('journal-entries.index') }}"><i class="bi bi-journal-text me-2"></i>Journal Entries<x-nav-badge for="accounting.journal-entries" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('audit-logs.*') }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check me-2"></i>Audit Trail<x-nav-badge for="accounting.audit-logs" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Reports -->
                    @can('reports.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('reports.daily-profit') }}" href="{{ route('reports.daily-profit') }}"><i class="bi bi-file-earmark-bar-graph me-2"></i>Daily Profit</a>
                    </li>
                    @endcan

                    <!-- What each action triggers -->
                    @can('reference.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('reference.action-effects') }}" href="{{ route('reference.action-effects') }}"><i class="bi bi-lightning-charge me-2"></i>Action Effects</a>
                    </li>
                    @endcan

                    <!-- Administration -->
                    @can('users.view')
                    <li class="nav-item mt-3">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#adminMenu" role="button" aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="adminMenu">
                            <span><i class="bi bi-gear me-2"></i>Administration<x-nav-badge for="admin" /></span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse{{ $adminOpen ? ' show' : '' }}" id="adminMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('users.*') }}" href="{{ route('users.index') }}"><i class="bi bi-person-badge me-2"></i>Users<x-nav-badge for="admin.users" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan
                </ul>
            </nav>
        </div>

        {{-- Signed-in user + sign out, pinned below the navigation --}}
        @auth
        <div class="sidebar-user border-top border-secondary p-3">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <i class="bi bi-person-circle fs-5"></i>
                    <div class="text-truncate">
                        <div class="small fw-semibold text-truncate">{{ auth()->user()->name }}</div>
                        <div class="text-white-50" style="font-size: .75rem;">{{ auth()->user()->email }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light" title="Sign out">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </div>
    <!-- END: Sidebar Navigation Layout -->

    <div class="container-fluid main-content">
        @php
            // One action, one notice. When the triggered-effects banner is
            // showing it already carries the success message as its headline,
            // so a toast on top of it is the same words twice. Actions that
            // raise no effects - deletes, mostly - have no banner and keep it.
            $effectsBanner = is_array($triggeredEffects ?? null) && ! empty($triggeredEffects['rows']);
        @endphp

        <!-- Toast notifications -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
            @if(session('success') && ! $effectsBanner)
                <div class="toast align-items-center text-white bg-success border-0" role="alert" data-bs-delay="3000">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="toast align-items-center text-white bg-danger border-0" role="alert" data-bs-delay="4000">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif
        </div>

        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- What the action that brought the reader here set off elsewhere. The
             sidebar badges say where to look; this says what landed there. --}}
        @if($effectsBanner)
            <x-triggered-effects :panel="$triggeredEffects" />
        @endif

        {{-- Page title/actions render above the panel, on the page background --}}
        @yield('page-header')

        <div class="page-panel">
            @yield('content')
        </div>
    </div>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <span>&copy; {{ date('Y') }} Sales Order System</span>
            <span class="text-primary-custom">Premium Business Solution</span>
        </div>
    </footer>

    <!-- Scripts -->
    <!-- jQuery already loaded in head -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Temporarily disabled to debug syntax error
    <script src="{{ asset('js/unified-search.js') }}"></script>
    -->
    
    <!-- Global Error Handler -->
    <script>
        // Enhanced unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(event) {
            const reason = event.reason;
            
            // Check if this is a browser extension message channel error
            if (reason && reason.message && (
                reason.message.includes('message channel closed') ||
                reason.message.includes('asynchronous response') ||
                reason.message.includes('listener indicated')
            )) {
                console.log('Browser extension message channel error detected, safely ignoring...');
                event.preventDefault();
                return false;
            }
            
            // Check if this is a network-related error that we can safely ignore
            if (reason && reason.message && (
                reason.message.includes('Failed to fetch') ||
                reason.message.includes('NetworkError') ||
                reason.message.includes('ERR_INTERNET_DISCONNECTED')
            )) {
                console.log('Network error detected, safely ignoring...');
                event.preventDefault();
                return false;
            }
            
            // Log other unhandled promise rejections for debugging
            console.error('Unhandled promise rejection:', reason);
            console.error('Promise rejection details:', {
                message: reason?.message,
                stack: reason?.stack,
                name: reason?.name
            });
            
            // Prevent the default browser behavior for unhandled rejections
            event.preventDefault();
        });
        
        // Enhanced global error handler
        window.addEventListener('error', function(event) {
            const error = event.error;
            
            // Check if this is a browser extension related error
            if (error && error.message && (
                error.message.includes('message channel closed') ||
                error.message.includes('asynchronous response') ||
                error.message.includes('Extension context invalidated')
            )) {
                console.log('Browser extension error detected, safely ignoring...');
                event.preventDefault();
                return false;
            }
            
            // Log other errors for debugging
            console.error('Global error:', error);
            console.error('Error details:', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: error
            });
        });
        
        // Handle beforeunload to clean up any pending operations
        window.addEventListener('beforeunload', function(event) {
            // Cancel any pending fetch requests
            if (window.activeRequests) {
                window.activeRequests.forEach(controller => {
                    if (controller && typeof controller.abort === 'function') {
                        controller.abort();
                    }
                });
            }
        });
        
        // Track active fetch requests for cleanup
        window.activeRequests = new Set();
        
        // Override fetch to track requests
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const controller = new AbortController();
            window.activeRequests.add(controller);
            
            const promise = originalFetch(...args, { signal: controller.signal })
                .finally(() => {
                    window.activeRequests.delete(controller);
                });
            
            return promise;
        };
        
        // Simple test to verify JavaScript is working
        console.log('Layout scripts loaded successfully');
    </script>
    
    <!-- Global DataTables Configuration -->
    <!-- Temporarily disabled to debug syntax error
    <script>
        // Wait for jQuery to be fully loaded before configuring DataTables
        function configureDataTables() {
            try {
                // Check if jQuery is available
                if (typeof jQuery !== 'undefined' && jQuery && jQuery.fn && jQuery.fn.DataTable) {
                    jQuery.extend(true, jQuery.fn.dataTable.defaults, {
                        // Suppress warnings
                        deferRender: true,
                        processing: true,
                        // Better error handling
                        language: {
                            processing: "Processing...",
                            search: "Search:",
                            lengthMenu: "Show _MENU_ entries",
                            info: "Showing _START_ to _END_ of _TOTAL_ entries",
                            infoEmpty: "Showing 0 to 0 of 0 entries",
                            infoFiltered: "(filtered from _MAX_ total entries)",
                            infoPostFix: "",
                            loadingRecords: "Loading...",
                            zeroRecords: "No matching records found",
                            emptyTable: "No data available in table",
                            paginate: {
                                first: "First",
                                previous: "Previous",
                                next: "Next",
                                last: "Last"
                            },
                            aria: {
                                sortAscending: ": activate to sort column ascending",
                                sortDescending: ": activate to sort column descending"
                            }
                        }
                    });
                    console.log('DataTables configured successfully');
                } else {
                    // Retry after a short delay if jQuery is not ready
                    setTimeout(configureDataTables, 100);
                }
            } catch (error) {
                console.error('Error configuring DataTables:', error);
            }
        }

        // Try to configure DataTables when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', configureDataTables);
        } else {
            configureDataTables();
        }
        
        // Also try when window is loaded to ensure all scripts are ready
        window.addEventListener('load', configureDataTables);
    </script>
    -->
    
    {{-- Page-specific scripts declared via @section('scripts') in child views --}}
    @yield('scripts')
    {{-- Page-specific scripts pushed via @push('scripts') --}}
    @stack('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Sidebar active state and menu expansion are rendered server-side in the
                // blade above, matched on route names rather than guessed from the URL.

                // Automatically show bootstrap toasts if present
                const toastElList = [].slice.call(document.querySelectorAll('.toast'));
                toastElList.forEach(function (toastEl) {
                    try {
                        const toast = new bootstrap.Toast(toastEl);
                        toast.show();
                    } catch (toastError) {
                        console.warn('Error showing toast:', toastError);
                    }
                });
            } catch (error) {
                console.error('Error in DOMContentLoaded:', error);
            }
        });
    </script>
</body>
</html> 

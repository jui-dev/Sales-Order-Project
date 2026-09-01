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

        /* The sidebar width is a variable because three things move together when
           the rail opens and closes: the panel itself, the body padding that keeps
           the content clear of it, and the footer that inherits that padding. */
        :root {
            --sidebar-width: 250px;
            --sidebar-rail-width: 68px;
        }

        /* ===== Sidebar Navigation ===== */
        .sidebar-nav {
            /* Bootstrap sizes .offcanvas-start from this custom property (default 400px).
               Both read the variable so the drawer and the desktop panel cannot drift
               apart; only the desktop query below ever changes what it holds. */
            --bs-offcanvas-width: var(--sidebar-width);
            width: var(--sidebar-width);
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

        /* .nav-effect-badge carries margin-left: auto, which parks it at the row
           edge on a plain link. On a group toggle it belongs beside the words
           instead, and the caret takes the auto margin so it still sits hard
           against the edge on groups that carry no badge at all. */
        .sidebar-nav .nav-link[data-bs-toggle="collapse"] .nav-effect-badge {
            margin-left: 0.5rem;
        }
        .sidebar-nav .nav-caret {
            margin-left: auto;
        }
        /* The rail hangs the badge off the icon, so the link is its containing block. */
        .sidebar-nav .nav-link {
            position: relative;
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
            /* Bootstrap turns this into a flex row at >=lg, which shrink-wraps the
               nav to its content: harmless at full width, but in the rail it
               collapses every link to the width of one icon. */
            flex-direction: column;
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
                /* .offcanvas.offcanvas-start is two classes and beats .sidebar-nav on
                   width, so the rail has to move Bootstrap's own knob as well. */
                --bs-offcanvas-width: var(--sidebar-width);
                width: var(--sidebar-width);
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
                padding-left: var(--sidebar-width); /* sidebar is fixed / out of flow */
                padding-top: 0;      /* overrides the old header offset in custom.css */
            }
            /* footer needs no margin-left: body padding-left already shifts it */
            .main-content {
                margin-left: 0;
                padding-top: 1.5rem; /* overrides the old 80px header clearance */
            }

            /* === Collapsed rail ===
               The class sits on <html> rather than <body> because the script that
               restores it runs in <head>, before <body> exists. Everything here is
               inside the desktop query: under 992px the sidebar is an offcanvas
               drawer, which has nowhere to shrink to and no toggle to shrink it. */
            html.sidebar-collapsed {
                --sidebar-width: var(--sidebar-rail-width);
            }
            html.sidebar-collapsed .sidebar-nav .nav-label,
            html.sidebar-collapsed .sidebar-nav .nav-caret,
            html.sidebar-collapsed .sidebar-brand .navbar-brand,
            html.sidebar-collapsed .sidebar-user .text-truncate {
                display: none;
            }
            /* A sub-menu has nowhere to go at 68px, so the rail does not open one at
               all; the script widens the sidebar first and opens it after. */
            html.sidebar-collapsed .sidebar-nav .collapse {
                display: none !important;
            }
            html.sidebar-collapsed .sidebar-nav .nav-link {
                justify-content: center;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            /* Undo the me-2 that spaces the icon off a label there is no longer */
            html.sidebar-collapsed .sidebar-nav .nav-icon {
                margin-right: 0 !important;
            }
            /* The count still has to register with no room to print it, so the badge
               becomes a dot on the icon. Its own title attribute keeps the number. */
            html.sidebar-collapsed .sidebar-nav .nav-effect-badge {
                position: absolute;
                top: 4px;
                right: 12px;
                width: 9px;
                height: 9px;
                min-width: 0;
                margin: 0;
                padding: 0;
                font-size: 0;
                line-height: 0;
            }
            html.sidebar-collapsed .sidebar-brand,
            html.sidebar-collapsed .sidebar-user > div {
                justify-content: center;
            }
            html.sidebar-collapsed .sidebar-toggle i {
                transform: rotate(180deg);
            }
            /* Named properties, not `all`: custom.css puts a blanket transition on
               .nav-link, which is the same trap the colour rule above sidesteps. */
            .sidebar-nav {
                transition: width 0.2s ease;
            }
            body {
                transition: padding-left 0.2s ease;
            }
            @media (prefers-reduced-motion: reduce) {
                .sidebar-nav,
                body {
                    transition: none;
                }
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

    <script>
        // Restore the rail before the first paint. Deferring this to DOMContentLoaded
        // flashes a full-width sidebar on every page load, and <body> does not exist
        // yet, so the class goes on <html> — which is what the CSS above keys off.
        try {
            if (localStorage.getItem('sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) { /* storage blocked: the sidebar simply starts expanded */ }
    </script>
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
            {{-- Desktop rail toggle. The close button beside it is d-lg-none and this
                 one is d-none d-lg-inline-flex, so the header shows one or the other. --}}
            <button type="button" class="btn btn-sm text-white border-0 p-1 d-none d-lg-inline-flex sidebar-toggle"
                    id="sidebarToggle" aria-controls="sidebar" aria-expanded="true"
                    title="Collapse sidebar" aria-label="Collapse sidebar">
                <i class="bi bi-chevron-double-left"></i>
            </button>
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

                    $catalogOpen = request()->routeIs('products.*', 'product-pricing.*');
                    $procurementOpen = request()->routeIs('purchase-orders.*', 'supplies.*', 'grns.*', 'supplier-bills.*', 'supplier-bill-payments.*');
                    $pickingOpen = request()->routeIs('stock-transfers.*', 'warehouse-to-customer-picking.*', 'retailer-to-customer-picking.*', 'picking-lists.*');
                    $returnsOpen = request()->routeIs('returns.*', 'credit-notes.*', 'debit-notes.*');
                    $stockOpen = request()->routeIs('stock-management.*', 'stock-locations.*', 'picking.transaction-flow');
                    $salesOrderOpen = request()->routeIs('orders.*', 'invoices.*', 'payments.*');
                    $accountingOpen = request()->routeIs('accounting.*', 'journal-entries.*', 'audit-logs.*', 'reports.trial-balance', 'reports.income-statement', 'reports.balance-sheet', 'reports.cash-flow');
                    $adminOpen = request()->routeIs('users.*');
                @endphp
                {{-- Every link is icon + .nav-label + badge, in that order and at that
                     depth. The rail hides .nav-label and turns the badge into a dot on
                     the icon, and neither can reach inside the other to do it. --}}
                <ul class="navbar-nav flex-column" id="sidebarAccordion">
                    <!-- Dashboard -->
                    @can('dashboard.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('dashboard') }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 nav-icon me-2"></i><span class="nav-label">Dashboard</span>
                        </a>
                    </li>
                    @endcan

                    <!-- Catalog -->
                    @can('products.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#catalogMenu" role="button" aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}" aria-controls="catalogMenu">
                            <i class="bi bi-journals nav-icon me-2"></i><span class="nav-label">Catalog</span><x-nav-badge for="catalog" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $catalogOpen ? ' show' : '' }}" id="catalogMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('products.*') }}" href="{{ route('products.index') }}"><i class="bi bi-box nav-icon me-2"></i><span class="nav-label">Products</span><x-nav-badge for="catalog.products" /></a></li>
                                @can('product-pricing.view')
                                <li><a class="nav-link px-3{{ $navActive('product-pricing.*') }}" href="{{ route('product-pricing.index') }}"><i class="bi bi-tags nav-icon me-2"></i><span class="nav-label">Product Pricing</span><x-nav-badge for="catalog.product-pricing" /></a></li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Master Data -->
                    @can('vendors.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('vendors.*') }}" href="{{ route('vendors.index') }}"><i class="bi bi-building nav-icon me-2"></i><span class="nav-label">Vendors</span><x-nav-badge for="vendors" /></a>
                    </li>
                    @endcan
                    @can('customers.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('customers.*') }}" href="{{ route('customers.index') }}"><i class="bi bi-people nav-icon me-2"></i><span class="nav-label">Customers</span><x-nav-badge for="customers" /></a>
                    </li>
                    @endcan

                    <!-- Procurement -->
                    @can('purchase-orders.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#procurementMenu" role="button" aria-expanded="{{ $procurementOpen ? 'true' : 'false' }}" aria-controls="procurementMenu">
                            <i class="bi bi-cart-check nav-icon me-2"></i><span class="nav-label">Procurement</span><x-nav-badge for="procurement" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $procurementOpen ? ' show' : '' }}" id="procurementMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('purchase-orders.*') }}" href="{{ route('purchase-orders.index') }}"><i class="bi bi-clipboard-check nav-icon me-2"></i><span class="nav-label">Purchase Orders</span><x-nav-badge for="procurement.purchase-orders" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplies.*') }}" href="{{ route('supplies.index') }}"><i class="bi bi-truck nav-icon me-2"></i><span class="nav-label">Supplies</span><x-nav-badge for="procurement.supplies" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('grns.*') }}" href="{{ route('grns.index') }}"><i class="bi bi-receipt nav-icon me-2"></i><span class="nav-label">Good Receipt Notes (GRNs)</span><x-nav-badge for="procurement.grns" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplier-bills.*') }}" href="{{ route('supplier-bills.index') }}"><i class="bi bi-file-earmark-text nav-icon me-2"></i><span class="nav-label">Supplier Bills</span><x-nav-badge for="procurement.supplier-bills" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('supplier-bill-payments.*') }}" href="{{ route('supplier-bill-payments.index') }}"><i class="bi bi-credit-card nav-icon me-2"></i><span class="nav-label">Supplier Bills Payment</span><x-nav-badge for="procurement.supplier-bill-payments" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Picking & Transfers -->
                    @can('picking.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#pickingMenu" role="button" aria-expanded="{{ $pickingOpen ? 'true' : 'false' }}" aria-controls="pickingMenu">
                            <i class="bi bi-list-check nav-icon me-2"></i><span class="nav-label">Picking &amp; Transfers</span><x-nav-badge for="picking" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $pickingOpen ? ' show' : '' }}" id="pickingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('stock-transfers.*') }}" href="{{ route('stock-transfers.warehouse-to-retailer') }}"><i class="bi bi-building-arrow-right nav-icon me-2"></i><span class="nav-label">Warehouse → Retailers</span><x-nav-badge for="picking.warehouse-to-retailers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('warehouse-to-customer-picking.*') }}" href="{{ route('warehouse-to-customer-picking.index') }}"><i class="bi bi-house-arrow-right nav-icon me-2"></i><span class="nav-label">Warehouse → Customers</span><x-nav-badge for="picking.warehouse-to-customers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('retailer-to-customer-picking.*') }}" href="{{ route('retailer-to-customer-picking.index') }}"><i class="bi bi-people-arrow-right nav-icon me-2"></i><span class="nav-label">Retailers → Customers</span><x-nav-badge for="picking.retailer-to-customers" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('picking-lists.*') }}" href="{{ route('picking-lists.index') }}"><i class="bi bi-list-ul nav-icon me-2"></i><span class="nav-label">All Picking Lists</span><x-nav-badge for="picking.all" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Returns -->
                    @can('returns.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#returnsMenu" role="button" aria-expanded="{{ $returnsOpen ? 'true' : 'false' }}" aria-controls="returnsMenu">
                            <i class="bi bi-arrow-return-left nav-icon me-2"></i><span class="nav-label">Returns</span><x-nav-badge for="returns" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $returnsOpen ? ' show' : '' }}" id="returnsMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $returnActive(null) }}" href="{{ route('returns.index') }}"><i class="bi bi-list nav-icon me-2"></i><span class="nav-label">All Returns</span><x-nav-badge for="returns.all" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('credit-notes.*') }}" href="{{ route('credit-notes.index') }}"><i class="bi bi-receipt nav-icon me-2"></i><span class="nav-label">Credit Notes</span><x-nav-badge for="returns.credit-notes" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('debit-notes.*') }}" href="{{ route('debit-notes.index') }}"><i class="bi bi-receipt nav-icon me-2"></i><span class="nav-label">Debit Notes</span><x-nav-badge for="returns.debit-notes" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('customer_return') }}" href="{{ route('returns.index', ['type' => 'customer_return']) }}"><i class="bi bi-arrow-return-left text-danger nav-icon me-2"></i><span class="nav-label">Customer Returns</span><x-nav-badge for="returns.customer_return" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('vendor_return') }}" href="{{ route('returns.index', ['type' => 'vendor_return']) }}"><i class="bi bi-arrow-return-right text-info nav-icon me-2"></i><span class="nav-label">Vendor Returns</span><x-nav-badge for="returns.vendor_return" /></a></li>
                                <li><a class="nav-link px-3{{ $returnActive('retailer_return') }}" href="{{ route('returns.index', ['type' => 'retailer_return']) }}"><i class="bi bi-arrow-return-left text-warning nav-icon me-2"></i><span class="nav-label">Retailer Returns</span><x-nav-badge for="returns.retailer_return" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Stock Management -->
                    @can('stock-management.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#stockMenu" role="button" aria-expanded="{{ $stockOpen ? 'true' : 'false' }}" aria-controls="stockMenu">
                            <i class="bi bi-boxes nav-icon me-2"></i><span class="nav-label">Stock Management</span><x-nav-badge for="stock" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $stockOpen ? ' show' : '' }}" id="stockMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('stock-management.*') }}" href="{{ route('stock-management.index') }}"><i class="bi bi-boxes nav-icon me-2"></i><span class="nav-label">Stock Management</span><x-nav-badge for="stock.stock-management" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('stock-locations.*') }}" href="{{ route('stock-locations.index') }}"><i class="bi bi-geo-alt nav-icon me-2"></i><span class="nav-label">Stock Locations</span><x-nav-badge for="stock.stock-locations" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('picking.transaction-flow') }}" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 nav-icon me-2"></i><span class="nav-label">Transaction Flow</span><x-nav-badge for="stock.transaction-flow" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Sales Order -->
                    @can('orders.view')
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#salesOrderMenu" role="button" aria-expanded="{{ $salesOrderOpen ? 'true' : 'false' }}" aria-controls="salesOrderMenu">
                            <i class="bi bi-cart nav-icon me-2"></i><span class="nav-label">Sales Order</span><x-nav-badge for="sales" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $salesOrderOpen ? ' show' : '' }}" id="salesOrderMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('orders.*') }}" href="{{ route('orders.index') }}"><i class="bi bi-cart nav-icon me-2"></i><span class="nav-label">Orders</span><x-nav-badge for="sales.orders" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('invoices.*') }}" href="{{ route('invoices.index') }}"><i class="bi bi-receipt nav-icon me-2"></i><span class="nav-label">Invoices</span><x-nav-badge for="sales.invoices" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('payments.*') }}" href="{{ route('payments.index') }}"><i class="bi bi-credit-card nav-icon me-2"></i><span class="nav-label">Invoice Payments</span><x-nav-badge for="sales.payments" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Accounting -->
                    @can('accounting.view')
                    <li class="nav-item mt-3">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#accountingMenu" role="button" aria-expanded="{{ $accountingOpen ? 'true' : 'false' }}" aria-controls="accountingMenu">
                            <i class="bi bi-journal-bookmark nav-icon me-2"></i><span class="nav-label">Accounting</span><x-nav-badge for="accounting" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $accountingOpen ? ' show' : '' }}" id="accountingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('accounting.chart-of-accounts', 'accounting.chart-of-accounts.*') }}" href="{{ route('accounting.chart-of-accounts') }}"><i class="bi bi-journal nav-icon me-2"></i><span class="nav-label">Chart of Accounts</span><x-nav-badge for="accounting.chart-of-accounts" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.trial-balance') }}" href="{{ route('reports.trial-balance') }}"><i class="bi bi-calculator nav-icon me-2"></i><span class="nav-label">Trial Balance</span></a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.income-statement') }}" href="{{ route('reports.income-statement') }}"><i class="bi bi-clipboard-data nav-icon me-2"></i><span class="nav-label">Income Statement</span></a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.balance-sheet') }}" href="{{ route('reports.balance-sheet') }}"><i class="bi bi-columns-gap nav-icon me-2"></i><span class="nav-label">Balance Sheet</span></a></li>
                                <li><a class="nav-link px-3{{ $navActive('reports.cash-flow') }}" href="{{ route('reports.cash-flow') }}"><i class="bi bi-cash-stack nav-icon me-2"></i><span class="nav-label">Cash Flow Statement</span></a></li>
                                <li><a class="nav-link px-3{{ $navActive('accounting.health') }}" href="{{ route('accounting.health') }}"><i class="bi bi-clipboard2-pulse nav-icon me-2"></i><span class="nav-label">Accounting Health</span></a></li>
                                <li><hr></li>
                                <li><a class="nav-link px-3{{ $navActive('journal-entries.*') }}" href="{{ route('journal-entries.index') }}"><i class="bi bi-journal-text nav-icon me-2"></i><span class="nav-label">Journal Entries</span><x-nav-badge for="accounting.journal-entries" /></a></li>
                                <li><a class="nav-link px-3{{ $navActive('audit-logs.*') }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check nav-icon me-2"></i><span class="nav-label">Audit Trail</span><x-nav-badge for="accounting.audit-logs" /></a></li>
                            </ul>
                        </div>
                    </li>
                    @endcan

                    <!-- Reports -->
                    @can('reports.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('reports.daily-profit') }}" href="{{ route('reports.daily-profit') }}"><i class="bi bi-file-earmark-bar-graph nav-icon me-2"></i><span class="nav-label">Daily Profit</span></a>
                    </li>
                    @endcan

                    <!-- What each action triggers -->
                    @can('reference.view')
                    <li class="nav-item">
                        <a class="nav-link px-3{{ $navActive('reference.action-effects') }}" href="{{ route('reference.action-effects') }}"><i class="bi bi-lightning-charge nav-icon me-2"></i><span class="nav-label">Action Effects</span></a>
                    </li>
                    @endcan

                    <!-- Administration -->
                    @can('users.view')
                    <li class="nav-item mt-3">
                        <a class="nav-link px-3 d-flex align-items-center" data-bs-toggle="collapse" href="#adminMenu" role="button" aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="adminMenu">
                            <i class="bi bi-gear nav-icon me-2"></i><span class="nav-label">Administration</span><x-nav-badge for="admin" />
                            <i class="bi bi-chevron-down small nav-caret"></i>
                        </a>
                        <div class="collapse{{ $adminOpen ? ' show' : '' }}" id="adminMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3{{ $navActive('users.*') }}" href="{{ route('users.index') }}"><i class="bi bi-person-badge nav-icon me-2"></i><span class="nav-label">Users</span><x-nav-badge for="admin.users" /></a></li>
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
            // The effects notice is behind a button now, so its headline is not
            // on screen until the reader opens it. The success toast therefore
            // always shows: it is the only thing saying the action worked.
            $hasEffects = is_array($triggeredEffects ?? null) && ! empty($triggeredEffects['rows']);
        @endphp

        <!-- Toast notifications -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
            @if(session('success'))
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

        {{-- Page title/actions render above the panel, on the page background --}}
        <div id="pageHeader">
            @yield('page-header')
        </div>

        {{-- What the action that brought the reader here set off elsewhere. The
             sidebar badges say where to look; this says what landed there. The
             script at the foot of this file lifts the button into the header
             above, alongside whatever actions the page offers. --}}
        @if($hasEffects)
            <x-triggered-effects :panel="$triggeredEffects" />
        @endif

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
                // Its width is the one piece of sidebar state that is not: a reader
                // picks it, so it is remembered per browser rather than per request.
                (function () {
                    const root    = document.documentElement;
                    const toggle  = document.getElementById('sidebarToggle');
                    const sidebar = document.getElementById('sidebar');
                    if (!toggle || !sidebar) return;

                    // Under 992px the sidebar is an offcanvas drawer at full width, so a
                    // rail remembered from a wider screen must not strip its labels.
                    const desktop = window.matchMedia('(min-width: 992px)');
                    const isRail = function () {
                        return root.classList.contains('sidebar-collapsed') && desktop.matches;
                    };

                    function sync() {
                        const rail = isRail();
                        toggle.setAttribute('aria-expanded', rail ? 'false' : 'true');
                        toggle.setAttribute('title', rail ? 'Expand sidebar' : 'Collapse sidebar');
                        toggle.setAttribute('aria-label', toggle.getAttribute('title'));

                        // The rail hides the words, so the name reaches the reader as a
                        // native tooltip: no library, and nothing the sidebar's own
                        // overflow can clip the way it would clip a flyout.
                        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
                            const label = link.querySelector('.nav-label');
                            if (!label) return;
                            if (rail) link.setAttribute('title', label.textContent.trim());
                            else link.removeAttribute('title');
                        });
                    }

                    function setCollapsed(state) {
                        root.classList.toggle('sidebar-collapsed', state);
                        try {
                            localStorage.setItem('sidebar-collapsed', state ? '1' : '0');
                        } catch (e) { /* storage blocked: the choice lasts this page only */ }
                        sync();
                    }

                    toggle.addEventListener('click', function () {
                        setCollapsed(!root.classList.contains('sidebar-collapsed'));
                    });

                    // A group clicked in the rail widens the sidebar and then opens, rather
                    // than toggling a panel that is display:none. Bootstrap registers its
                    // own collapse data-api on document in the CAPTURE phase, so a listener
                    // on the link - capturing or not - always runs second and cannot call it
                    // off. window is the one point upstream of document, which is what lets
                    // this take the click before Bootstrap toggles a panel nobody can see.
                    window.addEventListener('click', function (event) {
                        if (!isRail() || !(event.target instanceof Element)) return;
                        const link = event.target.closest('.nav-link[data-bs-toggle="collapse"]');
                        if (!link || !sidebar.contains(link)) return;
                        event.preventDefault();
                        event.stopPropagation();
                        setCollapsed(false);
                        const panel = document.querySelector(link.getAttribute('href'));
                        // toggle: false because the constructor acts on its config the first
                        // time it runs: left at the default it toggles, which would close the
                        // very group the reader asked for whenever the route rendered it open.
                        if (panel) bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
                    }, true);

                    desktop.addEventListener('change', sync);
                    sync();
                })();


                // The "This Also Triggered" notice is a header action rather
                // than a banner: park its button on the same line as whatever
                // actions the page already offers. Pages lay their headers out
                // differently, so the page's own buttons are the landmark - the
                // notice goes in front of the first one it finds.
                (function () {
                    const launcher = document.getElementById('triggeredEffectsLauncher');
                    const header   = document.getElementById('pageHeader');
                    const button   = document.getElementById('triggeredEffectsBtn');
                    if (!launcher || !header || !button) return;

                    const pageButtons = header.querySelectorAll('.btn');
                    if (pageButtons.length) {
                        // The group holding the page's actions, found via the
                        // last button because that is the primary action and so
                        // is always inside the action row rather than the title.
                        let group = pageButtons[pageButtons.length - 1].parentElement;
                        // A lone button wrapped in its own form is not the row;
                        // the row is what holds that form.
                        if (group.tagName === 'FORM') group = group.parentElement;
                        const first = Array.from(pageButtons).find(function (btn) {
                            return group.contains(btn);
                        }) || pageButtons[pageButtons.length - 1];

                        // A button may sit inside a form; insert before the
                        // child of the group that actually contains it.
                        let anchor = first;
                        while (anchor.parentElement && anchor.parentElement !== group) {
                            anchor = anchor.parentElement;
                        }
                        group.insertBefore(button, anchor);
                    } else {
                        // No page actions to sit beside: hang the button off the
                        // header's own row so it still reads as a header action.
                        const row = header.firstElementChild;
                        (row || header).appendChild(button);
                    }

                    launcher.remove();
                })();

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

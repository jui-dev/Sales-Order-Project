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
            width: 250px;
            background: var(--primary);
        }
        .sidebar-nav .nav-link {
            color: var(--light-text);
            padding: 0.5rem 1rem;
        }
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: var(--light-text);
        }
        .sidebar-nav .collapse .nav-link {
            padding-left: 2rem;
            font-size: 0.9rem;
        }
        /* Ensure sidebar is visible and fixed on desktop */
        @media (min-width: 992px) {
            .sidebar-nav {
                position: fixed;
                top: var(--navbar-height);
                left: 0;
                height: 100vh;
                transform: none !important;
                visibility: visible !important;
                overflow-y: auto;
            }
            /* Hide backdrop when sidebar is static */
            .offcanvas-backdrop.show {
                display: none;
            }

            body {
                padding-left: 250px; /* space for sidebar */
            }
            footer {
                margin-left: 250px;
            }
            .main-content {
                margin-left: 0;
            }
        }
        /* Ensure same background when sidebar is fixed on desktop */
        @media (min-width: 992px) {
            .sidebar-nav {
                background: var(--primary) !important;
            }
            .offcanvas-lg.sidebar-nav {
                 background: var(--primary) !important;
            }
        }
        /* Prevent brand text wrapping on very small screens */
        .navbar-brand {
            white-space: nowrap;
        }
        /* === Override: Hide sidebar on desktop & fix layout spacing === */
        @media (min-width: 992px) {
            .sidebar-nav { display: none !important; }
            body { padding-left: 0 !important; }
            /* Ensure navbar spans full width */
            .navbar.fixed-top { width: 100% !important; left: 0 !important; }
            /* Center main content */
            .main-content { margin-left: auto !important; margin-right: auto !important; }
        }

        /* === Sticky footer setup === */
        html, body { height: 100%; }
        body {
            display: flex;
            flex-direction: column;
        }
        .main-content { flex: 1 0 auto; }
        footer {
            flex-shrink: 0;
            margin-left: 0 !important;
            background: #f8f9fa;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- BEGIN: Sidebar Navigation Layout -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <!-- Sidebar toggle: visible only on mobile -->
            <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                <i class="bi bi-list"></i>
            </button>

            <!-- Brand -->
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
                <i class="bi bi-graph-up me-2"></i>Sales Order System
            </a>

            <!-- Top-nav collapse toggle (aligned to the far right on mobile) -->
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="topNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <!-- Inventory Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="inventoryTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-seam me-1"></i>Inventory
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="inventoryTop">
                            <li><a class="dropdown-item" href="{{ route('products.index') }}"><i class="bi bi-box me-1"></i> Products</a></li>
                            <li><a class="dropdown-item" href="{{ route('supplies.index') }}"><i class="bi bi-truck me-1"></i> Supplies</a></li>
                            <li><a class="dropdown-item" href="{{ route('vendors.index') }}"><i class="bi bi-building me-1"></i> Vendors</a></li>
                            <li><a class="dropdown-item" href="{{ route('customers.index') }}"><i class="bi bi-people me-1"></i> Customers</a></li>
                        </ul>
                    </li>

                    <!-- Purchases Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="purchasesTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart-check me-1"></i>Purchases
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="purchasesTop">
                            <li><a class="dropdown-item" href="{{ route('grns.index') }}"><i class="bi bi-receipt me-1"></i> Good Receipt Notes (GRNs)</a></li>
                            <li><a class="dropdown-item" href="{{ route('supplier-bills.index') }}"><i class="bi bi-file-earmark-text me-1"></i> Supplier Bills</a></li>
                            <li><a class="dropdown-item" href="{{ route('supplier-bill-payments.index') }}"><i class="bi bi-credit-card me-1"></i> Supplier Bills Payment</a></li>
                        </ul>
                    </li>

                    <!-- Picking & Transfers Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="pickingTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-list-check me-1"></i>Picking & Transfers
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="pickingTop">
                            <li><a class="dropdown-item" href="{{ route('vendor-to-warehouse-picking.index') }}"><i class="bi bi-truck-arrow-right me-1"></i> Vendors → Warehouse</a></li>
                            <li><a class="dropdown-item" href="{{ route('stock-transfers.warehouse-to-retailer') }}"><i class="bi bi-building-arrow-right me-1"></i> Warehouse → Retailers</a></li>
                            <li><a class="dropdown-item" href="{{ route('warehouse-to-customer-picking.index') }}"><i class="bi bi-house-arrow-right me-1"></i> Warehouse → Customers</a></li>
                            <li><a class="dropdown-item" href="{{ route('retailer-to-customer-picking.index') }}"><i class="bi bi-people-arrow-right me-1"></i> Retailers → Customers</a></li>
                            <li><a class="dropdown-item" href="{{ route('picking-lists.index') }}"><i class="bi bi-list-ul me-1"></i> All Picking Lists</a></li>
                            <li><a class="dropdown-item" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 me-1"></i> Transaction Flow</a></li>
                        </ul>
                    </li>

                    <!-- Returns Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="returnsTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-arrow-return-left me-1"></i>Returns
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="returnsTop">
                            <li><a class="dropdown-item" href="{{ route('returns.index') }}"><i class="bi bi-list me-1"></i> All Returns</a></li>
                            <li><a class="dropdown-item" href="{{ route('credit-notes.index') }}"><i class="bi bi-receipt me-1"></i> Credit Notes</a></li>
                            <li><a class="dropdown-item" href="{{ route('debit-notes.index') }}"><i class="bi bi-receipt me-1"></i> Debit Notes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('returns.index', ['type' => 'customer_return']) }}"><i class="bi bi-arrow-return-left text-danger me-1"></i> Customer Returns</a></li>
                            <li><a class="dropdown-item" href="{{ route('returns.index', ['type' => 'vendor_return']) }}"><i class="bi bi-arrow-return-right text-info me-1"></i> Vendor Returns</a></li>
                            <li><a class="dropdown-item" href="{{ route('returns.index', ['type' => 'retailer_return']) }}"><i class="bi bi-arrow-return-left text-warning me-1"></i> Retailer Returns</a></li>
                        </ul>
                    </li>

                    <!-- Stock Management Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="stockTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-boxes me-1"></i>Stock Management
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="stockTop">
                            <li><a class="dropdown-item" href="{{ route('stock-management.index') }}"><i class="bi bi-boxes me-1"></i> Stock Management</a></li>
                            <li><a class="dropdown-item" href="{{ route('stock-locations.index') }}"><i class="bi bi-geo-alt me-1"></i> Stock Locations</a></li>
                            <li><a class="dropdown-item" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 me-1"></i> Transaction Flow</a></li>
                        </ul>
                    </li>

                    <!-- Sales Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="salesTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cash-stack me-1"></i>Sales
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="salesTop">
                            <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="bi bi-cart me-1"></i> Orders</a></li>
                            <li><a class="dropdown-item" href="{{ route('invoices.index') }}"><i class="bi bi-receipt me-1"></i> Invoices</a></li>
                            <li><a class="dropdown-item" href="{{ route('reports.daily-profit') }}"><i class="bi bi-graph-up me-1"></i> Daily Profit</a></li>
                        </ul>
                    </li>

                    <!-- Accounting Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="accountingTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-journal-bookmark me-1"></i>Accounting
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountingTop">
                            <li><a class="dropdown-item" href="{{ route('accounting.chart-of-accounts') }}"><i class="bi bi-journal me-1"></i> Chart of Accounts</a></li>
                            <li><a class="dropdown-item" href="{{ route('reports.trial-balance') }}"><i class="bi bi-calculator me-1"></i> Trial Balance</a></li>
                            <li><a class="dropdown-item" href="{{ route('reports.income-statement') }}"><i class="bi bi-clipboard-data me-1"></i> Income Statement</a></li>
                            <li><a class="dropdown-item" href="{{ route('reports.balance-sheet') }}"><i class="bi bi-columns-gap me-1"></i> Balance Sheet</a></li>
                            <li><a class="dropdown-item" href="{{ route('reports.cash-flow') }}"><i class="bi bi-cash-stack me-1"></i> Cash Flow Statement</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('journal-entries.index') }}"><i class="bi bi-journal-text me-1"></i> Journal Entries</a></li>
                            <li><a class="dropdown-item" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check me-1"></i> Audit Trail</a></li>
                        </ul>
                    </li>

                    <!-- Reports Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsTop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-file-bar-graph me-1"></i>Reports
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="reportsTop">
                            <li><a class="dropdown-item" href="{{ route('reports.daily-profit') }}">Daily Profit</a></li>
                        </ul>
                    </li>

                    {{-- Profile section removed per latest requirements --}}
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="offcanvas offcanvas-start offcanvas-lg sidebar-nav text-white" tabindex="-1" id="sidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <nav class="navbar-dark">
                <ul class="navbar-nav flex-column" id="sidebarAccordion">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link px-3" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </li>

                    <!-- Inventory -->
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#inventoryMenu" role="button" aria-expanded="false" aria-controls="inventoryMenu">
                            <span><i class="bi bi-box-seam me-2"></i>Inventory</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="inventoryMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('products.index') }}"><i class="bi bi-box me-2"></i>Products</a></li>
                                <li><a class="nav-link px-3" href="{{ route('supplies.index') }}"><i class="bi bi-truck me-2"></i>Supplies</a></li>
                                <li><a class="nav-link px-3" href="{{ route('vendors.index') }}"><i class="bi bi-building me-2"></i>Vendors</a></li>
                                <li><a class="nav-link px-3" href="{{ route('customers.index') }}"><i class="bi bi-people me-2"></i>Customers</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Purchases -->
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#purchasesMenu" role="button" aria-expanded="false" aria-controls="purchasesMenu">
                            <span><i class="bi bi-cart-check me-2"></i>Purchases</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="purchasesMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('grns.index') }}"><i class="bi bi-receipt me-2"></i>Good Receipt Notes (GRNs)</a></li>
                                <li><a class="nav-link px-3" href="{{ route('supplier-bills.index') }}"><i class="bi bi-file-earmark-text me-2"></i>Supplier Bills</a></li>
                                <li><a class="nav-link px-3" href="{{ route('supplier-bill-payments.index') }}"><i class="bi bi-credit-card me-2"></i>Supplier Bills Payment</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Picking & Transfers -->
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#pickingMenu" role="button" aria-expanded="false" aria-controls="pickingMenu">
                            <span><i class="bi bi-list-check me-2"></i>Picking & Transfers</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="pickingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('vendor-to-warehouse-picking.index') }}"><i class="bi bi-truck-arrow-right me-2"></i>Vendors → Warehouse</a></li>
                                <li><a class="nav-link px-3" href="{{ route('stock-transfers.warehouse-to-retailer') }}"><i class="bi bi-building-arrow-right me-2"></i>Warehouse → Retailers</a></li>
                                <li><a class="nav-link px-3" href="{{ route('warehouse-to-customer-picking.index') }}"><i class="bi bi-house-arrow-right me-2"></i>Warehouse → Customers</a></li>
                                <li><a class="nav-link px-3" href="{{ route('retailer-to-customer-picking.index') }}"><i class="bi bi-people-arrow-right me-2"></i>Retailers → Customers</a></li>
                                <li><a class="nav-link px-3" href="{{ route('picking-lists.index') }}"><i class="bi bi-list-ul me-2"></i>All Picking Lists</a></li>
                                <li><a class="nav-link px-3" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 me-2"></i>Transaction Flow</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Returns -->
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#returnsMenu" role="button" aria-expanded="false" aria-controls="returnsMenu">
                            <span><i class="bi bi-arrow-return-left me-2"></i>Returns</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="returnsMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('returns.index') }}"><i class="bi bi-list me-2"></i>All Returns</a></li>
                                <li><a class="nav-link px-3" href="{{ route('credit-notes.index') }}"><i class="bi bi-receipt me-2"></i>Credit Notes</a></li>
                                <li><a class="nav-link px-3" href="{{ route('debit-notes.index') }}"><i class="bi bi-receipt me-2"></i>Debit Notes</a></li>
                                <li><a class="nav-link px-3" href="{{ route('returns.index', ['type' => 'customer_return']) }}"><i class="bi bi-arrow-return-left text-danger me-2"></i>Customer Returns</a></li>
                                <li><a class="nav-link px-3" href="{{ route('returns.index', ['type' => 'vendor_return']) }}"><i class="bi bi-arrow-return-right text-info me-2"></i>Vendor Returns</a></li>
                                <li><a class="nav-link px-3" href="{{ route('returns.index', ['type' => 'retailer_return']) }}"><i class="bi bi-arrow-return-left text-warning me-2"></i>Retailer Returns</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Stock Management -->
                    <li class="nav-item">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#stockMenu" role="button" aria-expanded="false" aria-controls="stockMenu">
                            <span><i class="bi bi-boxes me-2"></i>Stock Management</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="stockMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('stock-management.index') }}"><i class="bi bi-boxes me-2"></i>Stock Management</a></li>
                                <li><a class="nav-link px-3" href="{{ route('stock-locations.index') }}"><i class="bi bi-geo-alt me-2"></i>Stock Locations</a></li>
                                <li><a class="nav-link px-3" href="{{ route('picking.transaction-flow') }}"><i class="bi bi-diagram-3 me-2"></i>Transaction Flow</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Sales -->
                    <li class="nav-item">
                        <a class="nav-link px-3" href="{{ route('orders.index') }}"><i class="bi bi-cart me-2"></i>Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="{{ route('invoices.index') }}"><i class="bi bi-receipt me-2"></i>Invoices</a>
                    </li>

                    <!-- Accounting -->
                    <li class="nav-item mt-3">
                        <a class="nav-link px-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#accountingMenu" role="button" aria-expanded="false" aria-controls="accountingMenu">
                            <span><i class="bi bi-journal-bookmark me-2"></i>Accounting</span>
                            <i class="bi bi-chevron-down small"></i>
                        </a>
                        <div class="collapse" id="accountingMenu" data-bs-parent="#sidebarAccordion">
                            <ul class="navbar-nav ps-3">
                                <li><a class="nav-link px-3" href="{{ route('accounting.chart-of-accounts') }}"><i class="bi bi-journal me-2"></i>Chart of Accounts</a></li>
                                <li><a class="nav-link px-3" href="{{ route('reports.trial-balance') }}"><i class="bi bi-calculator me-2"></i>Trial Balance</a></li>
                                <li><a class="nav-link px-3" href="{{ route('reports.income-statement') }}"><i class="bi bi-clipboard-data me-2"></i>Income Statement</a></li>
                                <li><a class="nav-link px-3" href="{{ route('reports.balance-sheet') }}"><i class="bi bi-columns-gap me-2"></i>Balance Sheet</a></li>
                                <li><a class="nav-link px-3" href="{{ route('reports.cash-flow') }}"><i class="bi bi-cash-stack me-2"></i>Cash Flow Statement</a></li>
                                <li><hr></li>
                                <li><a class="nav-link px-3" href="{{ route('journal-entries.index') }}"><i class="bi bi-journal-text me-2"></i>Journal Entries</a></li>
                                <li><a class="nav-link px-3" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check me-2"></i>Audit Trail</a></li>
                            </ul>
                        </div>
                    </li>

                    <!-- Reports -->
                    <li class="nav-item">
                        <a class="nav-link px-3" href="{{ route('reports.daily-profit') }}"><i class="bi bi-file-earmark-bar-graph me-2"></i>Daily Profit</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <!-- END: Sidebar Navigation Layout -->

    <div class="container main-content">
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

        @yield('content')
    </div>

    <footer class="mt-5 py-4 text-center text-muted border-top">
        <div class="container">
            <p class="mb-0">&copy; {{ date('Y') }} Sales Order System | <span class="text-primary-custom">Premium Business Solution</span></p>
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
                const currentPath = window.location.pathname;
                document.querySelectorAll('#sidebar .nav-link, #topNav .nav-link').forEach(link => {
                    const href = link.getAttribute('href');
                    if (href && currentPath.includes(href.split('/').filter(Boolean).pop())) {
                        link.classList.add('active');
                        // If the link is inside a collapsed menu, expand it
                        const parentCollapse = link.closest('.collapse');
                        if (parentCollapse) {
                            const collapseInstance = bootstrap.Collapse.getOrCreateInstance(parentCollapse, { toggle: false });
                            collapseInstance.show();
                        }
                    }
                });
                
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
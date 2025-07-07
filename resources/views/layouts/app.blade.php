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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="bi bi-graph-up me-2"></i>Sales Order System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <i class="bi bi-box me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('customers.index') }}">
                            <i class="bi bi-people me-1"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('vendors.index') }}">
                            <i class="bi bi-building me-1"></i> Vendors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('supplies.index') }}">
                            <i class="bi bi-truck me-1"></i> Supplies
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="pickingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-list-check me-1"></i> Picking
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="pickingDropdown">
                            <li class="dropdown-submenu">
                                <a class="dropdown-item dropdown-toggle" href="#" id="pickingTypesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-diagram-2 me-1"></i> Picking Types
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="pickingTypesDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('vendor-to-warehouse-picking.index') }}">
                                            <i class="bi bi-truck-arrow-right me-1"></i> Vendors to Warehouse Picking
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('stock-transfers.warehouse-to-retailer') }}">
                                            <i class="bi bi-building-arrow-right me-1"></i> Warehouse to Retailers Picking
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('warehouse-to-customer-picking.index') }}">
                                            <i class="bi bi-house-arrow-right me-1"></i> Warehouse to Customers Picking
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('retailer-to-customer-picking.index') }}">
                                            <i class="bi bi-people-arrow-right me-1"></i> Retailers to Customers Picking
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('picking-lists.index') }}">
                                    <i class="bi bi-list-ul me-1"></i> View All Picking Lists
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('picking.transaction-flow') }}">
                                    <i class="bi bi-diagram-3 me-1"></i> Transaction Flow
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('orders.index') }}">
                            <i class="bi bi-cart me-1"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="stockDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-boxes me-1"></i> Stock Management
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="stockDropdown">
                            <li>
                                <a class="dropdown-item" href="{{ route('stock-management.index') }}">
                                    <i class="bi bi-boxes me-1"></i> Stock Management
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('stock-locations.index') }}">
                                    <i class="bi bi-geo-alt me-1"></i> Stock Locations
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('returns.index') }}">
                                    <i class="bi bi-arrow-return-left me-1"></i> Returns
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('picking.transaction-flow') }}">
                                    <i class="bi bi-diagram-3 me-1"></i> Transaction Flow
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                            <li>
                                <a class="dropdown-item" href="{{ route('reports.daily-profit') }}">
                                    <i class="bi bi-graph-up-arrow me-1"></i> Daily Profit
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            </div>
        @endif

        @if($errors->any())
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Page-specific scripts pushed from child views --}}
    @stack('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && currentPath.includes(href.split('/').filter(Boolean).pop())) {
                    link.classList.add('active');
                }
            });
            
            // Handle multi-level dropdown menus
            const dropdownSubmenus = document.querySelectorAll('.dropdown-submenu');
            
            dropdownSubmenus.forEach(function(submenu) {
                submenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other submenus
                    dropdownSubmenus.forEach(function(otherSubmenu) {
                        if (otherSubmenu !== submenu) {
                            const otherDropdownMenu = otherSubmenu.querySelector('.dropdown-menu');
                            if (otherDropdownMenu) {
                                otherDropdownMenu.style.display = 'none';
                            }
                        }
                    });
                    
                    // Toggle current submenu
                    const dropdownMenu = submenu.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        if (dropdownMenu.style.display === 'block') {
                            dropdownMenu.style.display = 'none';
                        } else {
                            dropdownMenu.style.display = 'block';
                        }
                    }
                });
                
                // Handle hover for desktop
                submenu.addEventListener('mouseenter', function() {
                    if (window.innerWidth > 767) {
                        const dropdownMenu = submenu.querySelector('.dropdown-menu');
                        if (dropdownMenu) {
                            dropdownMenu.style.display = 'block';
                        }
                    }
                });
                
                submenu.addEventListener('mouseleave', function() {
                    if (window.innerWidth > 767) {
                        const dropdownMenu = submenu.querySelector('.dropdown-menu');
                        if (dropdownMenu) {
                            dropdownMenu.style.display = 'none';
                        }
                    }
                });
            });
            
            // Prevent parent dropdown from closing when clicking submenu items
            document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(submenuDropdown) {
                submenuDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
</body>
</html> 
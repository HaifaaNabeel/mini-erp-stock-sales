<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? html_escape($page_title) . ' — Mini ERP' : 'Mini ERP Stock & Sales'; ?></title>
    <link rel="icon" type="image/jpeg" href="<?php echo base_url('assets/images/favicon.jpg'); ?>">
    <style>
        :root {
            --primary: #1e40af;
            --primary-hover: #1e3a8a;
            --primary-light: #eff6ff;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #2563eb;
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #ffffff;
            --success: #16a34a;
            --success-light: #f0fdf4;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text-dark: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            --sidebar-width: 260px;
            --topbar-height: 64px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-main);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            right: 0;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0 1.25rem;
            background-color: #0b1120;
            border-bottom: 1px solid #1e293b;
            text-decoration: none;
        }

        .sidebar-logo {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #3b82f6;
            background: white;
        }

        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand-title {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-brand-subtitle {
            color: #38bdf8;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 0;
        }

        .nav-category {
            padding: 0.75rem 1.25rem 0.35rem 1.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .nav-list {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 2px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 1.25rem;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-right: 3px solid transparent;
        }

        .nav-link:hover {
            color: var(--sidebar-text-hover);
            background-color: var(--sidebar-hover);
        }

        .nav-link.active {
            color: #ffffff;
            background-color: rgba(37, 99, 235, 0.15);
            border-right-color: #3b82f6;
            font-weight: 600;
        }

        .nav-link-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 22px;
            display: inline-block;
            text-align: center;
        }

        .nav-tag {
            font-size: 0.7rem;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            background-color: #1e293b;
            color: #94a3b8;
        }

        .nav-tag-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            background-color: #0b1120;
            border-top: 1px solid #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-name {
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .user-role {
            font-size: 0.75rem;
            color: #38bdf8;
        }

        .btn-sidebar-logout {
            background-color: #1e293b;
            color: #f87171;
            border: 1px solid #334155;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background-color: #7f1d1d;
            color: #ffffff;
            border-color: #991b1b;
        }

        /* App Wrapper */
        .app-wrapper {
            flex: 1;
            margin-right: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-width: 0;
        }

        /* Top Bar */
        .topbar {
            height: var(--topbar-height);
            background-color: #ffffff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-toggle-sidebar {
            display: none;
            background: none;
            border: 1px solid var(--border);
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .page-header-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .currency-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Role Badges */
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-badge-admin {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .role-badge-warehouse {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .role-badge-sales {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* Main Content */
        .content-body {
            flex: 1;
            padding: 1.5rem;
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
        }

        /* Flash Alerts */
        .alert {
            padding: 0.9rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        /* Cards */
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
            text-align: right;
            background: #ffffff;
        }

        .table th {
            background-color: #f8fafc;
            color: var(--text-secondary);
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background-color: #f8fafc;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 2px 4px rgba(30, 64, 175, 0.25);
        }

        .btn-secondary {
            background-color: #ffffff;
            color: var(--text-secondary);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background-color: #f1f5f9;
            color: var(--text-dark);
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.82rem;
            border-radius: 6px;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: #ffffff;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }

        /* Unified Data Table Controls & Pagination */
        .datatable-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .show-entries-box {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .show-entries-select {
            padding: 0.38rem 0.65rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background-color: #ffffff;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
            outline: none;
            transition: all 0.2s;
        }

        .show-entries-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        .datatable-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .datatable-summary {
            font-size: 0.88rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .pagination {
            display: inline-flex !important;
            flex-direction: row !important;
            gap: 0.35rem !important;
            align-items: center !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .pagination .page-item {
            display: inline-flex !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .pagination .page-item .page-link,
        .pagination .page-item a {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0.45rem 0.85rem !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            font-size: 0.88rem !important;
            font-weight: 600 !important;
            border: 1px solid var(--border) !important;
            color: var(--text-dark) !important;
            background: #ffffff !important;
            transition: all 0.15s ease-in-out !important;
            min-width: 38px !important;
            height: 38px !important;
            line-height: 1 !important;
        }

        .pagination .page-item a:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: var(--primary) !important;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary) !important;
            color: #ffffff !important;
            border-color: var(--primary) !important;
        }

        .filter-toolbar {
            background-color: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            flex: 1;
        }

        /* Footer */
        .footer {
            background-color: #ffffff;
            border-top: 1px solid var(--border);
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: auto;
        }

        /* Responsive Mobile */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .app-wrapper {
                margin-right: 0;
            }
            .btn-toggle-sidebar {
                display: block;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <a href="<?php echo site_url('dashboard'); ?>" class="sidebar-brand">
            <img src="<?php echo base_url('assets/images/logo.jpg'); ?>" alt="Logo" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">Mini ERP</span>
                <span class="sidebar-brand-subtitle">المخزون والمبيعات</span>
            </div>
        </a>

        <div class="sidebar-nav">
            <!-- Overview -->
            <div class="nav-category">الرئيسية</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo site_url('dashboard'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'dashboard' || $this->uri->segment(1) === '') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">📊</span>
                            <span>لوحة التحكم</span>
                        </span>
                    </a>
                </li>
            </ul>

            <?php if (isset($current_user) && $current_user->role_name === 'admin'): ?>
            <!-- System Admin -->
            <div class="nav-category">إدارة النظام</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo site_url('users'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'users') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">👥</span>
                            <span>إدارة المستخدمين</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F03</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <!-- Inventory Section -->
            <div class="nav-category">المخزون والمستودعات</div>
            <ul class="nav-list">
                <?php if (isset($current_user) && $current_user->role_name === 'admin'): ?>
                <li class="nav-item">
                    <a href="<?php echo site_url('categories'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'categories') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">🏷️</span>
                            <span>التصنيفات</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F04</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo site_url('products'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'products') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">📦</span>
                            <span>المنتجات</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F04</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo site_url('warehouses'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'warehouses' && $this->uri->segment(2) !== 'my') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">🏬</span>
                            <span>إدارة المستودعات</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F04</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (isset($current_user) && ($current_user->role_name === 'warehouse' || $current_user->role_name === 'sales')): ?>
                <li class="nav-item">
                    <a href="<?php echo site_url('warehouses/my'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'warehouses' && $this->uri->segment(2) === 'my') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">🏬</span>
                            <span>مستودعاتي المصرح بها</span>
                        </span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?php echo site_url('stock'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'stock' && $this->uri->segment(2) !== 'movements') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">📊</span>
                            <span>أرصدة المخزون</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F05</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo site_url('stock/movements'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'stock' && $this->uri->segment(2) === 'movements') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">🔄</span>
                            <span>حركات المخزون</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F06</span>
                    </a>
                </li>
            </ul>

            <!-- Sales Section -->
            <?php if (isset($current_user) && ($current_user->role_name === 'admin' || $current_user->role_name === 'sales')): ?>
            <div class="nav-category">المبيعات</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo site_url('customers'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'customers') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">👥</span>
                            <span>إدارة العملاء</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F07</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo site_url('sales/invoices'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'sales' && $this->uri->segment(2) === 'invoices') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">🧾</span>
                            <span>فواتير المبيعات</span>
                        </span>
                        <span class="nav-tag nav-tag-active">F07</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <!-- Reports Section -->
            <div class="nav-category">التقارير وسجل التدقيق</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo site_url('reports'); ?>" class="nav-link <?php echo ($this->uri->segment(1) === 'reports') ? 'active' : ''; ?>">
                        <span class="nav-link-content">
                            <span class="nav-icon">📊</span>
                            <span>التقارير</span>
                        </span>
                    </a>
                </li>
                <?php if (isset($current_user) && $current_user->role_name === 'admin'): ?>
                <li class="nav-item">
                    <a href="javascript:void(0);" class="nav-link" style="opacity: 0.6; cursor: not-allowed;" title="الميزة F10 القادمة">
                        <span class="nav-link-content">
                            <span class="nav-icon">📜</span>
                            <span>سجل التدقيق</span>
                        </span>
                        <span class="nav-tag">F10</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="user-name"><?php echo html_escape($current_user->full_name); ?></span>
                <span class="user-role"><?php echo html_escape($current_user->role_display_name); ?></span>
            </div>
            <a href="<?php echo site_url('logout'); ?>" class="btn-sidebar-logout" title="تسجيل الخروج">خروج</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="app-wrapper">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="btn-toggle-sidebar" id="toggleSidebar" aria-label="القائمة">
                    ☰
                </button>
                <h1 class="page-header-title">
                    <?php echo isset($page_title) ? html_escape($page_title) : 'Mini ERP Stock & Sales'; ?>
                </h1>
            </div>

            <div class="topbar-right">
                <span class="currency-badge">
                    <span>💵</span>
                    <span>العملة: ريال سعودي (SAR)</span>
                </span>

                <span class="role-badge role-badge-<?php echo html_escape($current_user->role_name); ?>">
                    <?php echo html_escape($current_user->role_display_name); ?>
                </span>
            </div>
        </header>

        <!-- Body Content -->
        <main class="content-body">
            <!-- Flash Messages -->
            <?php if ($this->session->flashdata('success')): ?>
                <div class="alert alert-success">
                    <span style="font-size: 1.2rem;">✓</span>
                    <div><?php echo $this->session->flashdata('success'); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($this->session->flashdata('error')): ?>
                <div class="alert alert-error">
                    <span style="font-size: 1.2rem;">⚠</span>
                    <div><?php echo $this->session->flashdata('error'); ?></div>
                </div>
            <?php endif; ?>

            <!-- Rendered Page Content -->
            <?php echo isset($_view_content) ? $_view_content : ''; ?>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>Mini ERP Stock & Sales &copy; <?php echo date('Y'); ?> — نظام إدارة المخزون والمبيعات الموحد</p>
        </footer>
    </div>

    <script>
        // Sidebar Toggle for Mobile Devices
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            var sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        });

        // Global Handler for Entries Per Page (Show) Change
        function changeEntriesLimit(selectElement, formId) {
            if (formId) {
                var form = document.getElementById(formId);
                if (form) {
                    var pageInput = form.querySelector('input[name="page"]');
                    if (pageInput) {
                        pageInput.remove();
                    }
                    form.submit();
                    return;
                }
            }
            var formParent = selectElement.closest('form');
            if (formParent) {
                var pageInput = formParent.querySelector('input[name="page"]');
                if (pageInput) {
                    pageInput.remove();
                }
                formParent.submit();
                return;
            }
            // Fallback for standalone dropdown
            var url = new URL(window.location.href);
            url.searchParams.set('limit', selectElement.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

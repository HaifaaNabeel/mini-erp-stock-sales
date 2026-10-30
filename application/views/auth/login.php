<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — Mini ERP Stock & Sales</title>
    <link rel="icon" type="image/jpeg" href="<?php echo base_url('assets/images/favicon.jpg'); ?>">
    <style>
        :root {
            --primary: #1e40af;
            --primary-hover: #1e3a8a;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --success: #16a34a;
            --success-light: #f0fdf4;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-color: #0f172a;
            --card-bg: #ffffff;
            --font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background-color: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
            position: relative;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            object-fit: cover;
            margin: 0 auto 1rem auto;
            display: block;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid #e2e8f0;
        }

        .login-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

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
            padding: 0.75rem 1rem;
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

        .btn-submit {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 0.5rem;
            font-family: inherit;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            padding-top: 1rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <img src="<?php echo base_url('assets/images/logo.jpg'); ?>" alt="Mini ERP Logo" class="login-logo">
            <h1 class="login-title">تسجيل الدخول للنظام</h1>
            <p class="login-subtitle">نظام إدارة المخزون والمبيعات (Mini ERP)</p>
        </div>

        <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <div><?php echo $this->session->flashdata('error'); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <div><?php echo $this->session->flashdata('success'); ?></div>
            </div>
        <?php endif; ?>

        <?php if (validation_errors()): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <div><?php echo validation_errors('<div>', '</div>'); ?></div>
            </div>
        <?php endif; ?>

        <?php echo form_open('login'); ?>
            <div class="form-group">
                <label for="username" class="form-label">اسم المستخدم</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?php echo set_value('username'); ?>" 
                       required autofocus autocomplete="username" placeholder="مثال: admin">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" id="password" name="password" class="form-control" 
                       required autocomplete="current-password" placeholder="أدخل كلمة المرور">
            </div>

            <button type="submit" class="btn-submit">دخول إلى النظام</button>
        <?php echo form_close(); ?>

        <div class="login-footer">
            <p>Mini ERP Stock & Sales &copy; <?php echo date('Y'); ?> — العملة: SAR</p>
        </div>
    </div>

</body>
</html>

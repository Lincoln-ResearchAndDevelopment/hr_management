<?php
// Topbar Component
$page_title = isset($page_title) ? $page_title : 'HR Dashboard';
$user_info = isset($user) ? $user : null;
?>

<!-- Topbar -->
<div class="topbar" id="topbar">
    <div class="topbar-left">
        <button class="toggle-btn" id="toggleBtn">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><?php echo $page_title; ?></h1>
    </div>

    <div class="topbar-right">
        <?php if ($user_info): ?>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_info['first_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_info['first_name']); ?></div>
                    <div class="user-role">HR Manager</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .topbar {
        position: fixed;
        top: 0;
        left: 280px;
        right: 0;
        height: 70px;
        background: linear-gradient(90deg, #fff 0%, #f8f9fa 100%);
        border-bottom: 2px solid #C82333;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        z-index: 999;
        box-shadow: 0 2px 8px rgba(200, 35, 51, 0.1);
    }

    .topbar.full-width {
        left: 0;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .toggle-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #C82333;
        cursor: pointer;
        transition: color 0.3s;
    }

    .toggle-btn:hover {
        color: #a01c28;
    }

    .topbar-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #111;
        margin: 0;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        padding: 8px 15px;
        border-radius: 6px;
        transition: background 0.3s;
    }

    .user-profile:hover {
        background-color: #ffe8eb;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #C82333;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #111;
    }

    .user-role {
        font-size: 0.8rem;
        color: #C82333;
    }

    @media (max-width: 768px) {
        .topbar {
            left: 220px;
            padding: 0 20px;
        }

        .topbar.full-width {
            left: 0;
        }

        .topbar-title {
            font-size: 1.1rem;
        }
    }

    @media (max-width: 480px) {
        .topbar {
            left: 200px;
            padding: 0 15px;
        }

        .topbar-right {
            gap: 10px;
        }

        .user-info {
            display: none;
        }
    }
</style>
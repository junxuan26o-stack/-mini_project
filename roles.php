<?php
// ============================================================
// auth/roles.php  —  三种身份: admin / agent / user
//
//   user  : 管理自己的 categories、transactions、budgets
//   agent : 只能看(所有用户的资料),什么都不能改
//   admin : 能看,还能修改 / 删除用户账号(email、身份、密码)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const VALID_ROLES = ['user', 'agent', 'admin'];

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_role() {
    return $_SESSION['user']['role'] ?? null;
}

// 没登录,或者是旧的 session(没有 role) → 清掉,送回登录页
function require_login() {
    $u = $_SESSION['user'] ?? null;

    if (!$u || !isset($u['id'], $u['role']) || !in_array($u['role'], VALID_ROLES, true)) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../verification/login.php');
        exit;
    }
}

// 登录后每个身份的首页
function home_for_role($role) {
    return $role === 'user' ? 'dashboard.php' : 'users.php';
}

// 只有列出的身份才能进这个页面,其他人送回自己的首页
function require_role(array $roles) {
    require_login();

    if (!in_array(current_role(), $roles, true)) {
        header('Location: ' . home_for_role(current_role()));
        exit;
    }
}
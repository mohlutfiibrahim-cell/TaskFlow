<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function taskflow_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function taskflow_verify_csrf($token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function taskflow_current_user_id()
{
    return taskflow_is_demo_mode()
        ? 0
        : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);
}

function taskflow_is_demo_mode()
{
    return !empty($_SESSION['demo_mode']);
}

function taskflow_require_login()
{
    if (!taskflow_is_demo_mode() && taskflow_current_user_id() < 1) {
        header('Location: login.php');
        exit;
    }
}

function taskflow_redirect($location)
{
    header('Location: ' . $location);
    exit;
}

function taskflow_set_flash($message, $type)
{
    $_SESSION['flash'] = array('message' => $message, 'type' => $type);
}

function taskflow_sign_in($user)
{
    session_regenerate_id(true);
    unset($_SESSION['demo_mode'], $_SESSION['demo_tasks'], $_SESSION['demo_next_task_id']);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function taskflow_sign_in_demo($name)
{
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    $_SESSION['demo_mode'] = true;
    $_SESSION['demo_tasks'] = array();
    $_SESSION['demo_next_task_id'] = 1;
    $_SESSION['user_name'] = (string) $name;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

<?php


foreach ($_GET as $key => $value) {
    // Keep @ and sanitize output
    $value = preg_replace('/[^a-zA-Z0-9@._+-]/', '', $value);
    $_GET[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

foreach ($_POST as $key => $value) {
    // Allow letters, numbers, @, ., -, _, + (for email aliases), and space
    $value = preg_replace('/[^a-zA-Z0-9@._+-]/', '', $value);
    $_POST[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

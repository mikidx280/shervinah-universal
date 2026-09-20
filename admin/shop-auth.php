<?php
declare(strict_types=1);
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
if(empty($_SESSION['su_admin'])){header('Location: index.php');exit;}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
require_once dirname(__DIR__).'/api/commerce-lib.php';
function esc($v): string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_csrf(): void {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Invalid request.');}
}

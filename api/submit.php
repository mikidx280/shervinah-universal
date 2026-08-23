<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function respond(int $status, array $body): never { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['message' => 'Method not allowed']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 25000) respond(413, ['message' => 'Request too large']);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin && parse_url($origin, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', $host)) respond(403, ['message' => 'Invalid origin']);
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) respond(400, ['message' => 'Invalid request']);
if (!empty($input['website'])) respond(200, ['message' => ['en' => 'Thank you.', 'fa' => 'متشکریم.']]);
$configPath = dirname(__DIR__, 2) . '/private-config.php';
if (!is_file($configPath)) respond(503, ['message' => ['en' => 'The secure form service is being configured. Please try again later.', 'fa' => 'سرویس امن فرم در حال راه‌اندازی است. لطفاً کمی بعد دوباره تلاش کنید.']]);
$config = require $configPath;
$allowedTypes = ['club', 'course', 'collaboration', 'personal_message'];
$type = (string)($input['form_type'] ?? '');
if (!in_array($type, $allowedTypes, true)) respond(422, ['message' => 'Unknown form type']);
$email = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) respond(422, ['message' => ['en' => 'Please enter a valid email address.', 'fa' => 'لطفاً یک آدرس ایمیل معتبر وارد کنید.']]);
if ($type === 'club' && empty($input['marketing_consent'])) respond(422, ['message' => ['en' => 'Club consent is required.', 'fa' => 'برای عضویت در باشگاه باید رضایت خود را تأیید کنید.']]);
if (in_array($type, ['course', 'collaboration', 'personal_message'], true) && empty($input['service_consent'])) respond(422, ['message' => ['en' => 'Service and privacy consent is required.', 'fa' => 'تأیید رضایت و سیاست حفظ حریم خصوصی الزامی است.']]);
$limit = 2000;
$clean = [];
foreach ($input as $key => $value) {
    if (!is_string($key) || !preg_match('/^[a-z_]+$/', $key) || in_array($key, ['website', 'form_type'], true)) continue;
    if (is_scalar($value)) $clean[$key] = mb_substr(trim((string)$value), 0, $limit);
}
$fullName = mb_substr(trim((string)($input['full_name'] ?? $input['first_name'] ?? '')), 0, 180);
if (in_array($type, ['course', 'collaboration', 'personal_message'], true) && $fullName === '') respond(422, ['message' => ['en' => 'Please enter your name.', 'fa' => 'لطفاً نام خود را وارد کنید.']]);
try {
    $pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $stmt = $pdo->prepare('INSERT INTO su_leads (form_type, full_name, email, phone, language, marketing_consent, service_consent, payload_json, consent_version, ip_hash, status, created_at) VALUES (:form_type,:full_name,:email,:phone,:language,:marketing,:service,:payload,:consent,:ip_hash,\'new\',UTC_TIMESTAMP())');
    $stmt->execute(['form_type'=>$type,'full_name'=>$fullName,'email'=>$email,'phone'=>mb_substr(trim((string)($input['phone'] ?? '')),0,60),'language'=>mb_substr(trim((string)($input['language'] ?? 'en')),0,10),'marketing'=>empty($input['marketing_consent'])?0:1,'service'=>empty($input['service_consent'])?0:1,'payload'=>json_encode($clean,JSON_UNESCAPED_UNICODE),'consent'=>'2026-08-24-v1','ip_hash'=>hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),(string)$config['ip_salt'])]);
    $leadId = (int)$pdo->lastInsertId();
    @mail((string)$config['notification_email'], 'New Shervinah Universal inquiry #'.$leadId, "A new {$type} inquiry was received. Sign in to the secure admin area to review it.", ['From'=>'no-reply@shervinahuniversal.com','Content-Type'=>'text/plain; charset=UTF-8']);
    $messages = ['club'=>['en'=>'Welcome to the Shervinah Universal Club. Please check your email for future updates.','fa'=>'به باشگاه شروینا یونیورسال خوش آمدی. از این پس خبرهای تازه را با ایمیل دریافت می‌کنی.'],'course'=>['en'=>'Thank you. Your course interest was received and a representative may contact you with the full details.','fa'=>'متشکریم. علاقه‌مندی تو به دوره ثبت شد و نماینده ما می‌تواند برای ارائه جزئیات کامل با تو تماس بگیرد.'],'collaboration'=>['en'=>'Thank you. Your collaboration proposal was received and will be reviewed.','fa'=>'متشکریم. پیشنهاد همکاری تو دریافت شد و بررسی خواهد شد.'],'personal_message'=>['en'=>'Your personal message order details were received.','fa'=>'اطلاعات سفارش پیام شخصی تو دریافت شد.']];
    respond(201, ['success'=>true,'message'=>$messages[$type]]);
} catch (Throwable $e) {
    error_log('Shervinah form error: '.$e->getMessage());
    respond(500, ['message'=>['en'=>'We could not save your request. Please try again later.','fa'=>'ذخیره درخواست انجام نشد. لطفاً کمی بعد دوباره تلاش کنید.']]);
}
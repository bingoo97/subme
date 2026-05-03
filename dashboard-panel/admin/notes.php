<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

admin_send_security_headers();
admin_start_session();

header('Content-Type: application/json; charset=utf-8');

$db = Mysql_ks::get_instance();
$adminUser = admin_load_session_user($db);

if ($adminUser === null) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

$currentLocale = isset($_SESSION['admin_locale']) ? admin_normalize_locale((string)$_SESSION['admin_locale']) : 'pl';
$messages = admin_load_messages($currentLocale);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action !== 'save_personal_notes') {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Not found.',
    ]);
    exit;
}

if (!admin_csrf_is_valid($_POST['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => admin_t($messages, 'login_error', 'Login failed. Check your credentials.'),
    ]);
    exit;
}

$adminUserId = (int)($adminUser['id'] ?? 0);
if ($adminUserId <= 0) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => admin_t($messages, 'login_error', 'Login failed. Check your credentials.'),
    ]);
    exit;
}

if (!admin_personal_notes_available($db)) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => admin_t($messages, 'settings_notes_sql_required', 'SQL migration is required before administrator notes can be saved.'),
    ]);
    exit;
}

$personalNotesHtml = (string)($_POST['personal_notes_html'] ?? '');
$personalNotesHtmlEncoded = trim((string)($_POST['personal_notes_html_b64'] ?? ''));
if ($personalNotesHtmlEncoded !== '') {
    $decodedNotes = base64_decode(strtr($personalNotesHtmlEncoded, ' ', '+'), true);
    if ($decodedNotes === false) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => admin_t($messages, 'settings_notes_save_error', 'Unable to save administrator notes.'),
        ]);
        exit;
    }
    $personalNotesHtml = $decodedNotes;
}

$saveNotesResult = admin_save_personal_notes($db, $adminUserId, $personalNotesHtml);

if (empty($saveNotesResult['ok']) || !is_array($saveNotesResult['admin_user'] ?? null)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => admin_t($messages, 'settings_notes_save_error', (string)($saveNotesResult['message'] ?? 'Unable to save administrator notes.')),
    ]);
    exit;
}

$savedAdminUser = (array)$saveNotesResult['admin_user'];
echo json_encode([
    'ok' => true,
    'message' => admin_t($messages, 'settings_notes_saved', 'Your administrator notes have been saved automatically.'),
    'notes_html' => (string)($savedAdminUser['personal_notes_html'] ?? ''),
    'updated_at' => (string)($savedAdminUser['updated_at'] ?? ''),
]);
exit;

<?php
// ============================================================
//  MyFitCal — Database + Mailer Configuration
// ============================================================

// ── DATABASE ─────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'myfit_cal');
define('DB_CHARSET', 'utf8mb4');
// ── MAILER ────────────────────────────────────────────────────
define('MAIL_FROM_EMAIL', 'jorisonlorica98@gmail.com');  // ← palitan
define('MAIL_FROM_NAME',  'MyFitCal');
define('MAIL_SMTP_PASS',  'mget izsf jfio vvuc');   // ← App Password
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', 'tls');

// ── DB Connection ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
      $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}

// ── PHPMailer Loader ──────────────────────────────────────────
function loadPHPMailer(): bool {
    // Option 1: Composer autoload
    $composerPaths = [
        dirname(__DIR__) . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
    ];

    foreach ($composerPaths as $composer) {
        if (file_exists($composer)) {
            require_once $composer;
            return true;
        }
    }

    // Option 2: Manual install (vendor + legacy PHPMailer directory)
    $candidateBases = [
        dirname(__DIR__) . '/PHPMailer/src/',
        __DIR__ . '/../PHPMailer/src/',
        dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer/src/',
        __DIR__ . '/../vendor/PHPMailer/PHPMailer/src/',
    ];

    foreach ($candidateBases as $base) {
        $files = [
            $base . 'Exception.php',
            $base . 'PHPMailer.php',
            $base . 'SMTP.php',
        ];

        $allExist = true;
        foreach ($files as $file) {
            if (!file_exists($file)) {
                $allExist = false;
                break;
            }
        }

        if ($allExist) {
            foreach ($files as $file) {
                require_once $file;
            }
            return true;
        }
    }

    return false;
}

// ── Send Email ────────────────────────────────────────────────
function sendMail(string $to_email, string $to_name, string $subject, string $html_body): bool|string {
    if (!loadPHPMailer()) {
        return 'PHPMailer not found. See setup instructions.';
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM_EMAIL;
        $mail->Password   = trim(MAIL_SMTP_PASS);
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>'], "\n", $html_body));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        return $mail->ErrorInfo;
    }
}
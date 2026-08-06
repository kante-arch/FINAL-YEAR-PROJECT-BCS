<?php
/**
 * notifications.php
 * ------------------
 * Two kinds of notifications:
 *
 *  - EMAIL is real: it actually sends through your SMTP account
 *    (configured in mail_config.php) using the PHPMailer library.
 *
 *  - SMS is simulated: sending real SMS needs a paid gateway account
 *    (Beem Africa, Africa's Talking, etc.) with an API key. Since you
 *    don't have one yet, send_sms() below just records what WOULD have
 *    been sent into the sms_log table. When you get a real API key,
 *    replace the body of send_sms() with an actual HTTP call to your
 *    gateway -- every place that calls send_sms() in this project stays
 *    exactly the same.
 */

require_once __DIR__ . "/mail_config.php";
require_once __DIR__ . "/../lib/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/../lib/PHPMailer/SMTP.php";
require_once __DIR__ . "/../lib/PHPMailer/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a real email. Returns true on success, false on failure.
 * Never throws -- a failed email should never break a booking.
 */
function send_email(string $to_email, string $to_name, string $subject, string $body_html): bool {
    if (empty($to_email)) {
        return false; // user has no email on file
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body_html;
        $mail->AltBody = strip_tags($body_html);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        // Log quietly instead of showing the passenger a scary error.
        error_log("Email failed to $to_email: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * "Send" an SMS. For now this just writes to the sms_log table so you
 * can see, in the admin area, exactly what message would have gone out
 * and to which number -- proof the notification logic fires correctly.
 * Swap this out for a real gateway call (e.g. Beem Africa's REST API)
 * once you have an account and API key.
 */
function send_sms(PDO $pdo, string $phone, string $message): void {
    try {
        $pdo->prepare("INSERT INTO sms_log (phone, message) VALUES (?, ?)")
            ->execute([$phone, $message]);
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), "sms_log") !== false || stripos($e->getMessage(), "doesn't exist") !== false || stripos($e->getMessage(), "does not exist") !== false) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                message VARCHAR(300) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->prepare("INSERT INTO sms_log (phone, message) VALUES (?, ?)")
                ->execute([$phone, $message]);
        } else {
            throw $e;
        }
    }
}

/** Email + SMS sent right after a successful booking. */
function notify_booking_confirmed(PDO $pdo, array $user, array $booking_details): void {
    $subject = "Your SGR SeatFlow ticket is confirmed";
    $body = "
        <h2>Ticket Confirmed</h2>
        <p>Hi {$user['full_name']},</p>
        <p>Your seat <strong>Coach {$booking_details['coach_number']} - {$booking_details['seat_number']}</strong> is booked from
           <strong>{$booking_details['origin_name']}</strong> to
           <strong>{$booking_details['destination_name']}</strong>.</p>
        <p>Fare paid: <strong>" . number_format($booking_details['fare'], 0) . " TZS</strong></p>
        <p>Ticket code: <strong>{$booking_details['qr_code']}</strong></p>
        <p>Safe travels!</p>
    ";
    send_email($user["email"] ?? "", $user["full_name"], $subject, $body);

    $sms_text = "SGR SeatFlow: Coach {$booking_details['coach_number']} Seat {$booking_details['seat_number']} booked, "
        . "{$booking_details['origin_name']} to {$booking_details['destination_name']}. "
        . "Fare " . number_format($booking_details['fare'], 0) . " TZS. Code: {$booking_details['qr_code']}";
    send_sms($pdo, $user["phone"], $sms_text);
}

/** Email + SMS sent right after an early-exit refund is processed. */
function notify_refund_processed(PDO $pdo, array $user, float $refund, string $exit_station_name): void {
    $subject = "Refund processed - SGR SeatFlow";
    $body = "
        <h2>Refund Processed</h2>
        <p>Hi {$user['full_name']},</p>
        <p>You exited early at <strong>{$exit_station_name}</strong>.
           You've been refunded <strong>" . number_format($refund, 0) . " TZS</strong>
           for the distance you didn't travel.</p>
        <p>Your seat's remaining segment is now available for other passengers to book.</p>
    ";
    send_email($user["email"] ?? "", $user["full_name"], $subject, $body);

    $sms_text = "SGR SeatFlow: Refund of " . number_format($refund, 0)
        . " TZS credited after early exit at {$exit_station_name}.";
    send_sms($pdo, $user["phone"], $sms_text);
}

/** Email + SMS sent right after a wallet top-up. */
function notify_wallet_topup(PDO $pdo, array $user, float $amount, float $new_balance): void {
    $subject = "Wallet top-up successful - SGR SeatFlow";
    $body = "
        <h2>Wallet Topped Up</h2>
        <p>Hi {$user['full_name']},</p>
        <p>Your wallet was topped up by <strong>" . number_format($amount, 0) . " TZS</strong>.</p>
        <p>New balance: <strong>" . number_format($new_balance, 0) . " TZS</strong></p>
    ";
    send_email($user["email"] ?? "", $user["full_name"], $subject, $body);

    $sms_text = "SGR SeatFlow: Wallet topped up by " . number_format($amount, 0)
        . " TZS. New balance: " . number_format($new_balance, 0) . " TZS.";
    send_sms($pdo, $user["phone"], $sms_text);
}

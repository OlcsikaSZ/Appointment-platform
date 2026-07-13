<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($subjectLine); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4efe5;color:#17213f;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4efe5;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#fffdf9;border:1px solid #ded3bf;border-radius:18px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 30px;background:#1c2541;color:#fffdf9;">
                        <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#f0bd63;font-weight:700;"><?php echo e($eventLabel); ?></div>
                        <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;"><?php echo e($booking->business->name ?? 'Időpontfoglalás'); ?></h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <?php if($recipientType === 'customer'): ?>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">Kedves <strong><?php echo e($booking->customer_name); ?></strong>!</p>
                        <?php else: ?>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">Új foglalási esemény történt az adminisztrált vállalkozásnál.</p>
                        <?php endif; ?>

                        <?php if($eventType === 'booking_rescheduled' && $previousSchedule): ?>
                            <div style="margin:0 0 22px;padding:14px 16px;border-radius:12px;background:#fff5df;border:1px solid #edd3a5;">
                                <strong>Korábbi időpont:</strong><br>
                                <?php echo e($previousSchedule['date'] ?? ''); ?> · <?php echo e(substr($previousSchedule['start_time'] ?? '', 0, 5)); ?>–<?php echo e(substr($previousSchedule['end_time'] ?? '', 0, 5)); ?>

                            </div>
                        <?php endif; ?>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                            <tr><td style="padding:10px 0;color:#746d61;width:38%;">Szolgáltatás</td><td style="padding:10px 0;font-weight:700;"><?php echo e($booking->service_name); ?></td></tr>
                            <tr><td style="padding:10px 0;color:#746d61;">Dátum</td><td style="padding:10px 0;font-weight:700;"><?php echo e($booking->date?->format('Y. m. d.')); ?></td></tr>
                            <tr><td style="padding:10px 0;color:#746d61;">Időpont</td><td style="padding:10px 0;font-weight:700;"><?php echo e(substr($booking->start_time, 0, 5)); ?>–<?php echo e(substr($booking->end_time, 0, 5)); ?></td></tr>
                            <tr><td style="padding:10px 0;color:#746d61;">Név</td><td style="padding:10px 0;font-weight:700;"><?php echo e($booking->customer_name); ?></td></tr>
                            <tr><td style="padding:10px 0;color:#746d61;">E-mail</td><td style="padding:10px 0;font-weight:700;"><?php echo e($booking->customer_contact); ?></td></tr>
                            <?php if($booking->customer_note): ?>
                                <tr><td style="padding:10px 0;color:#746d61;vertical-align:top;">Megjegyzés</td><td style="padding:10px 0;font-weight:700;white-space:pre-line;"><?php echo e($booking->customer_note); ?></td></tr>
                            <?php endif; ?>
                        </table>

                        <div style="margin-top:26px;">
                            <a href="<?php echo e($manageUrl); ?>" style="display:inline-block;padding:13px 20px;border-radius:999px;background:#1c2541;color:#fffdf9;text-decoration:none;font-weight:700;"><?php echo e($eventType === 'booking_cancelled' ? 'Foglalás megnyitása' : 'Foglalás kezelése'); ?></a>
                        </div>
                        <p style="margin:16px 0 0;color:#746d61;font-size:12px;line-height:1.6;word-break:break-all;">Kezelő link: <?php echo e($manageUrl); ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
<?php /**PATH E:\Progik\xampp\htdocs\appointment-platform\Appointment-platform\backend\resources\views/emails/booking-notification.blade.php ENDPATH**/ ?>
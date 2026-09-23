<?php
/**
 * Every mail kso sends: KSO's mark and the installation's name at the top, the message, and
 * who runs this installation at the bottom.
 *
 * The colours are written on each element, not only in the style block: several mail clients
 * drop the style block. `color-scheme: light only` asks a client in dark mode not to turn the
 * colours around - Apple Mail made the grafit band pale grey without it.
 *
 * @var string $receiverName
 * @var string $subject
 * @var string $message HTML, as the caller wrote it
 * @var string|null $logoCid the logo attached to the mail, see EmailLib
 * @var string $projectName PROJECT_NAME
 * @var string $contactUrl PROJECT_CONTACT_URL
 * @var string $contactEmail PROJECT_CONTACT_EMAIL
 */
$font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="color-scheme" content="light only"/>
    <meta name="supported-color-schemes" content="light only"/>
    <title><?= esc($subject) ?></title>
    <style type="text/css">
        :root { color-scheme: light only; supported-color-schemes: light only; }
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f1f3f5; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: #2563eb; }
        p { margin: 0 0 16px; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f3f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1f3f5;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden;">

                <tr>
                    <td style="background-color: #26313c; padding: 20px 28px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <?php if (!empty($logoCid)) : ?>
                                    <td valign="middle" style="padding-right: 14px;">
                                        <img src="cid:<?= esc($logoCid) ?>" width="56" height="56" alt="KSO" style="display: block; width: 56px; height: 56px;"/>
                                    </td>
                                <?php endif; ?>
                                <td valign="middle" style="font-family: <?= $font ?>; color: #ffffff;">
                                    <div style="font-size: 24px; font-weight: bold; letter-spacing: 1px; line-height: 28px;">KSO</div>
                                    <?php if ($projectName !== '') : ?>
                                        <div style="font-size: 15px; line-height: 20px; color: #c9d3de;"><?= esc($projectName) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 32px 28px 8px; font-family: <?= $font ?>; font-size: 17px; line-height: 26px; color: #26313c;">
                        <p style="margin: 0 0 16px; font-size: 17px; line-height: 26px; color: #26313c;">Hi <?= esc($receiverName) ?></p>
                        <div style="font-size: 17px; line-height: 26px; color: #26313c;"><?= $message ?></div>
                    </td>
                </tr>

                <?php if ($projectName !== '' || $contactUrl !== '' || $contactEmail !== '') : ?>
                    <tr>
                        <td style="padding: 20px 28px 28px; font-family: <?= $font ?>; font-size: 14px; line-height: 22px; color: #5b6772; border-top: 1px solid #e5e8ec;">
                            <?php if ($projectName !== '') : ?>
                                <div style="font-weight: bold; color: #26313c;"><?= esc($projectName) ?></div>
                            <?php endif; ?>
                            <?php if ($contactUrl !== '') : ?>
                                <div><a href="<?= esc($contactUrl) ?>" style="color: #2563eb; text-decoration: none;"><?= esc($contactUrl) ?></a></div>
                            <?php endif; ?>
                            <?php if ($contactEmail !== '') : ?>
                                <div><a href="mailto:<?= esc($contactEmail) ?>" style="color: #2563eb; text-decoration: none;"><?= esc($contactEmail) ?></a></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>

            </table>
        </td>
    </tr>
</table>
</body>
</html>

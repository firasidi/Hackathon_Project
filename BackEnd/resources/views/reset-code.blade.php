<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center">
      <table role="presentation" width="480" cellpadding="0" cellspacing="0"
             style="max-width:480px;background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr><td style="background:#e70013;color:#ffffff;padding:18px 24px;font-size:20px;font-weight:bold;">
          Insaf &nbsp;|&nbsp; إنصاف
        </td></tr>

        <tr><td style="padding:24px;">
          <p style="margin:0 0 12px;">Bonjour{{ $studentName ? ' ' . $studentName : '' }},</p>
          <p style="margin:0 0 16px;">Voici votre code de vérification pour réinitialiser votre mot de passe :</p>

          <p style="margin:0 0 16px;text-align:center;font-size:34px;letter-spacing:10px;font-weight:bold;
                    background:#f4f5f7;border-radius:8px;padding:14px 0;">{{ $code }}</p>

          <p style="margin:0 0 8px;">Ce code expire dans <strong>{{ $minutes }} minutes</strong>.
            Ne le communiquez à personne.</p>
          <p style="margin:0;color:#6b7280;font-size:13px;">
            Si vous n'avez pas fait cette demande, ignorez ce message : votre mot de passe ne sera pas modifié.</p>
        </td></tr>

        <tr><td dir="rtl" style="padding:0 24px 24px;border-top:1px solid #e5e7eb;text-align:right;">
          <p style="margin:16px 0 8px;">مرحباً{{ $studentName ? ' ' . $studentName : '' }}،</p>
          <p style="margin:0 0 8px;">رمز التحقق الخاص بك لإعادة تعيين كلمة المرور هو الموضّح أعلاه.
            تنتهي صلاحيته بعد <strong>{{ $minutes }} دقائق</strong>. لا تشاركه مع أي شخص.</p>
          <p style="margin:0;color:#6b7280;font-size:13px;">
            إذا لم تطلب ذلك، تجاهل هذه الرسالة.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
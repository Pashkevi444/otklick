<!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1F2937; background: #F8FAFC; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; border: 1px solid #E2E8F0;">
        <h1 style="color: #1F4E79; font-size: 20px; margin: 0 0 16px;">Подтверждение новой почты</h1>
        <p style="margin: 0 0 16px;">Вы указали этот адрес как новую почту аккаунта в «Отклик». Введите код в кабинете, чтобы подтвердить смену:</p>
        <div style="font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #2E74B5; text-align: center; padding: 16px; background: #EFF6FF; border-radius: 8px; margin: 0 0 16px;">
            {{ $code }}
        </div>
        <p style="margin: 0 0 8px; color: #64748B; font-size: 14px;">Код действует {{ $ttlMinutes }} минут.</p>
        <p style="margin: 0; color: #64748B; font-size: 14px;">Если вы не меняли почту — просто проигнорируйте это письмо.</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Tidak Selesai</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 22px;">Pembayaran Tidak Selesai</h1>
    <p>Halo {{ $subscription->user->full_name }},</p>
    <p>Pembayaran langganan <strong>SkinCek Pro</strong> kamu tidak selesai dengan alasan:</p>
    <blockquote style="border-left: 4px solid #dc2626; margin: 12px 0; padding: 8px 16px; background: #fef2f2;">
        {{ $reason }}
    </blockquote>
    <p>Jangan khawatir — kamu bisa mencoba lagi kapan saja melalui aplikasi SkinCek. Transaksi sebelumnya tidak akan dikenakan biaya.</p>
    <p>Salam sehat,<br>Tim SkinCek</p>
</body>
</html>
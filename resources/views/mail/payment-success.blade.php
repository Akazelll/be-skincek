<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 22px;">Pembayaran Berhasil 🎉</h1>
    <p>Halo {{ $subscription->user->full_name }},</p>
    <p>Selamat! Langganan <strong>SkinCek Pro</strong> kamu sudah aktif dan semua fitur premium sudah terbuka.</p>
    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">No. Pesanan</td>
            <td style="padding: 6px 12px;">{{ $subscription->midtrans_order_id }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Metode Pembayaran</td>
            <td style="padding: 6px 12px;">{{ $subscription->payment_method ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Total Dibayar</td>
            <td style="padding: 6px 12px;">Rp {{ number_format($subscription->amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Dibayar Pada</td>
            <td style="padding: 6px 12px;">{{ $subscription->paid_at?->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
    <p>Nikmati scan tanpa batas, chat prioritas dengan dokter, dan semua fitur SkinCek Pro.</p>
    <p>Salam sehat,<br>Tim SkinCek</p>
</body>
</html>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Scan Kulit</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 22px;">Hasil Scan Kulit Kamu Sudah Siap ✨</h1>
    <p>Halo {{ $history->user->full_name }},</p>
    <p>Analisis kulit kamu dengan <strong>{{ $history->model_used }}</strong> telah selesai:</p>
    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Masalah Terdeteksi</td>
            <td style="padding: 6px 12px;">{{ $history->predicted_class }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Tingkat Keyakinan</td>
            <td style="padding: 6px 12px;">{{ number_format($history->confidence * 100, 1) }}%</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; font-weight: bold;">Tingkat Keparahan</td>
            <td style="padding: 6px 12px;">{{ ucfirst($history->severity_level->value) }}</td>
        </tr>
    </table>
    <p style="font-size: 13px; color: #6b7280;">{{ config('services.ml.disclaimer') }}</p>
    <p>Buka aplikasi SkinCek untuk melihat detail lengkap dan berkonsultasi dengan dokter kulit.</p>
    <p>Salam sehat,<br>Tim SkinCek</p>
</body>
</html>
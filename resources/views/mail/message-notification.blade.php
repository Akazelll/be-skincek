<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Baru</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 22px;">Pesan Baru 💬</h1>
    <p>Kamu menerima pesan baru dari <strong>{{ $senderName }}</strong> di SkinCek:</p>
    <blockquote style="border-left: 4px solid #0ea5e9; margin: 12px 0; padding: 8px 16px; background: #f0f9ff;">
        {{ $snippet }}
    </blockquote>
    <p>Buka aplikasi SkinCek untuk membalas pesan ini.</p>
    <p>Salam sehat,<br>Tim SkinCek</p>
</body>
</html>
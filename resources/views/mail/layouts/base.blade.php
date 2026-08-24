<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'SkinCek')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0fdf4; font-family: 'Segoe UI', Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.6;">
    {{-- Preheader --}}
    <div style="display: none; max-height: 0; overflow: hidden;">@yield('preheader', config('app.name'))</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0fdf4;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" style="padding: 8px 0 20px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center">
                                <tr>
                                    <td style="font-size: 22px; padding-right: 8px;">🌿</td>
                                    <td style="font-size: 24px; font-weight: 700; color: #047857; letter-spacing: -0.5px;">Skin<span style="color: #10b981;">Cek</span></td>
                                </tr>
                            </table>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">Deteksi Kondisi Kulit Wajah dengan AI</div>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #d1fae5;">

                                {{-- Banner gradien --}}
                                <tr>
                                    <td style="background-color: #059669; background-image: linear-gradient(135deg, #059669 0%, #0d9488 100%); padding: 28px 32px;">
                                        <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff;">@yield('heading', 'Halo 👋')</h1>
                                        @hasSection('subheading')
                                            <p style="margin: 6px 0 0 0; font-size: 14px; color: #d1fae5;">@yield('subheading')</p>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Konten --}}
                                <tr>
                                    <td style="padding: 32px;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding: 24px 16px;">
                            <p style="margin: 0 0 6px 0; font-size: 12px; color: #6b7280;">
                                Email ini dikirim otomatis oleh sistem {{ config('app.name') }}.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                <a href="https://www.skincek.web.id" style="color: #059669; text-decoration: none; font-weight: 600;">skincek.web.id</a>
                                &nbsp;&middot;&nbsp; &copy; {{ date('Y') }} {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

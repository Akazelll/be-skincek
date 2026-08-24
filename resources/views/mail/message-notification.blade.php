@extends('mail.layouts.base')

@section('title', 'Pesan Baru SkinCek')

@section('preheader', $senderName . ' mengirim pesan baru untukmu di SkinCek.')

@section('heading', 'Kamu Punya Pesan Baru 💬')

@section('subheading', 'Seseorang menunggu balasanmu.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">
        Kamu menerima pesan baru dari <strong style="color: #047857;">{{ $senderName }}</strong>:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #f0fdf4; border-left: 4px solid #10b981; border-radius: 0 10px 10px 0; padding: 14px 18px; font-size: 15px; color: #374151; font-style: italic;">
                &ldquo;{{ $snippet }}&rdquo;
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
        <tr>
            <td align="center">
                <a href="https://www.skincek.web.id"
                   style="display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 10px;">
                    Buka & Balas Pesan
                </a>
            </td>
        </tr>
    </table>
@endsection

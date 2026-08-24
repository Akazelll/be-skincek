@extends('mail.layouts.base')

@section('title', 'Reset Password SkinCek')

@section('preheader', 'Kode reset password kamu: ' . $otp . ' (berlaku 10 menit)')

@section('heading', 'Reset Password 🔑')

@section('subheading', 'Kami menerima permintaan untuk mengganti password akunmu.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">Halo,</p>
    <p style="margin: 0 0 20px 0; font-size: 15px;">
        Gunakan kode <strong>One-Time Password (OTP)</strong> berikut untuk mengganti password akunmu:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="background-color: #ecfdf5; border: 2px dashed #10b981; border-radius: 12px; padding: 20px;">
                <span style="font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #047857; font-family: 'Courier New', Courier, monospace;">{{ $otp }}</span>
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0 0; font-size: 13px; color: #6b7280; text-align: center;">
        Kode berlaku selama <strong style="color: #047857;">10 menit</strong> dan hanya dapat digunakan satu kali.
    </p>

    <p style="margin: 24px 0 0 0; font-size: 14px; color: #6b7280;">
        <strong style="color: #dc2626;">Penting:</strong> Jika kamu tidak merasa meminta reset password, abaikan email ini dan pastikan akunmu aman. Password kamu tidak akan berubah tanpa kode ini.
    </p>
@endsection

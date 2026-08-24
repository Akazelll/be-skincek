@extends('mail.layouts.base')

@section('title', 'Verifikasi Email SkinCek')

@section('preheader', 'Kode verifikasi kamu: ' . $otp . ' (berlaku 10 menit)')

@section('heading', 'Verifikasi Email Kamu 📧')

@section('subheading', 'Satu langkah lagi untuk mengaktifkan akunmu.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">Halo,</p>
    <p style="margin: 0 0 20px 0; font-size: 15px;">
        Gunakan kode <strong>One-Time Password (OTP)</strong> berikut untuk memverifikasi alamat emailmu:
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
        Jika kamu tidak merasa meminta verifikasi email, abaikan email ini — akunmu tetap aman.
    </p>
@endsection

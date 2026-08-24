@extends('mail.layouts.base')

@section('title', 'Pembayaran Tidak Selesai')

@section('preheader', 'Pembayaran SkinCek Pro kamu tidak selesai. Coba lagi kapan saja.')

@section('heading', 'Pembayaran Tidak Selesai 😔')

@section('subheading', 'Tenang, tidak ada biaya yang terkena.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">Halo {{ $subscription->user->full_name }},</p>
    <p style="margin: 0 0 16px 0; font-size: 15px;">
        Pembayaran langganan <strong>SkinCek Pro</strong> kamu tidak selesai dengan alasan:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #fef2f2; border-left: 4px solid #dc2626; border-radius: 0 10px 10px 0; padding: 12px 16px; font-size: 14px; color: #991b1b;">
                {{ $reason }}
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0 0; font-size: 15px;">
        Jangan khawatir &mdash; kamu bisa mencoba lagi kapan saja melalui aplikasi SkinCek. Transaksi sebelumnya <strong>tidak dikenakan biaya</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
        <tr>
            <td align="center">
                <a href="https://www.skincek.web.id"
                   style="display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 10px;">
                    Coba Lagi
                </a>
            </td>
        </tr>
    </table>
@endsection

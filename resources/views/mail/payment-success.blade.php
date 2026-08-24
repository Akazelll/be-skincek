@extends('mail.layouts.base')

@section('title', 'Pembayaran Berhasil')

@section('preheader', 'Langganan SkinCek Pro kamu sudah aktif. Selamat!')

@section('heading', 'Pembayaran Berhasil 🎉')

@section('subheading', 'Selamat! SkinCek Pro kamu sudah aktif.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">Halo {{ $subscription->user->full_name }},</p>
    <p style="margin: 0 0 20px 0; font-size: 15px;">
        Terima kasih! Langganan <strong style="color: #047857;">SkinCek Pro</strong> kamu sudah aktif dan semua fitur premium sudah terbuka.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #f0fdf4; border-radius: 12px; padding: 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">No. Pesanan</td>
                        <td align="right" style="padding: 6px 0; font-size: 13px; font-weight: 600; color: #1f2937;">{{ $subscription->midtrans_order_id }}</td>
                    </tr>
                    <tr><td colspan="2" style="border-top: 1px solid #d1fae5;"></td></tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Metode Pembayaran</td>
                        <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #1f2937;">{{ $subscription->payment_method ?? '-' }}</td>
                    </tr>
                    <tr><td colspan="2" style="border-top: 1px solid #d1fae5;"></td></tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Total Dibayar</td>
                        <td align="right" style="padding: 6px 0; font-size: 15px; font-weight: 700; color: #047857;">Rp {{ number_format($subscription->amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr><td colspan="2" style="border-top: 1px solid #d1fae5;"></td></tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Dibayar Pada</td>
                        <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #1f2937;">{{ $subscription->paid_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
        <tr>
            <td align="center">
                <a href="https://www.skincek.web.id"
                   style="display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 10px;">
                    Mulai Pakai SkinCek Pro
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 24px 0 0 0; font-size: 14px; color: #6b7280;">
        Nikmati scan tanpa batas, chat prioritas dengan dokter, dan semua fitur SkinCek Pro.
    </p>
@endsection

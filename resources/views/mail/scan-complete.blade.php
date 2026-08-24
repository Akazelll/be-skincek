@extends('mail.layouts.base')

@section('title', 'Hasil Scan Kulit')

@section('preheader', 'Hasil analisis kulitmu dengan AI sudah siap dilihat.')

@section('heading', 'Hasil Scan Kamu Sudah Siap ✨')

@section('subheading', 'Analisis AI selesai. Berikut ringkasannya.')

@section('content')
    <p style="margin: 0 0 16px 0; font-size: 15px;">Halo {{ $history->user->full_name }},</p>
    <p style="margin: 0 0 20px 0; font-size: 15px;">
        Analisis kulit wajahmu menggunakan model <strong>{{ $history->model_used }}</strong> telah selesai:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #f0fdf4; border-radius: 12px; padding: 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Masalah Terdeteksi</td>
                        <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #047857;">{{ $history->predicted_class }}</td>
                    </tr>
                    <tr><td colspan="2" style="border-top: 1px solid #d1fae5;"></td></tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Tingkat Keyakinan</td>
                        <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #1f2937;">{{ number_format($history->confidence * 100, 1) }}%</td>
                    </tr>
                    <tr><td colspan="2" style="border-top: 1px solid #d1fae5;"></td></tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #6b7280;">Tingkat Keparahan</td>
                        <td align="right" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #1f2937;">{{ ucfirst($history->severity_level->value) }}</td>
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
                    Lihat Hasil Lengkap
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 24px 0 0 0; font-size: 13px; color: #9ca3af; background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 10px 14px; border-radius: 0 8px 8px 0;">
        {{ config('services.ml.disclaimer') }}
    </p>

    <p style="margin: 20px 0 0 0; font-size: 14px; color: #6b7280;">
        Buka aplikasi SkinCek untuk melihat detail lengkap dan berkonsultasi langsung dengan dokter kulit.
    </p>
@endsection

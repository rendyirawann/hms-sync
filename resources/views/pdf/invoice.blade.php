<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $booking->booking_code }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 22px; margin: 0; color: #0f172a; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
        .items th { background: #f1f5f9; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; color: #475569; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; }
        .totals td { padding: 4px 8px; }
        .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #0f172a; padding-top: 8px; }
        .stamp { display: inline-block; padding: 4px 12px; border: 2px solid; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .paid { color: #16a34a; border-color: #16a34a; }
        .unpaid { color: #ea580c; border-color: #ea580c; }
    </style>
</head>
<body>
    @php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

    <table class="head">
        <tr>
            <td>
                <h1>{{ $hotel->name }}</h1>
                <div class="muted">{{ $hotel->address }}</div>
                <div class="muted">{{ $hotel->phone }} {{ $hotel->email ? '· ' . $hotel->email : '' }}</div>
            </td>
            <td class="right">
                <div style="font-size:18px;font-weight:bold;">INVOICE</div>
                <div>No. {{ $booking->booking_code }}</div>
                <div class="muted">Tanggal {{ now()->translatedFormat('d F Y') }}</div>
                <div style="margin-top:8px">
                    <span class="stamp {{ $booking->balance_due <= 0 ? 'paid' : 'unpaid' }}">{{ $booking->balance_due <= 0 ? 'LUNAS' : 'BELUM LUNAS' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table style="margin: 20px 0;">
        <tr>
            <td style="width:50%; padding-right:8px;">
                <div class="box">
                    <div class="muted">Ditagihkan kepada</div>
                    <strong>{{ $booking->guest_display_name }}</strong><br>
                    {{ $booking->guest_contact_email }}<br>
                    {{ $booking->guest_phone }}
                </div>
            </td>
            <td style="width:50%; padding-left:8px;">
                <div class="box">
                    <div class="muted">Detail menginap</div>
                    <strong>{{ $booking->roomType->name }}</strong>{{ $booking->reservation ? ' · Kamar ' . $booking->reservation->room->room_number : '' }}<br>
                    {{ $booking->check_in_date->translatedFormat('d M Y') }} – {{ $booking->check_out_date->translatedFormat('d M Y') }} ({{ $booking->nights }} malam)<br>
                    {{ $booking->guests }} tamu
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>Keterangan</th><th class="right">Jumlah</th><th class="right">Harga</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
            <tr><td>Kamar {{ $booking->roomType->name }}</td><td class="right">{{ $booking->nights }} malam</td><td class="right">{{ $rp($booking->room_total / max(1, $booking->nights)) }}</td><td class="right">{{ $rp($booking->room_total) }}</td></tr>
            @foreach ($booking->charges as $charge)
                <tr><td>{{ $charge->description }}</td><td class="right">{{ $charge->quantity }}</td><td class="right">{{ $rp($charge->unit_price) }}</td><td class="right">{{ $rp($charge->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 50%; margin-left: 50%; margin-top: 10px;">
        @if ((float) $booking->discount > 0)
            <tr><td>Diskon {{ $booking->promoCode?->code }}</td><td class="right">- {{ $rp($booking->discount) }}</td></tr>
        @endif
        <tr><td>Service charge</td><td class="right">{{ $rp($booking->service_charge) }}</td></tr>
        <tr><td>Pajak</td><td class="right">{{ $rp($booking->tax) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $rp($booking->grand_total) }}</td></tr>
        <tr><td>Dibayar</td><td class="right">{{ $rp($booking->amount_paid) }}</td></tr>
        <tr><td><strong>Sisa tagihan</strong></td><td class="right"><strong>{{ $rp($booking->balance_due) }}</strong></td></tr>
    </table>

    @if ($booking->payments->where('status', 'paid')->isNotEmpty())
        <div style="margin-top: 20px;">
            <div class="muted" style="margin-bottom:4px;">Riwayat pembayaran</div>
            <table class="items">
                @foreach ($booking->payments->where('status', 'paid') as $payment)
                    <tr><td>{{ $payment->paid_at?->translatedFormat('d M Y H:i') }}</td><td>{{ $payment->method_label }}</td><td>{{ $payment->reference }}</td><td class="right">{{ $rp($payment->amount) }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    <p class="muted" style="margin-top: 30px; text-align:center;">Terima kasih telah memilih {{ $hotel->name }}. Dokumen ini dibuat otomatis dan sah tanpa tanda tangan.</p>
</body>
</html>

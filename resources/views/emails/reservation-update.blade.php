<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $details['title'] }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f9f7f5;color:#29212a;font:15px/1.7 Arial,Helvetica,sans-serif;">
@php($money = fn ($kobo) => 'NGN '.number_format($kobo / 100, 2))
@php($localDate = fn ($date) => \Carbon\CarbonImmutable::parse($date)->timezone(config('property.timezone'))->format('l, j F Y, g:i A').' (Nigeria time)')
<table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="100%" style="width:100%;max-width:600px;background:white;border:1px solid #e8e0e3;border-top:6px solid #4a0e2e;" cellpadding="0" cellspacing="0">
<tr><td style="padding:28px;text-align:center"><img src="{{ $message->embed(public_path('images/insignia-logo.png')) }}" width="170" alt="Insignia Court"></td></tr>
<tr><td style="padding:0 28px 28px"><h1 style="font:28px Georgia,serif;color:#4a0e2e">{{ $details['title'] }}</h1>
<p>Dear {{ $customerName }},</p><p>{{ $details['update_text'] }}</p>
<table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;font-size:14px">
@foreach (['Booking reference' => $details['number'], 'Status' => $details['stay_stage'], 'Room / suite' => $details['room_name'], 'Room category' => $details['room_category'], 'Scheduled check-in' => $localDate($details['check_in_at']), 'Scheduled check-out' => $localDate($details['check_out_at']), 'Stay total' => $money($details['total_kobo']), 'Verified payments' => $money($details['total_paid_kobo']), 'Balance before check-in' => $money($details['balance_kobo'])] as $label => $value)
<tr><th align="left" style="border-bottom:1px solid #eee;font-weight:normal;color:#70656a">{{ $label }}</th><td align="right" style="border-bottom:1px solid #eee">{{ $value }}</td></tr>
@endforeach
@if ($details['hold_expires_at'])<tr><th align="left">Temporary hold expires</th><td align="right">{{ $details['hold_expires_at'] }}</td></tr>@endif
</table><p>For assistance, reply to this email or contact Guest Relations on +{{ $details['phone'] }}.</p>
<p>Warm regards,<br><strong>Guest Relations</strong><br>Insignia Court</p></td></tr>
</table></td></tr></table></body></html>

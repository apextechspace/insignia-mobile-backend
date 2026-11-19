<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $details['title'] }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f9f7f5;color:#29212a;font:15px/1.7 Arial,Helvetica,sans-serif;">
@php($money = fn ($kobo) => 'NGN '.number_format($kobo / 100, 2))
@php($localDate = fn ($date) => \Carbon\CarbonImmutable::parse($date)->timezone(config('property.timezone'))->format('l, j F Y, g:i A').' (Nigeria time)')
<table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="600" style="max-width:100%;background:white;border:1px solid #e8e0e3;border-top:6px solid #4a0e2e;" cellpadding="0" cellspacing="0">
<tr><td style="padding:28px;text-align:center"><img src="{{ $message->embed(public_path('images/insignia-logo.png')) }}" width="170" alt="Insignia Court"></td></tr>
<tr><td style="padding:0 28px 28px">
<h1 style="font:28px Georgia,serif;color:#4a0e2e">{{ $details['title'] }}</h1>
<p>Dear {{ $customerName }},</p>
@if ($details['full'])
<p>Thank you for choosing Insignia Court. We’ve received your full accommodation payment of {{ $money($details['total_paid_kobo']) }} and are pleased to confirm your reservation.</p>
@elseif ($details['confirmed'])
<p>Thank you for choosing Insignia Court{{ $details['nights'] >= 7 ? ' for your extended stay' : '' }}. We’ve received a total deposit of {{ $money($details['total_paid_kobo']) }}{{ $details['night_based'] ? ', covering your first '.($details['nights'] >= 7 ? 'three nights' : 'night') : '' }}, and are pleased to confirm your reservation.</p>
@else
<p>Thank you. We’ve verified your payment of {{ $money($details['amount_received_kobo']) }}. Your reservation is not yet confirmed because the required deposit has not been reached. Please contact Guest Relations to complete your deposit.</p>
@endif
<table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;font-size:14px">
@foreach (['Booking reference' => $details['number'], 'Room category' => $details['room_category'], 'Room / suite' => $details['room_name'], 'Check-in' => $localDate($details['check_in_at']), 'Check-out' => $localDate($details['check_out_at']), 'Length of stay' => $details['nights'].' '.($details['nights'] === 1 ? 'Night' : 'Nights'), 'Payment reference' => $details['reference'], 'Payment method' => $details['method'], 'Payment date' => $details['date']] as $label => $value)
<tr><th align="left" style="border-bottom:1px solid #eee;font-weight:normal;color:#70656a">{{ $label }}</th><td align="right" style="border-bottom:1px solid #eee">{{ $value }}</td></tr>
@endforeach
@if (empty($details['confirmation_only']))<tr><th align="left">This payment received</th><td align="right">{{ $money($details['amount_received_kobo']) }}</td></tr>@endif
<tr><th align="left">Accommodation total</th><td align="right">{{ $money($details['total_kobo']) }}</td></tr>
<tr><th align="left">Total payments verified</th><td align="right">{{ $money($details['total_paid_kobo']) }}</td></tr>
<tr style="background:#4a0e2e;color:white"><th align="left">{{ $details['full'] ? 'Accommodation balance' : 'Balance due at check-in' }}</th><td align="right" style="font-weight:bold">{{ $money($details['balance_kobo']) }}</td></tr>
</table>
@if (!$details['full'])<p>Please arrange the remaining payment with Guest Relations. Full verified payment is required before check-in can be completed.</p>@endif
@if ($details['confirmed'] && !empty($details['terms']['cancellation_enabled']))
@php($charge = min($details['total_paid_kobo'], $details['terms']['cancellation_charge_kobo']))
<h2 style="font-size:18px;color:#4a0e2e">If your plans change</h2>
@if (!empty($details['terms']['booked_after_deadline']))<p>This reservation was made after its free-cancellation deadline of {{ $localDate($details['terms']['refund_deadline']) }}. The late-cancellation terms below apply.</p>@endif
<p>Please contact us by {{ $localDate($details['terms']['refund_deadline']) }} to request a full refund of your accommodation payment. For later cancellations or no-shows, we retain {{ $money($charge) }}, covering up to your first {{ $details['terms']['cancellation_charge_nights'] === 1 ? 'night' : 'three nights' }}. The remaining refundable amount would be {{ $money(max(0, $details['total_paid_kobo'] - $charge)) }}.</p>
<p>Refunds are arranged by management after reviewing your request; they are not processed automatically.</p>
@if (!empty($details['terms']['release_review_at']))
<p>Unless you arrange a later arrival with us, management may release the room for the remainder of your stay from {{ $localDate($details['terms']['release_review_at']) }}.</p>
@endif
@endif
<p>If you expect to arrive late, please reply to this email or call +{{ $details['phone'] }} so we can arrange your check-in.</p>
<p>We look forward to welcoming you.</p><p>Warm regards,<br><strong>Guest Relations</strong><br>Insignia Court</p>
</td></tr><tr><td style="padding:18px;text-align:center;background:#f3eef0;color:#4a0e2e">Please keep this email as your payment record.<br><a href="{{ rtrim(config('services.guest_access.frontend_url'), '/') }}/access-stay" style="color:#4a0e2e">Access My Stay — request a secure sign-in link</a></td></tr>
</table></td></tr></table></body></html>

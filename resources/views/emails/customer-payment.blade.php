<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $details['title'] }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f9f7f5;color:#2d2d2d;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #e8e0e3;">
<tr><td style="padding:32px;text-align:center;border-top:6px solid #4a0e2e;">
<img src="{{ $message->embed(public_path('images/insignia-logo.png')) }}" alt="Insignia Courts" width="170" style="max-width:100%;height:auto;">
</td></tr>
<tr><td style="padding:0 32px 24px;">
<p style="color:#4a0e2e;font-size:12px;letter-spacing:2px;text-transform:uppercase;">{{ $details['receipt'] ? 'Payment verified' : 'Awaiting payment' }}</p>
<h1 style="font-family:Georgia,serif;font-size:30px;color:#4a0e2e;margin:12px 0;">{{ $details['title'] }}</h1>
<p>Hello {{ $customerName }},</p>
<p style="line-height:1.7;">{{ $details['receipt'] ? 'Thank you. Your payment has been verified. Please keep this receipt for your records.' : 'We have received your room booking request. Your reservation will be confirmed after payment is verified. This email is not a payment receipt.' }}</p>
<table width="100%" cellpadding="12" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
@foreach (['Booking / order' => $details['number'], 'Description' => $details['description'], 'Reference' => $details['reference'], 'Payment method' => $details['method'], 'Date' => $details['date']] as $label => $value)
<tr><th scope="row" align="left" style="border-bottom:1px solid #eee;font-weight:normal;color:#70656a;">{{ $label }}</th><td align="right" style="border-bottom:1px solid #eee;overflow-wrap:anywhere;">{{ $value }}</td></tr>
@endforeach
@if ($details['check_in'])
<tr><th scope="row" align="left" style="font-weight:normal;">Check-in / Check-out</th><td align="right">{{ $details['check_in'] }} / {{ $details['check_out'] }}</td></tr>
@endif
<tr style="background:#4a0e2e;color:#fff;"><th scope="row" align="left">{{ $details['receipt'] ? 'Total paid' : 'Amount due' }}</th><td align="right" style="font-size:22px;font-weight:bold;">NGN {{ $details['amount'] }}</td></tr>
</table>
<p style="font-size:13px;line-height:1.7;color:#70656a;margin-top:24px;">For assistance, contact our guest relations team and quote the reference above.</p>
</td></tr>
<tr><td style="padding:24px 32px;background:#f3eef0;color:#4a0e2e;text-align:center;font-size:12px;">INSIGNIA COURTS<br><span style="line-height:2;">Thank you for choosing us.</span></td></tr>
</table></td></tr></table>
</body></html>

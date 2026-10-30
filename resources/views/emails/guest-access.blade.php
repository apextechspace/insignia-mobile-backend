<!doctype html><html><body style="margin:0;background:#f6f2ef;font-family:Arial,sans-serif;color:#29212a">
<table role="presentation" width="100%"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" style="max-width:560px;background:white;border-radius:16px"><tr><td style="padding:32px">
<img src="{{ $message->embed(public_path('images/insignia-logo.png')) }}" alt="Insignia Courts" width="150">
<h1 style="color:#4b1d3f;font-size:24px">Access your stay</h1>
<p>Continue your booking or return to your guest dashboard. No password is required.</p>
<p>Your verification code:</p><p style="font-size:32px;letter-spacing:6px;font-weight:bold">{{ $code }}</p>
<p>Enter this code in the app, or open the secure link below and confirm your sign-in.</p>
<p style="padding:16px 0"><a href="{{ $url }}" style="background:#4b1d3f;color:white;padding:14px 22px;border-radius:8px;text-decoration:none">Manage My Stay</a></p>
<p>This code and link expire after 15 minutes and can be used once. Never share them. If you did not request this email, ignore it.</p>
</td></tr></table></td></tr></table></body></html>

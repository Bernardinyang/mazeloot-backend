@php
    $footerAddress = config('mail.footer.address', []);
    $logoUrl = asset('logos/mazelootPrimaryLogo.svg');
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="color-scheme" content="light" />
<title>{{ config('app.name') }}</title>
<link rel="preload" as="image" href="{{ $logoUrl }}" />
<style type="text/css">
body,a,p,h1,h2,h3 { margin: 0; }
a { color: #0ea5e9; text-decoration: none; }
img { border: 0; outline: none; max-width: 100%; }
h1 { font-size: 26px; line-height: 1.3; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
h2 { font-size: 18px; font-weight: 600; color: #1e293b; }
h3 { font-size: 16px; font-weight: 600; color: #334155; }
p { font-size: 16px; line-height: 1.65; margin-top: 16px; margin-bottom: 16px; color: #475569; }
.subcopy { border-top: 1px solid #e2e8f0; margin-top: 28px; padding-top: 24px; font-size: 14px; color: #64748b; }
.action { margin: 28px 0; text-align: center; }
.button { display: inline-block; padding: 14px 24px; background-color: #0ea5e9; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
.button-error { background-color: #ef4444; }
.panel { border-left: 4px solid #0ea5e9; margin: 24px 0; background-color: #f0f9ff; padding: 18px; border-radius: 0 8px 8px 0; }
.break-all { word-break: break-all; }
@media only screen and (max-width: 620px) { .card { width: 100% !important; max-width: 100% !important; } .card-inner { padding: 32px 24px !important; } }
</style>
{!! $head ?? '' !!}
</head>
<body style="background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 24px;">
<div style="display:none; overflow:hidden; line-height:1px; opacity:0; max-height:0; max-width:0;">{{ config('app.name') }}&#847;&#8203;&#8199;&#8198;&#8197;&#8196;&#8195;&#8194;&#8193;&#8192;</div>
<table border="0" width="100%" cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td style="background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 16px; line-height: 24px; padding: 32px 16px;">
<img alt="{{ config('app.name') }}" src="{{ $logoUrl }}" width="184" height="75" style="display: block; outline: none; border: none; text-decoration: none; margin-right: auto; margin-left: auto; margin-bottom: 24px; margin-top: 0;" />
<table class="card" align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 37.5em; width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 10px 20px -5px rgba(0,0,0,0.06);">
<tr>
<td style="border-top: 4px solid #0ea5e9;"></td>
</tr>
<tr>
<td class="card-inner" style="padding: 40px 44px;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
@if(!empty($subcopy))
<div class="subcopy">{!! $subcopy !!}</div>
@endif
</td>
</tr>
</table>
<table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 37.5em; width: 600px; margin-top: 28px;">
<tr>
<td style="padding: 20px 24px; background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
<table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding-right: 20px; text-align: right;"><a href="{{ config('app.url') }}" style="color: #0ea5e9; text-decoration: none; font-size: 14px; font-weight: 500;" target="_blank">Unsubscribe</a></td>
<td style="text-align: left;"><a href="{{ config('app.url') }}" style="color: #0ea5e9; text-decoration: none; font-size: 14px; font-weight: 500;" target="_blank">Manage preferences</a></td>
</tr>
</table>
<p style="font-size: 13px; line-height: 1.5; margin: 16px 0 0 0; text-align: center; color: #94a3b8;">
@if(!empty($footerAddress))
{{ implode(' ', $footerAddress) }}
@else
{{ config('app.name') }} &middot; {{ config('mail.from.address', '') }}
@endif
</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

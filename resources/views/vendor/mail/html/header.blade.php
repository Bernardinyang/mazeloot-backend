@props(['url'])
<tr>
<td class="logo-container" width="600" style="padding: 0; background-color: #004829; border-radius: 8px 8px 0 0; border-bottom: 2px solid #227353;">
<table width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding: 20px; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo" style="max-width: 200px; height: auto;">
@else
<img src="{{ asset('logos/mazelootPrimaryLogo.svg') }}" alt="{{ config('app.name') }}" class="logo" style="max-width: 200px; height: auto;">
@endif
</a>
</td>
</tr>
</table>
</td>
</tr>

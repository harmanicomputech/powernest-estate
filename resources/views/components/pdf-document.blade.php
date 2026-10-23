@props(['estate', 'title', 'subtitle' => null, 'badge' => null])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 36px 40px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #1b1f1d; font-size: 10.5px; line-height: 1.45; }
    .brand { color: #154634; font-size: 16px; font-weight: bold; }
    .brand small { display: block; color: #b98a2d; font-size: 8px; letter-spacing: 2px; font-weight: bold; }
    .muted { color: #6a716c; }
    .title { font-size: 20px; font-weight: bold; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { vertical-align: top; padding: 0; }
    .box { border: 1px solid #e5e2da; border-radius: 6px; padding: 10px 12px; }
    .items th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #6a716c; background: #f6f5f1; padding: 7px 8px; border-bottom: 1px solid #e5e2da; }
    .items td { padding: 8px; border-bottom: 1px solid #efede7; }
    .right { text-align: right; }
    .totals td { padding: 4px 8px; }
    .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #154634; padding-top: 8px; }
    .badge { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge-paid { background: #e3f4ea; color: #18794e; }
    .badge-due { background: #fdf1dc; color: #a35a00; }
    .badge-void, .badge-reversed { background: #fdecea; color: #b42318; }
    .token { font-family: 'DejaVu Sans Mono', monospace; font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #154634; }
    .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8.5px; color: #9aa09b; text-align: center; }
    .bar { height: 4px; background: #154634; margin-bottom: 18px; }
</style>
</head>
<body>
<div class="bar"></div>
<table class="meta">
    <tr>
        <td style="width:55%">
            <div class="brand">PowerNest<small>ESTATE</small></div>
            <div style="margin-top:8px"><strong>{{ $estate->name }}</strong><br><span class="muted">{{ $estate->address }}{{ $estate->contact_email ? ' · '.$estate->contact_email : '' }}{{ $estate->contact_phone ? ' · '.$estate->contact_phone : '' }}</span></div>
        </td>
        <td class="right">
            <p class="title">{{ $title }}</p>
            <div class="muted" style="margin-top:4px">{{ $subtitle }}</div>
            @if ($badge)<div style="margin-top:8px"><span class="badge badge-{{ $badge[1] }}">{{ $badge[0] }}</span></div>@endif
        </td>
    </tr>
</table>
<div style="margin-top:22px">{{ $slot }}</div>
<div class="footer">Generated {{ now()->timezone($estate->timezone())->format('j M Y, g:ia') }} · PowerNest Estate @if($estate->is_demo)· DEMO DATA — NOT A VALID DOCUMENT @endif</div>
</body>
</html>

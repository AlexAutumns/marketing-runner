<!doctype html>
<html>

<body style="font-family:Segoe UI, Arial; font-size:14px;">
    <p>Hi {{ $firstName ?? 'there' }}!</p>

    <p>This is a demo email from our <b>Marketing Runner</b>.</p>

    <p><b>Tracking ID:</b> {{ $trackingId }}</p>
    <p><b>Step:</b> {{ $stepKey }}</p>

    <p>
        CTA (demo):
        <a href="{{ $ctaUrl }}">Open Link</a>
    </p>

    <p style="color:#666; margin-top:16px;">
        (Log-mode demo — not using a real provider yet)
    </p>
</body>

</html>

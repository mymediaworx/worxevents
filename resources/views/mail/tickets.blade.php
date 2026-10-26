<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $guestName }},</p>
    <p>Your registration for {{ $eventTitle }} is confirmed. Reference {{ $reference }}.</p>
    <p>The attached PDF and SVG are your tickets. Each QR code is a random token. Keep the attachments. This email body does not include the token.</p>
    <p><a href="{{ $orderUrl }}">Look up this registration</a></p>
</body>
</html>

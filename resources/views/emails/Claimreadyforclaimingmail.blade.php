<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Claim Ready for Claiming</title>
</head>
<body style="font-family: sans-serif; color: #0F212F; background: #F8FAF8; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #EAF1EC; border-radius: 16px; padding: 24px;">
        <h2 style="color: #116D3E; margin-top: 0;">Your Claim is Ready for Claiming</h2>

        <p>Hi {{ $farmerName }},</p>

        <p>
            Good news — your indemnity claim (Claim #{{ $claimId }}) for
            <strong>{{ $farmName }}</strong> ({{ $cropType }}) has been approved by PCIC
            and is now ready for claiming.
        </p>

        @if($claimSchedule && $claimVenue)
            <div style="background: #F1F6F2; border-radius: 12px; padding: 16px; margin: 16px 0;">
                <p style="margin: 0 0 4px 0;"><strong>Claiming Date:</strong> {{ $claimSchedule }}</p>
                <p style="margin: 0;"><strong>Venue:</strong> {{ $claimVenue }}</p>
            </div>
        @else
            <p>Your claiming date and venue will be announced soon. You'll receive another notification once it's set.</p>
        @endif

        <p>Please bring a valid ID and any documents requested by your MAO office.</p>

        <p style="margin-top: 24px; font-size: 0.85rem; color: #5c6b64;">
            This is an automated message from the AgriSure crop insurance system.
        </p>
    </div>
</body>
</html>
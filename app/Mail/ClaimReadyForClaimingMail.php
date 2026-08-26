<?php

namespace App\Mail;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ClaimReadyForClaimingMail extends Mailable
{
    use Queueable, SerializesModels;

    public Claim $claim;

    public function __construct(Claim $claim)
    {
        $this->claim = $claim;
    }

    public function build()
    {
        // Reload relations safely for runtime rendering
        $claim = $this->claim->loadMissing([
            'damageReport.insuranceApplication.farm.farmerProfile.user'
        ]);

        $application = $claim->damageReport?->insuranceApplication;
        $farm        = $application?->farm;
        $farmerUser  = $farm?->farmerProfile?->user;

        $farmerName = trim(
            ($farmerUser->first_name ?? '') . ' ' . ($farmerUser->last_name ?? '')
        ) ?: 'Farmer';

        $farmName = $farm->farm_name ?? 'your registered farm';
        $cropType = $farm->crop_type ?? '—';

        $scheduleText = $claim->claim_schedule
            ? Carbon::parse($claim->claim_schedule)->format('F j, Y')
            : 'To be announced';

        $venueText = $claim->claim_venue ?? 'To be announced';

        return $this->subject('Your Crop Insurance Claim is Ready for Claiming')
            ->html("
                <div style=\"font-family: sans-serif; padding: 20px; color: #0F212F;\">
                    <h2 style=\"color: #116D3E;\">Your Claim is Ready for Claiming</h2>
                    <p>Hi {$farmerName},</p>
                    <p>Good news! Your indemnity claim has been approved by PCIC and is now ready for claiming.</p>
                    <div style=\"background: #F1F6F2; border-radius: 8px; padding: 14px; margin: 16px 0;\">
                        <p style=\"margin: 0 0 6px 0;\"><strong>Claiming Date:</strong> {$scheduleText}</p>
                        <p style=\"margin: 0;\"><strong>Venue:</strong> {$venueText}</p>
                    </div>
                    <p>Please bring a valid ID and any required documents to your local MAO office.</p>
                    <p>Thank you for using AgriSure!</p>
                </div>
            ");
    }
}
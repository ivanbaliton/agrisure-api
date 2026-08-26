<?php

namespace App\Mail;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ClaimReadyForClaimingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Claim $claim;

    public function __construct(Claim $claim)
    {
        $this->claim = $claim;
    }

    public function build()
    {
        // Reload relationships when executed asynchronously by queue workers
        $this->claim->loadMissing([
            'damageReport.insuranceApplication.farm.farmerProfile.user'
        ]);

        $application = $this->claim->damageReport?->insuranceApplication;
        $farm        = $application?->farm;
        $farmerUser  = $farm?->farmerProfile?->user;

        $farmerName = trim(
            ($farmerUser->first_name ?? '') . ' ' . ($farmerUser->last_name ?? '')
        ) ?: 'Farmer';

        return $this->subject('Your Crop Insurance Claim is Ready for Claiming')
            ->view('emails.claim-ready-for-claiming')
            ->with([
                'farmerName'    => $farmerName,
                'farmName'      => $farm->farm_name ?? '—',
                'cropType'      => $farm->crop_type ?? '—',
                'claimSchedule' => $this->claim->claim_schedule
                    ? Carbon::parse($this->claim->claim_schedule)->format('F j, Y')
                    : null,
                'claimVenue'    => $this->claim->claim_venue,
                'claimId'       => $this->claim->id,
            ]);
    }
}
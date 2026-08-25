<?php

namespace App\Mail;

use App\Models\InsuranceApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationForwardedToPcicMail extends Mailable
{
    use Queueable, SerializesModels;

    public InsuranceApplication $application;

    public function __construct(InsuranceApplication $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        $farmName = $this->application->farm->farm_name ?? 'your registered farm';

        return $this->subject('Insurance Application Forwarded to PCIC')
                    ->html("
                        <h2>Application Forwarded to PCIC</h2>
                        <p>Hello,</p>
                        <p>Your crop insurance application for <strong>{$farmName}</strong> has been endorsed by MAO and submitted to PCIC.</p>
                        <ul>
                            <li><strong>Application ID:</strong> #{$this->application->id}</li>
                            <li><strong>Crop Variety:</strong> {$this->application->variety}</li>
                            <li><strong>Insured Area:</strong> {$this->application->insured_area} ha</li>
                        </ul>
                        <p>Thank you for using AgriSure!</p>
                    ");
    }
}
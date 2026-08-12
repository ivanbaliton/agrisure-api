<x-mail::message>
# Hello, {{ $farmerName }}!

Good news! You have been selected to receive farm supply assistance from the Municipal Agriculture Office (MAO) for the upcoming distribution event: **{{ $event->title ?? $event->name ?? 'Farm Supply Assistance' }}**.

📅 **Distribution Date:** {{ $event->distribution_date ? \Carbon\Carbon::parse($event->distribution_date)->format('F d, Y') : 'Scheduled Date' }}
⏰ **Time:** {{ $event->distribution_time ? \Carbon\Carbon::parse($event->distribution_time)->format('g:i A') : 'Scheduled Time' }}
📍 **Venue:** {{ $event->venue ?? 'Barangay Hall' }}

Please make sure to head to the venue at your scheduled time. Remember to bring a **Valid ID** and your **RSBSA Reference Number** to claim your assigned inputs.

<x-mail::button :url="url('/dashboard')">
View My Allocation
</x-mail::button>

Thank you for partnering with AgriSure and the MAO!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
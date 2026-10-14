<x-mail::message>
# {{ __('Tindak Lanjut Alert') }} — {{ $followUp->zone->label() }}

**{{ $scopeLabel }}** · {{ sprintf('%02d/%d', $followUp->fiscal_month, $followUp->fiscal_year) }}
· {{ __('Indeks') }}: **{{ number_format((float) $followUp->index_pct, 1, ',', '.') }}%**

<x-mail::table>
| 5W2H | {{ __('Detail') }} |
| :--- | :--- |
| **What** | {{ $followUp->what_text }} |
| **Why** | {{ $followUp->why_text }} |
| **When** | {{ $followUp->when_date?->format('d/m/Y') }} |
| **Where** | {{ $followUp->where_location ?: '-' }} |
| **Who** | {{ $followUp->whoDepartment?->name }} |
| **How** | {{ $followUp->how_text }} |
| **How much** | {{ $costLabel }} · {{ $hoursLabel }} {{ __('jam') }} |
</x-mail::table>

{{ __('Dikirim oleh') }} {{ $followUp->sender?->name }}
@if ($followUp->sent_at)
{{ __('pada') }} {{ $followUp->sent_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB.
@endif

<x-mail::button :url="$dashboardUrl">
{{ __('Buka Dashboard') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>

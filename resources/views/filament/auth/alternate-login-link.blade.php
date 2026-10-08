@php
    $isMerchantPanel = filament()->getId() === 'merchant';
@endphp

<div class="mt-2 text-center">
    <x-filament::link :href="$isMerchantPanel ? route('filament.user.auth.login') : route('filament.merchant.auth.login')">
        {{ $isMerchantPanel ? 'Staff login' : 'Merchant login' }}
    </x-filament::link>
</div>

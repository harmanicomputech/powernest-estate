{{-- Opens WhatsApp with the token message ready to send (WhatsApp app on phones, WhatsApp Web elsewhere). --}}
@php
    $tokenText = trim(chunk_split($purchase->token->token, 4, ' '));
    $message = "⚡ Electricity token\nMeter: {$purchase->meter_number}\nToken: {$tokenText}"
        .($purchase->token->units ? "\nUnits: {$purchase->token->units} {$purchase->token->unit_label}" : '')
        ."\nAmount: ".money($purchase->vend_amount)."\nRef: {$purchase->reference}";
@endphp
<a href="https://wa.me/?text={{ rawurlencode($message) }}" target="_blank" rel="noopener" data-no-loading data-ripple
   class="{{ $class ?? '' }} inline-flex h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-[#25D366] px-4 text-[0.9375rem] font-semibold text-[#06361a] shadow-[0_1px_2px_rgb(0_0_0/0.2)] hover:bg-[#20bd5a]">
    <svg viewBox="0 0 24 24" class="size-[1.125rem]" fill="currentColor" aria-hidden="true"><path d="M12.04 2a9.9 9.9 0 0 0-8.5 15l-1.4 5.1 5.2-1.4A9.9 9.9 0 1 0 12.04 2Zm0 18.1a8.2 8.2 0 0 1-4.2-1.15l-.3-.18-3.1.82.83-3-.2-.31a8.2 8.2 0 1 1 6.97 3.82Zm4.5-6.1c-.25-.12-1.46-.72-1.69-.8-.23-.09-.39-.13-.56.12-.16.25-.64.8-.79.97-.14.16-.29.18-.54.06a6.7 6.7 0 0 1-3.32-2.9c-.25-.43.25-.4.72-1.33.08-.16.04-.3-.02-.43l-.76-1.83c-.2-.48-.4-.41-.56-.42h-.47a.9.9 0 0 0-.66.31 2.77 2.77 0 0 0-.86 2.06 4.8 4.8 0 0 0 1 2.55 11 11 0 0 0 4.21 3.72c1.57.68 2.19.74 2.97.62.48-.07 1.46-.6 1.67-1.18.2-.58.2-1.08.14-1.18-.06-.1-.22-.16-.47-.28Z"/></svg>
    Send on WhatsApp
</a>

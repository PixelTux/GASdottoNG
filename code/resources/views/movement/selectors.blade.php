<input type="hidden" name="sender_type" value="{{ $sender_type }}" />
<input type="hidden" name="target_type" value="{{ $target_type }}" />

@if(!empty($senders))
    @if($senders->count() == 1)
        <input type="hidden" name="sender_id" value="{{ $senders->first()->id }}">
    @else
        <x-larastrap::select-model name="sender_id" :label="$sender_type::commonClassName()" :options="$senders" />
    @endif
@endif

@if(!empty($targets))
    @if($targets->count() == 1)
        <input type="hidden" name="target_id" value="{{ $targets->first()->id }}">
    @else
        <x-larastrap::select-model name="target_id" :label="$target_type::commonClassName()" :options="$targets" />
    @endif
@endif

@if($fixed)
    <x-larastrap::price name="amount" :label="_i('Valore')" :value="$fixed" readonly />
@else
    @include('commons.pricecurrency', ['allow_negative' => $allow_negative])
@endif

<x-larastrap::radios name="method" :label="_i('Metodo')" :options="$payments" :value="$default_method" required />
<x-larastrap::text name="identifier" :label="_i('Identificativo')" />
<x-larastrap::textarea name="notes" :label="_i('Note')" :value="$default_notes" />

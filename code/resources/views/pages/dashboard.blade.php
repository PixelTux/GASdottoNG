@extends('app')

@section('content')

@if($notifications->isEmpty() == false)
    <div class="row mb-3" id="home-notifications">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    {{ _i('Notifiche') }}
                </div>
                <div class="card-body">
                    @foreach($notifications as $notify)
                        <x-larastrap::suggestion classes="alert-dismissible fade show">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <input type="hidden" name="notification_id" value="{{ $notify->id }}" />

                            {!! $notify->formattedContent($currentuser) !!}

                            @if($notify->attachments->isEmpty() == false)
                                <hr>
                                @foreach($notify->attachments as $attachment)
                                    <a class="btn btn-info" href="{{ $attachment->download_url }}">
                                        {{ $attachment->name }} <i class="bi-download"></i>
                                    </a>
                                @endforeach
                            @endif
                        </x-larastrap::suggestion>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        @if(Gate::check('supplier.book', null))
            <div class="card mb-3">
                <div class="card-header">
                    <p class="float-end">
                        <a target="_blank" href="{{ url('ordini.xml') }}"><i class="bi-rss"></i></a>
                        <a target="_blank" href="{{ url('ordini.ics') }}"><i class="bi-calendar"></i></a>
                    </p>
                    {{ _i('Prenotazioni Aperte') }}
                </div>
                @if(count($opened) == 0)
                    <div class="card-body">
                        <x-larastrap::suggestion>
                            {{ _i('Non ci sono prenotazioni aperte.') }}
                        </x-larastrap::suggestion>
                    </div>
                @else
                    @include('order.homelist', ['orders' => $opened])
                @endif
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    {{ _i('Ordini in Consegna') }}
                </div>
                @if(count($shipping) == 0)
                    <div class="card-body">
                        <x-larastrap::suggestion>
                            {{ _i('Non ci sono ordini in consegna.') }}
                        </x-larastrap::suggestion>
                    </div>
                @else
                    @include('order.homelist', ['orders' => $shipping])
                @endif
            </div>
        @endif

        @if($currentgas->attachments->isEmpty() == false)
            <div class="card mb-3">
                <div class="card-header">
                    {{ _i('File Condivisi') }}
                </div>
                <div class="list-group list-group-flush">
                    @foreach($currentgas->attachments as $attachment)
                        <a href="{{ $attachment->download_url }}" class="list-group-item list-group-item-action" target="_blank">
                            {{ $attachment->name }}
                            <i class="bi-download float-end"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-md-6">
        @if($currentuser->isFriend() == false)
            @php
            $configuration = $currentgas->credit_home;
            @endphp

            @if($configuration['current_credit'] || $configuration['to_pay'])
                @php

                $balances = [];
                $default_currency = defaultCurrency();
                $currencies = App\Currency::enabled();

                if ($configuration['current_credit']) {
                    foreach ($currencies as $currency) {
                        $balances[$currency->id] = $currentuser->currentBalanceAmount($currency);
                    }
                }
                else {
                    $balances[$default_currency->id] = 0;
                }

                if ($configuration['to_pay']) {
                    $to_pay = $currentuser->pending_balance;
                    $to_pay_friend = [];

                    foreach($currentuser->friends as $friend) {
                        $tpf = $friend->pending_balance;
                        if ($tpf != 0) {
                            $to_pay += $tpf;
                            $to_pay_friend[$friend->printableName()] = printablePrice($tpf);
                        }
                    }
                }
                else {
                    $to_pay = 0;
                }

                @endphp

                <div class="alert {{ $balances[$default_currency->id] >= $to_pay ? 'alert-success' : 'alert-danger' }} text-right">
                    @if($configuration['current_credit'])
                        @foreach($currencies as $curr)
                            <p class="d-flex align-items-center justify-content-start">
                                <x-larastrap::pophelp classes="me-2" :text="_i('Questo è il tuo saldo attuale nei confronti del GAS.')" />
                                <span class="lead">{{ _i('Credito Attuale') }}: {{ printablePriceCurrency($balances[$curr->id], '.', $curr) }}</span>
                            </p>
                        @endforeach
                    @endif

                    @if($configuration['to_pay'])
                        <p class="d-flex align-items-center justify-content-start">
                            <x-larastrap::pophelp classes="me-2" :text="_i('Questo è il totale delle tue prenotazioni non ancora consegnate, e di cui non è dunque ancora stato registrato il pagamento.')" />
                            <span class="lead">{{ _i('Da Pagare') }}: {{ printablePriceCurrency($to_pay) }}</span>
                        </p>
                        @if(!empty($to_pay_friend))
                            <p>{{ _i('di cui') }}</p>
                            @foreach($to_pay_friend as $friend_name => $friend_amount)
                                <p>{{ $friend_name }} {{ printablePriceCurrency($friend_amount) }}</p>
                            @endforeach
                        @endif
                    @endif
                </div>

                <br>
            @endif
        @endif

        <div class="panel panel-default">
            <div class="panel-body">
                @include('dates.calendar')
            </div>
        </div>
    </div>
</div>

@endsection

@extends('app')

@section('content')

@if($notifications->isEmpty() == false)
    <div class="row" id="home-notifications">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h2 class="panel-title">{{ _i('Notifiche') }}</h2>
                </div>
                <div class="panel-body">
                    @foreach($notifications as $notify)
                        <div class="alert alert-info">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <input type="hidden" name="notification_id" value="{{ $notify->id }}" />

                            {!! nl2br($notify->content) !!}

                            @if($notify->attachments->isEmpty() == false)
                                <hr>
                                @foreach($notify->attachments as $attachment)
                                    <a class="btn btn-info" href="{{ $attachment->download_url }}">
                                        {{ $attachment->name }} <span class="glyphicon glyphicon-download" aria-hidden="true"></span>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        @if(Gate::check('supplier.book', null))
            <div class="panel panel-default">
                <div class="panel-heading">
                    <p class="pull-right">
                        <a target="_blank" href="{{ url('ordini.xml') }}"><span class="glyphicon glyphicon-signal" aria-hidden="true"></span></a>
                        <a target="_blank" href="{{ url('ordini.ics') }}"><span class="glyphicon glyphicon-calendar" aria-hidden="true"></span></a>
                    </p>
                    <h2 class="panel-title">{{ _i('Prenotazioni Aperte') }}</h2>
                </div>
                @if(count($opened) == 0)
                    <div class="panel-body">
                        <div class="alert alert-info" role="alert">
                            {{ _i('Non ci sono prenotazioni aperte.') }}
                        </div>
                    </div>
                @else
                    @include('order.homelist', ['orders' => $opened])
                @endif
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h2 class="panel-title">{{ _i('Ordini in Consegna') }}</h2>
                </div>
                @if(count($shipping) == 0)
                    <div class="panel-body">
                        <div class="alert alert-info" role="alert">
                            {{ _i('Non ci sono ordini in consegna.') }}
                        </div>
                    </div>
                @else
                    @include('order.homelist', ['orders' => $shipping])
                @endif
            </div>
        @endif

        @if($currentgas->attachments->isEmpty() == false)
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h2 class="panel-title">{{ _i('File Condivisi') }}</h2>
                </div>
                <div class="list-group">
                    @foreach($currentgas->attachments as $attachment)
                        <a href="{{ $attachment->download_url }}" class="list-group-item">
                            {{ $attachment->name }}
                            <span class="glyphicon glyphicon-download pull-right" aria-hidden="true"></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-md-6">
        @if($currentuser->isFriend() == false)
            <?php

            $current_balance = $currentuser->current_balance_amount;
            $to_pay = $currentuser->pending_balance;
            $to_pay_friend = [];

            foreach($currentuser->friends as $friend) {
                $tpf = $friend->pending_balance;
                if ($tpf != 0) {
                    $to_pay += $tpf;
                    $to_pay_friend[$friend->printableName()] = printablePrice($tpf);
                }
            }

            ?>

            <div class="alert {{ $current_balance >= $to_pay ? 'alert-success' : 'alert-danger' }} text-right">
                <p class="lead">{{ _i('Credito Attuale') }}: {{ printablePriceCurrency($current_balance) }}</p>
                <br>
                <p class="lead">{{ _i('Da Pagare') }}: {{ printablePriceCurrency($to_pay) }}</p>
                @if(!empty($to_pay_friend))
                    <p>{{ _i('di cui') }}</p>
                    @foreach($to_pay_friend as $friend_name => $friend_amount)
                        <p>{{ $friend_name }} {{ $friend_amount }} {{ $currentgas->currency }}</p>
                    @endforeach
                @endif
            </div>

            <br>

            <div class="panel panel-default">
                <div class="panel-body">
                    @include('dates.calendar')
                </div>
            </div>
        @endif
    </div>
</div>

@endsection

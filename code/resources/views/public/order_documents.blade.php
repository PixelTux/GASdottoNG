@extends('app')

@section('content')

<div class="row justify-content-center">
    <div class="col-12 col-md-6 mb-5">
        <p class="alert alert-info mb-4">
            {!! __('texts.orders.files.public.help', ['order' => $order->printableName()]) !!}
        </p>

        @foreach($links as $name => $link)
            <a class="btn btn-primary mb-2" href="{{ $link }}">{{ $name }}</a><br>
        @endforeach
    </div>
</div>

@include('commons.promofooter')

@endsection

@extends('layouts.app')

@section('content')
    <div class="page-head">
        <div>
            <h1>Check-in</h1>
            <p class="lede">Scan a ticket QR code or type the code. The camera uses the browser barcode detector when the device has one.</p>
        </div>
    </div>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif

    <form method="post" action="{{ route('check-in.scan') }}" class="stack panel" data-scan-form>
        @csrf
        <label>Event
            <select name="event_id" required>
                <option value="">Choose</option>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Ticket code
            <input name="token" autocomplete="off" required>
        </label>
        <button class="button" type="submit">Check in</button>
        <button class="button button-quiet" type="button" data-scan data-detector="BarcodeDetector">Scan with camera</button>
        <p id="scan-support" class="meta" hidden>This browser has no barcode detector. Type the code instead.</p>
        <video data-preview playsinline hidden></video>
    </form>

    <h2>Look up</h2>
    <form method="post" action="{{ route('check-in.lookup') }}" class="stack panel">
        @csrf
        <label>Event
            <select name="event_id" required>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Name, email, or order reference
            <input name="query" required>
        </label>
        <button class="button button-quiet" type="submit">Find</button>
    </form>

    @if ($matches->isNotEmpty())
        <h2>Matches</h2>
        <ul>
            @foreach ($matches as $attendee)
                <li>
                    {{ $attendee->name }} · {{ $attendee->email }} · {{ $attendee->order?->reference }}
                    @php $issued = $attendee->tickets->first(fn ($ticket) => $ticket->status->value === 'issued'); @endphp
                    @if ($issued)
                        <form method="post" action="{{ route('check-in.admit', $issued) }}">
                            @csrf
                            <input type="hidden" name="event_id" value="{{ $eventId }}">
                            <button type="submit">Check in</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Recent</h2>
    <ul>
        @forelse ($recent as $row)
            <li>
                {{ $row->result }} · {{ $row->event?->title }}
                @if ($row->result === 'checked_in' && $row->ticket && auth()->user()->can('events.manage'))
                    <form method="post" action="{{ route('check-in.reverse', $row->ticket) }}">
                        @csrf
                        <button class="button danger" type="submit">Reverse</button>
                    </form>
                @endif
            </li>
        @empty
            <li>No scans yet.</li>
        @endforelse
    </ul>
    <script src="{{ asset('js/check-in.js') }}?v={{ filemtime(public_path('js/check-in.js')) }}"></script>
@endsection

@php
    $artFile = public_path('images/demo/'.$event->slug.'.svg');
    $artUrl = is_file($artFile) ? asset('images/demo/'.$event->slug.'.svg').'?v='.filemtime($artFile) : null;
@endphp
<li class="card">
    <a href="{{ route('public.platform.event', ['organization' => $event->organization->slug, 'event' => $event->slug]) }}">
        @if ($artUrl)
            <img class="card-art" src="{{ $artUrl }}" alt="">
        @else
            <span class="card-art"></span>
        @endif
        <span class="card-body">
            <h2>{{ $event->title }}</h2>
            <p class="meta">{{ $event->organization->name }} · {{ $event->starts_at->timezone($event->timezone)->format('M j, Y g:i A') }}
                @if ($event->venue && $event->venue->city)
                    · {{ $event->venue->city }}
                @endif
            </p>
        </span>
    </a>
</li>

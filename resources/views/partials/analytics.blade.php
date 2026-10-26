@php($analytics = app(\App\Domain\Growth\AnalyticsHook::class)->src(request()))
@if (is_string($analytics))
    <script src="{{ $analytics }}" data-analytics="consented"></script>
@elseif (request()->cookie('worxevents_analytics') !== '1')
    <form method="post" action="{{ route('analytics.consent') }}">
        @csrf
        <button type="submit">Allow analytics</button>
    </form>
@endif

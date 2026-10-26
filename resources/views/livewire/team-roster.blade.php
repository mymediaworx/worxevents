<div>
    <ul data-member-count="{{ $members->count() }}">
        @foreach ($members as $member)
            <li>{{ $member->user->name }} · {{ $member->role->name }}</li>
        @endforeach
    </ul>
    @if ($members->hasPages())
        <nav aria-label="Members">
            @if ($members->previousPageUrl())
                <a href="{{ $members->previousPageUrl() }}">Previous</a>
            @endif
            <span>Page {{ $members->currentPage() }} of {{ $members->lastPage() }}</span>
            @if ($members->nextPageUrl())
                <a href="{{ $members->nextPageUrl() }}">Next</a>
            @endif
        </nav>
    @endif
</div>

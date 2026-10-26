<footer>
    <span>{{ \App\Domain\Imaging\LayoutPlanner::FOOTER_LEFT }}</span>
    @if ($totalPages > 1)
        <span class="page">{{ $pageNumber }}/{{ $totalPages }}</span>
    @endif
    <span>{{ \App\Domain\Imaging\LayoutPlanner::FOOTER_RIGHT }}</span>
</footer>

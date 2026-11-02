@if (! empty($activeTableFilters))
    <div
        role="status"
        style="margin-bottom: 15px; padding: 10px 12px; background-color: #f8fafc; border: 1px solid #d2d6de; border-left: 4px solid #3c8dbc; border-radius: 3px; color: #444;"
    >
        <div class="clearfix">
            <div class="pull-left">
                <strong style="color: #3c8dbc;">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtered view
                </strong>
                <span class="text-muted" style="margin-left: 8px;">Showing records matching:</span>

                @foreach ($activeTableFilters as $filter)
                    <a
                        href="{{ $filter['remove_url'] }}"
                        style="display: inline-block; margin-left: 6px; padding: 4px 7px; background-color: #e8f1f7; border: 1px solid #b8cfde; border-radius: 2px; color: #2f6487; font-size: 12px; font-weight: 600;"
                        title="Remove {{ $filter['label'] }} filter"
                        aria-label="Remove {{ $filter['label'] }} filter"
                    >
                        {{ $filter['label'] }} <i class="fas fa-times" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>

            <div class="pull-right">
                @if (! empty($backToContextUrl) && ! empty($backToContextLabel))
                    <a href="{{ $backToContextUrl }}" style="margin-right: 12px; color: #3c8dbc;">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $backToContextLabel }}
                    </a>
                @endif

                <a href="{{ $clearFiltersUrl }}" style="color: #3c8dbc;">Clear all filters</a>
            </div>
        </div>
    </div>
@endif

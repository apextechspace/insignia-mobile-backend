<div class="space-y-3">
    @php
        $rows = $preview['rows'];
        $rowErrors = $preview['errors'];
    @endphp
    @if ($rowErrors)
        <p class="font-semibold">Correct these errors before importing. No assets will be saved from this file.</p>
        <ul class="list-disc space-y-1 pl-5">
            @foreach (array_slice($rowErrors, 0, 20) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        @if (count($rowErrors) > 20)
            <p>{{ count($rowErrors) - 20 }} more errors.</p>
        @endif
    @else
        <p class="font-semibold">{{ count($rows) }} new assets ready to import.</p>
        <ul class="list-disc space-y-1 pl-5">
            @foreach (array_slice($rows, 0, 10) as $row)
                <li>{{ $row['asset_code'] }} — {{ $row['name'] }} ({{ $row['category'] }})</li>
            @endforeach
        </ul>
        @if (count($rows) > 10)
            <p>Showing the first 10 assets.</p>
        @endif
    @endif
</div>

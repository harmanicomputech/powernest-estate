<x-pdf-document :estate="$estate" :title="$report['title']" :subtitle="$from->format('j M Y').' – '.$to->format('j M Y')">
    <table class="items" style="margin-bottom:16px">
        <tbody>
            @foreach ($report['summary'] as $label => $value)
                <tr><td class="muted" style="width:40%">{{ $label }}</td><td><strong>{{ $value }}</strong></td></tr>
            @endforeach
        </tbody>
    </table>
    @if ($report['rows'])
        <table class="items" style="font-size:8.5px">
            <thead><tr>@foreach ($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach (array_slice($report['rows'], 0, $rowLimit) as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
        @if (count($report['rows']) > $rowLimit)
            <p class="muted" style="margin-top:10px">Showing the first {{ number_format($rowLimit) }} of {{ number_format(count($report['rows'])) }} rows. Download the CSV for the full list.</p>
        @endif
    @else
        <p class="muted">No records in this period.</p>
    @endif
</x-pdf-document>

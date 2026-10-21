{{-- Shared "Export to Excel" button for all report tables. Included from report-nav. --}}
<button type="button" id="btn-export-excel" class="btn btn-sm btn-success d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill flex-shrink-0" style="font-size: 13px;">
    <i class="fa-solid fa-file-excel"></i>
    <span>{{ __('report.export_excel') }}</span>
</button>

@push('after-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(function () {
    const stripHtml = (value) => {
        if (value === null || value === undefined) return '';
        if (typeof value === 'object') {
            value = value.display ?? value._ ?? value.sort ?? '';
        }
        value = String(value);
        if (value.indexOf('<') === -1) return value.trim();
        const doc = new DOMParser().parseFromString(value, 'text/html');
        return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    };

    const toCell = (text) => {
        const clean = text.replace(/,/g, '');
        return /^-?\d+(\.\d+)?$/.test(clean) && text.length < 16 ? Number(clean) : text;
    };

    const fetchAllRows = (dt) => {
        const settings = dt.settings()[0];
        if (!settings.oFeatures.bServerSide) {
            return $.Deferred().resolve(dt.rows({ search: 'applied' }).data().toArray()).promise();
        }
        const params = $.extend(true, {}, dt.ajax.params(), { start: 0, length: -1 });
        return $.ajax({ url: dt.ajax.url(), type: 'GET', data: params }).then((json) => json.data || []);
    };

    $(document).on('click', '#btn-export-excel', function () {
        const $btn = $(this);
        const selector = $.fn.dataTable.isDataTable('#datatable') ? '#datatable' : '#datatable-sales';
        if (!$.fn.dataTable.isDataTable(selector)) return;

        const dt = $(selector).DataTable();
        const columns = dt.settings()[0].aoColumns.filter((c) => c.bVisible !== false && c.data !== null);
        const headers = columns.map((c) => stripHtml(c.sTitle));

        $btn.prop('disabled', true);
        fetchAllRows(dt).done((rows) => {
            const body = rows.map((row, i) => columns.map((c) => {
                if (c.data === 'DT_RowIndex') return i + 1;
                const raw = typeof c.data === 'function' ? c.data(row, 'display') : row[c.data];
                return toCell(stripHtml(raw));
            }));

            const ws = XLSX.utils.aoa_to_sheet([headers, ...body]);
            ws['!cols'] = headers.map((h, idx) => ({
                wch: Math.min(40, Math.max(h.length, ...body.map((r) => String(r[idx] ?? '').length)) + 2)
            }));
            const wb = XLSX.utils.book_new();
            wb.Workbook = { Views: [{ RTL: document.documentElement.dir === 'rtl' }] };
            XLSX.utils.book_append_sheet(wb, ws, 'Report');

            const title = (document.title.split(/[|\-–]/)[0] || 'report').trim().replace(/[\/:*?"<>|]+/g, '_') || 'report';
            XLSX.writeFile(wb, title + '-' + new Date().toISOString().slice(0, 10) + '.xlsx');
        }).always(() => $btn.prop('disabled', false));
    });
})();
</script>
@endpush

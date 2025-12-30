<div class="bulk-toolbar d-none mb-3">
    <select id="bulkAction" class="form-select d-inline-block w-auto">
        <option value="">Bulk Action</option>
        <option value="assign">Assign</option>
        <option value="status">Change Status</option>
        <option value="delete">Delete</option>
    </select>
    <button id="applyBulk" class="btn btn-primary ms-2">Apply</button>
</div>

<script>
$('#applyBulk').click(function() {
    const action = $('#bulkAction').val();
    if (!action) return;
    const ids = $('.select-row:checked').map(function() { return this.value; }).get();
    if (!ids.length) return alert('Select candidates');
    // AJAX to bulk_handler.php with action/ids
});
</script>
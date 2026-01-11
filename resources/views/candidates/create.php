<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Add Candidate</h2>
    <a href="/candidates" class="btn btn-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/candidates" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">LinkedIn URL</label>
                    <input type="url" name="linkedin_url" class="form-control">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Resume (PDF/DOC)</label>
                    <input type="file" name="resume" class="form-control">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label">Summary / Notes</label>
                    <textarea name="summary" class="form-control" rows="4"></textarea>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Candidate</button>
            </div>
        </form>
    </div>
</div>
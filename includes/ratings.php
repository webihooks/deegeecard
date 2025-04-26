<div class="display_ratings">
    <h6>Customer Reviews</h6>
    <?php if (!empty($ratings)): ?>
    <div class="row">
        <?php foreach ($ratings as $review): ?>
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title"><?= htmlspecialchars($review['reviewer_name']) ?></h5>
                        <div class="star-rating">
                            <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                        </div>
                    </div>
                    <p class="card-text"><?= htmlspecialchars($review['feedback']) ?></p>
                    <small class="text-muted">
                        <?= date('F j, Y', strtotime($review['created_at'])) ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No reviews yet.</div>
    <?php endif; ?>
</div>

<div class="rating card">
    <h6>Leave a Review</h6>
    <form method="POST">
        <div class="row mb-3">
            <div class="col-sm-12">
                <label for="reviewer_name" class="form-label">Your Name*</label>
                <input type="text" class="form-control" id="reviewer_name" name="reviewer_name" required>
            </div>
            <div class="col-sm-12">
                <label for="reviewer_email" class="form-label">Your Email</label>
                <input type="email" class="form-control" id="reviewer_email" name="reviewer_email">
            </div>
            <div class="col-sm-12">
                <label for="reviewer_phone" class="form-label">Your Phone</label>
                <input type="tel" class="form-control" id="reviewer_phone" name="reviewer_phone" 
                       pattern="[0-9]{10,15}" title="Phone number (10-15 digits)">
            </div>
        </div>
        <div class="mb-3 col-sm-12">
            <label class="form-label">Rating*</label>
            <div class="rating-input">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" 
                           value="<?= $i ?>" <?= $i === 3 ? 'required' : '' ?>>
                    <label class="form-check-label" for="rating<?= $i ?>"><?= $i ?> ★</label>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="mb-3">
            <label for="feedback" class="form-label">Your Feedback</label>
            <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
        </div>
        <button type="submit" name="submit_rating" class="btn btn-primary">Submit Review</button>
    </form>
</div>
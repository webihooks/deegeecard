<?php if (!empty($gallery)): ?>
<div class="gallery">
    <h6>Photo Gallery</h6>
    <div class="row">
        <?php foreach ($gallery as $photo): ?>
        <div class="col-sm-12">
            <div class="card">
                <img src="<?= htmlspecialchars($photo['photo_gallery_path']) ?>" 
                     class="card-img-top" 
                     alt="<?= htmlspecialchars($photo['title']) ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($photo['title']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($photo['description']) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
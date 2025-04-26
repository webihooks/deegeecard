<!-- Cover -->
<div class="cover_photo">
    <?php if (!empty($photos['cover_photo'])): ?>
    <img src="uploads/cover/<?= htmlspecialchars($photos['cover_photo']) ?>" class="img-fluid" alt="Cover Photo">
    <?php endif; ?>
</div>

<!-- Profile Photo -->
<div class="profile_photo">
    <?php if (!empty($photos['profile_photo'])): ?>
    <img src="uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>" class="img-fluid" alt="Profile Photo">
    <?php endif; ?>
</div>

<!-- Profile Name and Details -->
<div class="personal_info">
    <h1><?= htmlspecialchars($user['name']) ?></h1>
    <?php if ($business_info && !empty($business_info['designation'])): ?>
    <div class="designation">
        <h2><?= htmlspecialchars($business_info['designation']) ?></h2>
    </div>
    <?php endif; ?>
    
    <ul class="social_networks">
        <?php foreach (['facebook', 'instagram', 'whatsapp', 'linkedin', 'youtube', 'telegram'] as $social): ?>
            <?php if (!empty($social_link[$social])): ?>
            <li>
                <a href="<?= htmlspecialchars($social_link[$social]) ?>" target="_blank">
                    <i class="bi bi-<?= $social ?>"></i>
                </a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    
    <ul class="personal_contact mt-4">
        <?php if (!empty($user['phone'])): ?>
        <li>
            <a href="tel:<?= htmlspecialchars($user['phone']) ?>">
                <i class="bi bi-telephone"></i> <?= htmlspecialchars($user['phone']) ?>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (!empty($social_link['whatsapp'])): ?>
        <li>
            <a href="<?= htmlspecialchars($social_link['whatsapp']) ?>">
                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($social_link['whatsapp']) ?>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (!empty($user['email'])): ?>
        <li>
            <a href="mailto:<?= htmlspecialchars($user['email']) ?>">
                <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (!empty($business_info['google_direction'])): ?>
        <li>
            <a href="<?= htmlspecialchars($business_info['google_direction']) ?>" target="_blank">
                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($business_info['google_direction']) ?>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
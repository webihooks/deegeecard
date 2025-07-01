<div class="share-section">
    <h6>Share Profile</h6>
    <div class="share-buttons">
        <?php 
        $current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $share_text = "📱 No waiting, no calling – Just tap & taste! Order online from";
        if ($business_info && !empty($business_info['business_name'])) {
            $share_text .= " - " . htmlspecialchars($business_info['business_name']);
        }
        ?>
        
        <!-- WhatsApp -->
        <a href="https://wa.me/?text=<?= urlencode($share_text . ': ' . $current_url) ?>" 
           target="_blank" class="share-btn whatsapp">
           <i class="bi bi-whatsapp"></i>
        </a>
        
        <!-- Facebook -->
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" 
           target="_blank" class="share-btn facebook">
           <i class="bi bi-facebook"></i>
        </a>
        
        <!-- Twitter -->
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($share_text) ?>&url=<?= urlencode($current_url) ?>" 
           target="_blank" class="share-btn twitter">
           <i class="bi bi-twitter"></i>
        </a>
        
        <!-- LinkedIn -->
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode($current_url) ?>&title=<?= urlencode($share_text) ?>" 
           target="_blank" class="share-btn linkedin">
           <i class="bi bi-linkedin"></i>
        </a>
        
        <!-- Telegram -->
        <a href="https://t.me/share/url?url=<?= urlencode($current_url) ?>&text=<?= urlencode($share_text) ?>" 
           target="_blank" class="share-btn telegram">
           <i class="bi bi-telegram"></i>
        </a>
        
        <!-- Email -->
        <a href="mailto:?subject=<?= rawurlencode($share_text) ?>&body=<?= rawurlencode("Check out this profile: " . $current_url) ?>" 
           class="share-btn email">
           <i class="bi bi-envelope"></i>
        </a>
    </div>
</div>

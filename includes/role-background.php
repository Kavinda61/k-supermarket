<?php
$page_background_role = $page_background_role ?? 'customer';
$page_background_type = $page_background_type ?? 'dashboard';

$role_backgrounds = [
    'admin' => [
        'video' => 'https://cdn.pixabay.com/video/2021/04/12/70884-536962254_large.mp4',
        'image' => 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?q=80&w=1920&auto=format&fit=crop'
    ],
    'staff' => [
        'video' => 'https://assets.mixkit.co/videos/preview/mixkit-abstract-technology-mesh-network-41558-large.mp4',
        'image' => 'https://images.unsplash.com/photo-1588964895597-cfccd6e2dbf9?q=80&w=1920&auto=format&fit=crop'
    ],
    'customer' => [
        'video' => 'https://cdn.pixabay.com/video/2022/05/17/117260-711019677_large.mp4',
        'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?q=80&w=1920&auto=format&fit=crop'
    ]
];

$background = $role_backgrounds[$page_background_role] ?? $role_backgrounds['customer'];
?>
<style>
.ks-role-background {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    overflow: hidden;
    pointer-events: none;
    z-index: -2;
}

.ks-role-background-video,
.ks-role-background-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background-position: center;
    background-size: cover;
    background-repeat: no-repeat;
    filter: brightness(0.55) contrast(1.1);
}

.ks-role-background-image {
    background-image: url('<?php echo htmlspecialchars($background['image'], ENT_QUOTES, 'UTF-8'); ?>');
}

.ks-role-background-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.7);
}

body.ks-role-backdrop:not(.dark-mode) {
    background-color: transparent !important;
}
</style>

<div id="ks-role-background" class="ks-role-background ks-role-background-<?php echo htmlspecialchars($page_background_type, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
<?php if ($page_background_type === 'dashboard'): ?>
    <video class="ks-role-background-video" autoplay loop muted playsinline poster="<?php echo htmlspecialchars($background['image'], ENT_QUOTES, 'UTF-8'); ?>">
        <source src="<?php echo htmlspecialchars($background['video'], ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
    </video>
<?php else: ?>
    <div class="ks-role-background-image"></div>
<?php endif; ?>
    <div class="ks-role-background-overlay"></div>
</div>

<script>
document.body.classList.add('ks-role-backdrop');
</script>

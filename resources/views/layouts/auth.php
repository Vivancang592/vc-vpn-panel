<?php 
$extraCss = 'auth';
$extraJs = 'auth';
require_once __DIR__ . '/header.php'; 
?>

<!-- Slide Background & Fog Effects -->
<div id="dynamic-bg" class="bg-slideshow"></div>
<div class="bg-overlay"></div>

<!-- Brand Logo / Tên Web ở góc trên bên trái màn hình -->
<a href="/" class="auth-brand-corner">
    <span>
        <?= htmlspecialchars($siteTitle ?? 'VC VPN 2027', ENT_QUOTES, 'UTF-8') ?>
    </span>
</a>

<div class="corner-glow">
  <div class="glow glow-left"></div>
  <div class="glow glow-right"></div>
</div>

<div class="fog-container">
  <img src="https://raw.githubusercontent.com/danielstuart14/CSS_FOG_ANIMATION/master/fog1.png" class="fog-img" alt="fog">
  <img src="https://raw.githubusercontent.com/danielstuart14/CSS_FOG_ANIMATION/master/fog2.png" class="fog-img fog-2" alt="fog">
</div>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4 assemble-top">
            <h2 class="fw-bold royal-title mb-1 text-sparkle"><?= htmlspecialchars($authTitle ?? 'Đăng nhập tài khoản', ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="small auth-subtitle"><?= htmlspecialchars($authSubtitle ?? 'An Toàn - Bảo Mật - Uy Tín', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        
        <?= $content ?? '' ?>
    </div>
</div>

<!-- Nạp JS Động Cho Trang Auth -->
<?php if (isset($extraJs)): ?>
    <script src="/assets/js/<?= $extraJs ?>.js?v=<?= time() ?>"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const images = [
      'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=2000', 
      'https://images.unsplash.com/photo-1614850523459-c2f4c699c52e?q=80&w=2000', 
      'https://images.unsplash.com/photo-1550684848-fac1c5b4e853?q=80&w=2000', 
      'https://images.unsplash.com/photo-1506748686214-e28214011676?q=80&w=2000'
    ];
    
    const bgContainer = document.getElementById('dynamic-bg');

    if (bgContainer && images.length > 0) {
        images.forEach((src, index) => {
            const slide = document.createElement('div');
            slide.className = 'slide-item';
            slide.style.backgroundImage = `url('${src}')`;
            if (index === 0) slide.classList.add('active');
            bgContainer.appendChild(slide);
        });

        const slides = document.querySelectorAll('.slide-item');
        let currentIndex = 0;

        if (slides.length > 1) {
            setInterval(() => {
                slides[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % slides.length;
                slides[currentIndex].classList.add('active');
            }, 6000);
        }
    }
});
</script>
</body>
</html>
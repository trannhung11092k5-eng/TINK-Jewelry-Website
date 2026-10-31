<?php
// includes/footer.php
?>
<!-- CSS -->
    <link rel="stylesheet" href="../public/assets/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-info">
                <div class="logo">TINK <span>Jewelry</span></div>
                <p>Vẻ đẹp tinh tế trong từng chi tiết</p>
            </div>
            <div class="footer-links">
                <a href="#" class="social-link"><i class="fab fa-facebook-f"></i> Facebook</a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i> Instagram</a>
                <a href="#" class="social-link"><i class="fab fa-pinterest"></i> Pinterest</a>
            </div>
            <p class="copyright">© 2025 TINK Jewelry | All Rights Reserved</p>
        </div>
    </footer>

    <!-- Nút về đầu trang -->
    <button class="back-to-top">↑</button>

    <script>
        // Back to top button
        const backToTop = document.querySelector('.back-to-top');
        window.addEventListener('scroll', () => {
            backToTop.classList.toggle('show', window.pageYOffset > 300);
        });
        backToTop.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Xử lý dropdown menu
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('mouseenter', function() {
                this.querySelector('.dropdown-menu').style.opacity = '1';
                this.querySelector('.dropdown-menu').style.visibility = 'visible';
                this.querySelector('.dropdown-menu').style.transform = 'translateY(0)';
            });
            
            dropdown.addEventListener('mouseleave', function() {
                this.querySelector('.dropdown-menu').style.opacity = '0';
                this.querySelector('.dropdown-menu').style.visibility = 'hidden';
                this.querySelector('.dropdown-menu').style.transform = 'translateY(10px)';
            });
        });
    </script>
</body>
</html>
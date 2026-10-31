<?php
// homepage.php - Trang chủ
require_once '../config/db.php';
?>
<?php include '../public/includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero section-with-effect">
        <!-- Slider Container -->
        <div class="hero-slider">
            <div class="slide active" style="background-image: url('../photos/8.jpg');"></div>
            <div class="slide" style="background-image: url('../photos/anh1.jpg');"></div>
            <div class="slide" style="background-image: url('../photos/anh2.jpg');"></div>
            <div class="slide" style="background-image: url('../photos/anh7.jpg');"></div>
            <div class="slide" style="background-image: url('../photos/anh4.jpg');"></div>
        </div>
        
        <!-- Text Content -->
        <div class="hero-text" id="heroText">
            <h1>Vẻ đẹp tinh tế trong từng chi tiết</h1>
            <p>Khám phá sự sang trọng thuần khiết và tinh xảo qua từng món trang sức của TINK. Mỗi thiết kế là một tác phẩm nghệ thuật, kết tinh từ sự tỉ mỉ và đam mê.</p>
            <a href="products.php" class="hero-btn">Khám phá ngay</a>
        </div>
        
        <!-- Slider Controls -->
        <button class="slider-arrow prev">‹</button>
        <button class="slider-arrow next">›</button>
        
        <div class="slider-controls">
            <div class="slider-dot active"></div>
            <div class="slider-dot"></div>
            <div class="slider-dot"></div>
            <div class="slider-dot"></div>
            <div class="slider-dot"></div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="gallery section-with-effect">
        <h2>Bạn đang tìm gì hôm nay?</h2>
        <div class="gallery-grid">
            <div class="gallery-item">
                <img src="../photos/1.webp" alt="Nhẫn" class="gallery-image primary">
                <img src="../photos/1 hover.jpg" alt="Nhẫn - Ảnh hover" class="gallery-image hover">
                <div class="gallery-overlay">
                    <div class="gallery-title">NHẪN</div>
                    <div class="gallery-subtitle">Món quà ý nghĩa</div>
                    <a href="products.php?category=Nhẫn" class="gallery-link">Xem chi tiết</a>
                </div>
            </div>
            <div class="gallery-item">
                <img src="../photos/2.webp" alt="Vòng Tay" class="gallery-image primary">
                <img src="../photos/2 hover.jpg" alt="Vòng Tay - Ảnh hover" class="gallery-image hover">
                <div class="gallery-overlay">
                    <div class="gallery-title">VÒNG TAY</div>
                    <div class="gallery-subtitle">Sang trọng tinh tế</div>
                    <a href="products.php?category=Vòng tay" class="gallery-link">Xem chi tiết</a>
                </div>
            </div>
            <div class="gallery-item">
                <img src="../photos/3.jpg" alt="Vòng cổ" class="gallery-image primary">
                <img src="../photos/3 hover.jpg" alt="Vòng cổ - Ảnh hover" class="gallery-image hover">
                <div class="gallery-overlay">
                    <div class="gallery-title">VÒNG CỔ</div>
                    <div class="gallery-subtitle">Nữ tính thanh lịch</div>
                    <a href="products.php?category=Vòng cổ" class="gallery-link">Xem chi tiết</a>
                </div>
            </div>
            <div class="gallery-item">
                <img src="../photos/4.webp" alt="Khuyên tai" class="gallery-image primary">
                <img src="../photos/4 hover.jpg" alt="Khuyên tai - Ảnh hover" class="gallery-image hover">
                <div class="gallery-overlay">
                    <div class="gallery-title">KHUYÊN TAI</div>
                    <div class="gallery-subtitle">Nét đẹp quyến rũ</div>
                    <a href="products.php?category=Khuyên tai" class="gallery-link">Xem chi tiết</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Sản phẩm nổi bật (lấy từ database) -->
    <section class="product-sections">
        <?php
        // Lấy 4 sản phẩm mới và 4 sản phẩm "bán chạy"
        try {
            $stmtNew = $pdo->query("SELECT * FROM SANPHAM ORDER BY MaSP DESC LIMIT 4");
            $newProducts = $stmtNew->fetchAll();

            // Sản phẩm bán chạy: ưu tiên số lượng đã bán từ đơn "Đã giao", sau đó mới đến số lượng tồn kho ít nhất
            $stmtBest = $pdo->query("
                SELECT sp.*, 
                       COALESCE(SUM(CASE WHEN dh.TrangThai IN ('Đã giao', 'DA_GIAO') THEN ctdh.SoLuong ELSE 0 END), 0) as SoLuongDaBan,
                       sp.SoLuongTon
                FROM SANPHAM sp
                LEFT JOIN CHITIET_DONHANG ctdh ON sp.MaSP = ctdh.MaSP
                LEFT JOIN DONHANG dh ON ctdh.MaDonHang = dh.MaDonHang
                GROUP BY sp.MaSP
                ORDER BY SoLuongDaBan DESC, sp.SoLuongTon ASC
                LIMIT 4
            ");
            $bestProducts = $stmtBest->fetchAll();
        } catch (PDOException $e) {
            $newProducts = [];
            $bestProducts = [];
        }
        ?>

        <div class="section-header">
            <h2>Sản Phẩm Nổi Bật</h2>
            <div class="section-tabs">
                <button type="button" class="tab-btn active" id="tab-new">Sản phẩm mới</button>
                <button type="button" class="tab-btn" id="tab-best">Sản phẩm bán chạy</button>
            </div>
        </div>
        
        <div class="product-tab-content active" id="content-new">
            <div class="product-grid">
                <?php
                if ($newProducts) {
                    foreach ($newProducts as $product) {
                        echo '<div class="product-card" onclick="window.location=\'product-detail.php?id=' . $product['MaSP'] . '\'">
                                <div class="product-image">
                                    <img src="../' . htmlspecialchars($product['AnhSP']) . '" alt="' . htmlspecialchars($product['TenSP']) . '">
                                    <div class="product-badge">Mới</div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">' . htmlspecialchars($product['TenSP']) . '</h3>
                                    <div class="product-price">' . number_format($product['GiaBan'], 0, ',', '.') . 'đ</div>
                                    <div class="product-sold">Mã: ' . htmlspecialchars($product['MaSP']) . '</div>
                                </div>
                              </div>';
                    }
                } else {
                    echo '<p>Chưa có sản phẩm nào.</p>';
                }
                ?>
            </div>
        </div>

        <div class="product-tab-content" id="content-best">
            <div class="product-grid">
                <?php
                if ($bestProducts) {
                    foreach ($bestProducts as $product) {
                        echo '<div class="product-card" onclick="window.location=\'product-detail.php?id=' . $product['MaSP'] . '\'">
                                <div class="product-image">
                                    <img src="../' . htmlspecialchars($product['AnhSP']) . '" alt="' . htmlspecialchars($product['TenSP']) . '">
                                    <div class="product-badge">Bán chạy</div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">' . htmlspecialchars($product['TenSP']) . '</h3>
                                    <div class="product-price">' . number_format($product['GiaBan'], 0, ',', '.') . 'đ</div>
                                    <div class="product-sold">Mã: ' . htmlspecialchars($product['MaSP']) . '</div>
                                </div>
                              </div>';
                    }
                } else {
                    echo '<p>Chưa có sản phẩm nào.</p>';
                }
                ?>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="products.php" class="hero-btn" style="background: #1a237e; color: white;">Xem tất cả sản phẩm</a>
        </div>
    </section>

    <!-- Store Description -->
    <section id="store-description" class="store-description section-with-effect">
        <div class="store-content">
            <div class="store-text">
                <h2>Không Gian TINK Jewelry</h2>
                <p>Bước vào TINK Jewelry, bạn sẽ được chào đón bởi một không gian sang trọng và ấm cúng. Với thiết kế tối giản nhưng tinh tế, showroom của chúng tôi là nơi hoàn hảo để bạn khám phá và trải nghiệm các bộ sưu tập trang sức độc đáo.</p>
                <p>Mỗi góc trưng bày được chăm chút tỉ mỉ, nơi ánh sáng tự nhiên hòa quyện cùng ánh kim của những món trang sức, tạo nên một không gian nghệ thuật đầy cảm hứng.</p>
            </div>
                <div class="store-image">
                <img src="../photos/7.jpg" alt="TINK Jewelry Store">
            </div>
        </div>
    </section>

    <!-- Review Section -->
    <section id="reviews" class="reviews section-with-effect">
        <!-- Glitter Sweep Effect cho Reviews -->
        <div class="glitter-sweep" id="reviewsSweep"></div>
        <div class="glitter-particles" id="reviewsParticles"></div>

        <h2>Khách hàng nói gì về TINK</h2>
        <div class="review-grid">
            <div class="review-card" style="background-image: url('../photos/9.jpg');">
                <p>"Chiếc vòng cổ thật sự tinh tế và sang trọng, cảm giác như được làm riêng cho tôi vậy."</p>
                <span>- Lan Anh, Hà Nội</span>
            </div>
            <div class="review-card" style="background-image: url('../photos/9.jpg');">
                <p>"Dịch vụ tuyệt vời, sản phẩm vượt ngoài mong đợi. Tôi chắc chắn sẽ quay lại."</p>
                <span>- Minh Trang, TP.HCM</span>
            </div>
            <div class="review-card" style="background-image: url('../photos/9.jpg');">
                <p>"Mỗi món trang sức của TINK đều mang một vẻ đẹp độc nhất, thật đáng giá."</p>
                <span>- Thu Hà, Đà Nẵng</span>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact section-with-effect">
        <h2>Liên Hệ Với Chúng Tôi</h2>
        <div class="contact-grid">
            <div class="contact-info">
                <div class="info-item">
                    <h3>Địa Chỉ</h3>
                    <p>123 Nguyễn Huệ, Quận 1</p>
                    <p>TP. Hồ Chí Minh, Việt Nam</p>
                </div>
                <div class="info-item">
                    <h3>Giờ Mở Cửa</h3>
                    <p>Thứ 2 - Thứ 7: 9:00 - 21:00</p>
                    <p>Chủ nhật: 10:00 - 18:00</p>
                </div>
                <div class="info-item">
                    <h3>Liên Hệ</h3>
                    <p>Email: hello@tinkjewelry.com</p>
                    <p>Tel: (028) 1234 5678</p>
                </div>
            </div>
            <div class="map">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.3253104372514!2d106.70129661533454!3d10.777636362124085!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f4b3330bcc7%3A0x4db964d76bf6e18e!2zMTIzIE5ndXnhu4VuIEh14buHLCBC4bq_biBOZ2jDqSwgUXXhuq1uIDEsIFRow6BuaCBwaOG7kSBI4buTIENow60gTWluaCwgVmnhu4d0IE5hbQ!5e0!3m2!1svi!2s!4v1635000000000!5m2!1svi!2s" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </section>

<?php include '../public/includes/footer.php'; ?>

<script>
    // Hero Slider Script
    class HeroSlider {
        constructor() {
            this.slides = document.querySelectorAll('.slide');
            this.dots = document.querySelectorAll('.slider-dot');
            this.prevBtn = document.querySelector('.slider-arrow.prev');
            this.nextBtn = document.querySelector('.slider-arrow.next');
            this.currentSlide = 0;
            this.slideInterval = null;
            this.slideDuration = 5000;
            
            this.init();
        }
        
        init() {
            this.startAutoSlide();
            this.prevBtn.addEventListener('click', () => this.prevSlide());
            this.nextBtn.addEventListener('click', () => this.nextSlide());
            
            this.dots.forEach((dot, index) => {
                dot.addEventListener('click', () => this.goToSlide(index));
            });
            
            const slider = document.querySelector('.hero-slider');
            slider.addEventListener('mouseenter', () => this.stopAutoSlide());
            slider.addEventListener('mouseleave', () => this.startAutoSlide());
        }
        
        startAutoSlide() {
            this.stopAutoSlide();
            this.slideInterval = setInterval(() => {
                this.nextSlide();
            }, this.slideDuration);
        }
        
        stopAutoSlide() {
            if (this.slideInterval) {
                clearInterval(this.slideInterval);
            }
        }
        
        nextSlide() {
            this.currentSlide = (this.currentSlide + 1) % this.slides.length;
            this.updateSlider();
        }
        
        prevSlide() {
            this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
            this.updateSlider();
        }
        
        goToSlide(index) {
            this.currentSlide = index;
            this.updateSlider();
        }
        
        updateSlider() {
            this.slides.forEach((slide, index) => {
                slide.classList.toggle('active', index === this.currentSlide);
            });
            
            this.dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === this.currentSlide);
            });
        }
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        new HeroSlider();
        
        // Hiệu ứng fade-in cho hero text
        const heroText = document.getElementById('heroText');
        setTimeout(() => {
            heroText.classList.add('animate');
        }, 500);

        // Tab "Sản phẩm mới" / "Bán chạy"
        const tabNew = document.getElementById('tab-new');
        const tabBest = document.getElementById('tab-best');
        const contentNew = document.getElementById('content-new');
        const contentBest = document.getElementById('content-best');

        if (tabNew && tabBest && contentNew && contentBest) {
            tabNew.addEventListener('click', () => {
                tabNew.classList.add('active');
                tabBest.classList.remove('active');
                contentNew.classList.add('active');
                contentBest.classList.remove('active');
            });

            tabBest.addEventListener('click', () => {
                tabBest.classList.add('active');
                tabNew.classList.remove('active');
                contentBest.classList.add('active');
                contentNew.classList.remove('active');
            });
        }
    });
</script>
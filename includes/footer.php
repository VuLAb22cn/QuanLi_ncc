</div> <!-- End container-fluid -->

    <!-- Footer -->
    <footer class="bg-light py-4 px-4">
        <div>
            <div class="row">
                <!-- Phần mô tả -->
                <div class="col-12 mb-4">
                    <h5 class="text-dark">Tây Đô Product</h5>
                    <p class="text-muted">Chúng tôi cung cấp các giải pháp quản lý nhà cung cấp toàn diện, giúp doanh nghiệp của bạn hoạt động hiệu quả hơn. Kết nối với chúng tôi để khám phá các sản phẩm và dịch vụ tiên tiến.</p>
                </div>
            </div>
            <div class="row">
                <!-- Thông tin liên hệ -->
                <div class="col-md-4 mb-3">
                    <h5 class="text-dark mb-3">Thông tin liên hệ</h5>
                    <p class="mb-2"><i class="fas fa-map-marker-alt mr-2"></i> Địa chỉ: 123 Đường ABC, Quận XYZ, TP. Cần Thơ</p>
                    <p class="mb-2"><i class="fas fa-phone mr-2"></i> Hotline: 02923.XXX.XXX</p>
                    <p class="mb-2"><i class="fas fa-envelope mr-2"></i> Email: info@taydoproduct.com</p>
                    <p class="mb-2"><i class="fas fa-clock mr-2"></i> Giờ làm việc: 8:30 - 17:30 (Thứ 2 - Thứ 7)</p>
                </div>
                
                <!-- Bản đồ -->
                <div class="col-md-4 mb-3">
                    <h5 class="text-dark mb-3">Vị trí của chúng tôi</h5>
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3928.8411233555397!2d105.76890461480084!3d10.032919989822!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31a0883e69f6d3a5%3A0x2c3e3a5b3a5b3a5b!2zVMOibiBwaOG6p24gQ-G6p3UgR2nhuqV5!5e0!3m2!1svi!2s!4v1234567890!5m2!1svi!2s" 
                            width="100%" 
                            height="200" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
                
                <!-- Liên kết nhanh -->
                <div class="col-md-4 mb-3 pl-4">
                    <h5 class="text-dark mb-3">Liên kết nhanh</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="about.php" class="text-muted">Về chúng tôi</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-muted">Liên hệ</a></li>
                        <li class="mb-2"><a href="privacy.php" class="text-muted">Chính sách bảo mật</a></li>
                        <li class="mb-2"><a href="terms.php" class="text-muted">Điều khoản sử dụng</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-3">
            
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Hệ thống quản lý nhà cung cấp. Phát triển bởi <strong>Tây Đô Product</strong></p>
            </div>
        </div>
    </footer>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap 4 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <!-- Page specific scripts -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
</body>
</html>

    <!-- Footer -->
    <footer style="background: #1a1a1a; color: #FFFFFF; margin-top: auto; padding: 60px 0 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
        <div class="container" style="max-width: 1280px; margin: 0 auto; padding: 0 16px;">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 style="font-size: 18px; font-weight: 700; color: #FFFFFF; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                        <img src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/tau-logo.png" alt="TAU Logo" style="height: 40px; width: auto;"> TAU-UREO Portal
                    </h5>
                    <p style="font-size: 14px; line-height: 1.6; color: #999999;">
                        University Research Ethics Office - Ensuring ethical standards in research through transparent and efficient review processes.
                    </p>
                </div>
                <div class="col-md-4">
                    <h6 style="font-size: 14px; font-weight: 600; color: #FFFFFF; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Quick Links</h6>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="margin-bottom: 8px;"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php" style="color: #999999; text-decoration: none; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#999999'">Home</a></li>
                        <li style="margin-bottom: 8px;"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>track-application.php" style="color: #999999; text-decoration: none; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#999999'">Track Application</a></li>
                        <li style="margin-bottom: 8px;"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>submit-intent.php" style="color: #999999; text-decoration: none; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#999999'">Submit Application</a></li>
                        <li style="margin-bottom: 8px;"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>staff/login.php" style="color: #999999; text-decoration: none; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#FFFFFF'" onmouseout="this.style.color='#999999'">Staff Portal</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 style="font-size: 14px; font-weight: 600; color: #FFFFFF; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Contact Information</h6>
                    <p style="font-size: 14px; line-height: 1.6; color: #999999; margin-bottom: 8px;">
                        <i class="bi bi-envelope" style="margin-right: 8px; color: #006400;"></i> tau_ureo@tau.edu.ph
                    </p>
                    <p style="font-size: 14px; line-height: 1.6; color: #999999; margin-bottom: 8px;">
                        <i class="bi bi-telephone" style="margin-right: 8px; color: #006400;"></i> (045) 493-0182
                    </p>
                    <p style="font-size: 14px; line-height: 1.6; color: #999999; margin-bottom: 0;">
                        <i class="bi bi-geo-alt" style="margin-right: 8px; color: #006400;"></i> Tarlac Agricultural University<br>
                        <span style="margin-left: 24px;">Camiling, Tarlac</span>
                    </p>
                </div>
            </div>
            <hr style="border: 0; height: 1px; background: rgba(255, 255, 255, 0.1); margin: 40px 0 20px;">
            <div class="text-center">
                <p style="font-size: 14px; color: #666666; margin: 0;">&copy; <?php echo date('Y'); ?> Tarlac Agricultural University - University Research Ethics Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

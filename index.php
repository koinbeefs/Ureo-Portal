<?php
/**
 * Homepage - Public Progress Tracking
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$tracking_result = null;
$error_message = '';

// Handle tracking search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_application'])) {
    $queue_number = sanitizeInput($_POST['queue_number']);
    
    if (!empty($queue_number)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT queue_number, current_status, last_updated FROM applications WHERE queue_number = ?");
        $stmt->bind_param("s", $queue_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $tracking_result = $result->fetch_assoc();
        } else {
            $error_message = "Queue number not found. Please check and try again.";
        }
        
        closeDBConnection($conn);
    } else {
        $error_message = "Please enter a queue number.";
    }
}
?>
<?php 
$page_title = 'Home';
$active_page = 'home';
include 'includes/header.php'; 
?>

    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); position: relative; overflow: hidden; padding: 60px 0;">
        <div style="position: absolute; inset: 0; background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.05"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        <div class="container" style="max-width: 1280px; margin: 0 auto; padding: 0 1rem; position: relative; z-index: 1;">
            <div class="row align-items-center">
                <div class="col-lg-6" style="margin-bottom: 2rem;">
                    <h1 style="font-size: 48px; font-weight: 700; color: white; line-height: 1.2; margin-bottom: 16px;">University Research Ethics Office</h1>
                    <p style="font-size: 20px; color: rgba(255, 255, 255, 0.95); font-weight: 500; margin-bottom: 12px;">Tarlac Agricultural University</p>
                    <p style="font-size: 16px; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">Ensuring ethical standards in research through transparent and efficient review processes.</p>
                </div>
                <div class="col-lg-6">
                    <div style="background: white; border-radius: 0.75rem; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
                        <h3 style="font-size: 24px; font-weight: 600; color: #006400; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-search"></i> Quick Track
                        </h3>
                        <p style="color: #666666; font-size: 14px; margin-bottom: 20px;">Enter your queue number to check your application status</p>
                            
                            <form method="GET" action="track-application.php">
                                <div style="margin-bottom: 1rem;">
                                    <label for="queue_number" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Queue Number</label>
                                    <input type="text" id="queue_number" name="queue_number" placeholder="UREO-0000" required pattern="UREO-\d{4}" 
                                           style="width: 100%; padding: 0.625rem 0.75rem; font-size: 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; transition: all 0.2s; outline: none;"
                                           onfocus="this.style.borderColor='#059669'; this.style.boxShadow='0 0 0 3px rgba(5, 150, 105, 0.1)'"
                                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                                    <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">Format: UREO-0000</p>
                                </div>
                                <button type="submit" style="width: 100%; padding: 12px 24px; background: linear-gradient(135deg, #006400, #228B22); color: white; font-weight: 600; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0, 100, 0, 0.15);" 
                                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0, 100, 0, 0.25)'"
                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0, 100, 0, 0.15)'">
                                    <i class="bi bi-search"></i> Track Application
                                </button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <a href="track-application.php" class="btn btn-link">
                                    View detailed tracking page <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section style="padding: 60px 0; background: #F8F8F8;">
        <div class="container" style="max-width: 1280px; margin: 0 auto; padding: 0 16px;">
            <div style="text-align: center; margin-bottom: 40px;">
                <h2 style="font-size: 42px; font-weight: 700; color: #006400; margin-bottom: 12px;">Our Services</h2>
                <p style="font-size: 18px; color: #666666; max-width: 700px; margin: 0 auto;">Comprehensive research ethics review and support</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div style="background: white; border-radius: 0.75rem; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transition: all 0.3s; height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center;"
                         onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 8px 16px rgba(0, 0, 0, 0.15)'"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 8px rgba(0, 0, 0, 0.12)'">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0f7a2a, #0b3b18); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(15, 122, 42, 0.3);">
                            <i class="bi bi-file-earmark-text" style="font-size: 28px; color: white;"></i>
                        </div>
                        <h3 style="font-size: 20px; font-weight: 600; color: #0f7a2a; margin-bottom: 12px;">Submit Application</h3>
                        <p style="color: #666666; font-size: 14px; line-height: 1.6; margin-bottom: 20px; flex-grow: 1;">Start your research ethics review by submitting a letter of intent.</p>
                        <a href="submit-intent.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #006400; color: #006400; font-weight: 600; text-decoration: none; border-radius: 8px; font-size: 14px; transition: all 0.2s;"
                           onmouseover="this.style.background='#006400'; this.style.color='white'"
                           onmouseout="this.style.background='transparent'; this.style.color='#006400'">Get Started <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="background: white; border-radius: 12px; padding: 32px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12); transition: all 0.3s; height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center;"
                         onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 8px 16px rgba(0, 0, 0, 0.15)'"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 8px rgba(0, 0, 0, 0.12)'">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0f7a2a, #0b3b18); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(15, 122, 42, 0.3);">
                            <i class="bi bi-clock-history" style="font-size: 28px; color: white;"></i>
                        </div>
                        <h3 style="font-size: 20px; font-weight: 600; color: #0f7a2a; margin-bottom: 12px;">Track Progress</h3>
                        <p style="color: #666666; font-size: 14px; line-height: 1.6; margin-bottom: 20px; flex-grow: 1;">Monitor your application status in real-time using your queue number.</p>
                        <a href="track-application.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #006400; color: #006400; font-weight: 600; text-decoration: none; border-radius: 8px; font-size: 14px; transition: all 0.2s;"
                           onmouseover="this.style.background='#006400'; this.style.color='white'"
                           onmouseout="this.style.background='transparent'; this.style.color='#006400'">Track Now <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div style="background: white; border-radius: 12px; padding: 32px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12); transition: all 0.3s; height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center;"
                         onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 8px 16px rgba(0, 0, 0, 0.15)'"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 8px rgba(0, 0, 0, 0.12)'">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0f7a2a, #0b3b18); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(15, 122, 42, 0.3);">
                            <i class="bi bi-book" style="font-size: 28px; color: white;"></i>
                        </div>
                        <h3 style="font-size: 20px; font-weight: 600; color: #0f7a2a; margin-bottom: 12px;">Guidelines</h3>
                        <p style="color: #666666; font-size: 14px; line-height: 1.6; margin-bottom: 20px; flex-grow: 1;">Review our research ethics guidelines and requirements.</p>
                        <a href="guidelines.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #006400; color: #006400; font-weight: 600; text-decoration: none; border-radius: 8px; font-size: 14px; transition: all 0.2s;"
                           onmouseover="this.style.background='#006400'; this.style.color='white'"
                           onmouseout="this.style.background='transparent'; this.style.color='#006400'">Learn More <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>

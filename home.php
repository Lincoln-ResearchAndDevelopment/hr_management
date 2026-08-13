<!-- HERO SECTION -->
<section id="home" class="hero-section py-5">
    <div class="container-fluid px-4 px-md-5">
        <div class="row align-items-center g-5">
            <!-- Left Column - Text -->
            <div class="col-12 col-lg-6">
                <h1 class="hero-title mb-4">
                    Join Our <span style="color: #C82333;">Team</span> and Build Your Career.
                </h1>
                <p class="hero-subtitle mb-5" style="color: #111; font-size: 1.1rem; line-height: 1.6;">
                    Discover exciting career opportunities and become part of a dynamic team. Apply for open positions or access your staff portal.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="application.php" class="btn btn-primary rounded-pill px-5 py-3" style="background-color: #C82333; border: none; font-weight: 600; font-size: 1rem;">
                        Apply Now
                    </a>
                    <a href="staff/login.php" class="btn btn-outline-danger rounded-pill px-5 py-3" style="font-weight: 600; font-size: 1rem;">
                        Staff Login
                    </a>
                </div>
            </div>

            <!-- Right Column - Image with floating cards -->
            <div class="col-12 col-lg-6 position-relative">
                <img src="./assets/img/heroimage.png" alt="Professional team" class="img-fluid rounded" style="border-radius: 14px;">

                <!-- Floating Card 1 -->
                <div class="floating-card floating-card-1">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div>
                            <p class="mb-0 fw-600" style="color: #111; font-size: 0.95rem;">Join our talented team of professionals</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <img src="./assets/img/avatar.png" alt="avatar" class="avatar-small">
                        <img src="./assets/img/avatar.png" alt="avatar" class="avatar-small">
                        <img src="./assets/img/avatar.png" alt="avatar" class="avatar-small">
                    </div>
                </div>

                <!-- Floating Card 2 -->
                <div class="floating-card floating-card-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-briefcase" style="font-size: 1.8rem; color: #C82333;"></i>
                        <div>
                            <p class="mb-0 fw-600" style="color: #111; font-size: 0.95rem;">Multiple positions available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES SECTION -->
<section class="features-section py-5" style="padding-top: 80px; padding-bottom: 80px;">
    <div class="container-fluid px-4 px-md-5">
        <div class="row g-4">
            <!-- Feature 1: Online Applications -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h6 class="feature-title">Easy Application</h6>
                </div>
            </div>

            <!-- Feature 2: Interview Scheduling -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h6 class="feature-title">Interview Scheduling</h6>
                </div>
            </div>

            <!-- Feature 3: Staff Portal -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h6 class="feature-title">Staff Portal</h6>
                </div>
            </div>

            <!-- Feature 4: Training Programs -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h6 class="feature-title">Training Programs</h6>
                </div>
            </div>

            <!-- Feature 5: HR Management -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h6 class="feature-title">HR Management</h6>
                </div>
            </div>

            <!-- Feature 6: Career Growth -->
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h6 class="feature-title">Career Growth</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- JOB OFFERS SECTION -->
<?php
require_once 'config.php';

// Fetch available job vacancies
$jobs_query = "SELECT * FROM job_vacancies WHERE is_active = 1 ORDER BY posted_date DESC LIMIT 6";
$jobs_result = $conn->query($jobs_query);
?>
<section class="jobs-section py-5" style="padding-top: 80px; padding-bottom: 80px;" id="jobs">
    <div class="container-fluid px-4 px-md-5">
        <!-- Header with View All button -->
        <div class="row mb-5">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2 class="mb-0" style="color: #111; font-weight: 700; font-size: 2rem;">
                    Available Job Opportunities
                </h2>
                <a href="application.php" style="color: #C82333; text-decoration: none; font-weight: 600;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Job Cards Grid -->
        <div class="row g-4">
            <?php if ($jobs_result && $jobs_result->num_rows > 0): ?>
                <?php while ($job = $jobs_result->fetch_assoc()): ?>
                    <!-- Job Card -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="job-card">
                            <img src="assets/img/job-default.svg" alt="<?php echo htmlspecialchars($job['title']); ?>" class="job-img">
                            <div class="job-content">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="job-title mb-0"><?php echo htmlspecialchars($job['title']); ?></h6>
                                    <i class="fas fa-briefcase" style="color: #C82333; font-size: 1.1rem;"></i>
                                </div>
                                <div class="job-meta mb-3">
                                    <span class="badge" style="background-color: #f0f0f0; color: #111;"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center text-muted">No job openings available at the moment. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- BENEFITS SECTION -->
<section class="benefits-section py-5" style="padding-top: 80px; padding-bottom: 80px; background-color: #FFFFFF;">
    <div class="container-fluid px-4 px-md-5">
        <!-- Heading -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 style="color: #111; font-weight: 700; font-size: 2rem; margin-bottom: 0;">
                    Why Work With Us
                </h2>
            </div>
        </div>

        <!-- Benefits Cards -->
        <div class="row g-4">
            <!-- Benefit 1 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="skill-card">
                    <img src="assets/img/skill-course1.svg" alt="Competitive Benefits" class="skill-img">
                    <div class="skill-content">
                        <h6 class="skill-title mb-2">Competitive Salary & Benefits</h6>
                        <div class="skill-meta">
                            <p style="font-size: 0.9rem; color: #666; margin: 0; line-height: 1.6;">
                                We offer attractive compensation packages with comprehensive health benefits, retirement plans, and performance bonuses.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Benefit 2 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="skill-card">
                    <img src="assets/img/skill-course2.svg" alt="Professional Development" class="skill-img">
                    <div class="skill-content">
                        <h6 class="skill-title mb-2">Professional Development & Training</h6>
                        <div class="skill-meta">
                            <p style="font-size: 0.9rem; color: #666; margin: 0; line-height: 1.6;">
                                Continuous learning opportunities through workshops, seminars, and professional training programs to enhance your skills.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Benefit 3 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="skill-card">
                    <img src="assets/img/skill-course3.svg" alt="Work-Life Balance" class="skill-img">
                    <div class="skill-content">
                        <h6 class="skill-title mb-2">Work-Life Balance & Flexibility</h6>
                        <div class="skill-meta">
                            <p style="font-size: 0.9rem; color: #666; margin: 0; line-height: 1.6;">
                                Flexible working hours, remote work options, and generous leave policies to help you maintain a healthy work-life balance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT US SECTION -->
<section class="about-section py-5" style="padding-top: 80px; padding-bottom: 80px; background-color: #FFFFFF;">
    <div class="container-fluid px-4 px-md-5">
        <div class="row align-items-center g-5">
            <!-- Left Column - Image -->
            <div class="col-12 col-lg-5">
                <img src="assets/img/instructor-image.svg" alt="Our Team" class="img-fluid rounded" style="border-radius: 14px; width: 100%; max-width: 400px;">
            </div>

            <!-- Right Column - Content -->
            <div class="col-12 col-lg-7">
                <h2 style="color: #111; font-weight: 700; font-size: 2.5rem; margin-bottom: 1.5rem; line-height: 1.2;">
                    Building Careers, Growing Together
                </h2>

                <!-- Info Card -->
                <div class="testimonial-card" style="background: #FFFFFF; border-left: 4px solid #C82333; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <i class="fas fa-users" style="font-size: 1.8rem; color: #C82333; flex-shrink: 0; margin-top: 5px;"></i>
                        <div>
                            <h5 style="color: #111; font-weight: 600; margin-bottom: 0.5rem; font-size: 1.1rem;">
                                Join Our Growing Team
                            </h5>
                            <p style="color: #666; font-size: 0.95rem; margin: 0; line-height: 1.6;">
                                Comprehensive HR system with employee portal, training programs, and career development opportunities.
                            </p>
                        </div>
                    </div>
                </div>

                <p style="color: #666; font-size: 1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                    We're committed to creating a supportive work environment where employees can thrive. Our integrated HR system provides seamless onboarding, continuous professional development, and transparent communication channels. Join us and be part of a team that values growth, innovation, and collaboration.
                </p>

                <a href="application.php" class="btn btn-primary rounded-pill px-5 py-3" style="background-color: #C82333; border: none; font-weight: 600; font-size: 1rem;">
                    Apply Now
                </a>
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section class="testimonials-section py-5" style="padding-top: 80px; padding-bottom: 80px; background-color: #FFFFFF;">
    <div class="container-fluid px-4 px-md-5">
        <div class="row align-items-start g-5">
            <!-- Left Column - Heading & Navigation -->
            <div class="col-12 col-lg-4">
                <h2 style="color: #111; font-weight: 400; font-size: 2rem; line-height: 1.2; margin-bottom: 2rem;">
                    What Our <span style="color: #C82333; font-weight: 700;">Employees</span> Say <br>About Us
                </h2>

                <!-- Navigation Arrows -->
                <div class="testimonial-nav d-flex gap-2" style="margin-top: 2rem;">
                    <button class="testimonial-arrow-btn testimonial-prev" style="width: 50px; height: 50px; border-radius: 50%; background: #FFFFFF; border: 2px solid #e0e0e0; color: #C82333; font-size: 1.2rem; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button class="testimonial-arrow-btn testimonial-next" style="width: 50px; height: 50px; border-radius: 50%; background: #FFFFFF; border: 2px solid #e0e0e0; color: #C82333; font-size: 1.2rem; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Right Column - Testimonial Cards -->
            <div class="col-12 col-lg-8">
                <div class="testimonials-carousel">
                    <!-- Testimonial 1 -->
                    <div class="testimonial-item" style="background: #FFFFFF; border-radius: 14px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 20px; border: 1px solid #f5f5f5;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <img src="assets/img/testimonial-avatar1.svg" alt="Abdulmujeed Ismail" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                            <div>
                                <h6 style="color: #111; font-weight: 600; margin: 0; font-size: 1rem;">Abdulmujeed Ismail</h6>
                                <p style="color: #999; font-size: 0.85rem; margin: 0;">Senior Developer</p>
                            </div>
                        </div>
                        <p style="color: #666; font-size: 0.95rem; line-height: 1.6; margin: 0;">
                            Working here has been an incredible experience. The professional development opportunities and supportive team environment have helped me grow tremendously in my career. The HR system makes it easy to manage everything from training to leave requests.
                        </p>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="testimonial-item" style="background: #FFFFFF; border-radius: 14px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 20px; border: 1px solid #f5f5f5;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <img src="assets/img/testimonial-avatar2.svg" alt="Eze Weng" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                            <div>
                                <h6 style="color: #111; font-weight: 600; margin: 0; font-size: 1rem;">Eze Weng</h6>
                                <p style="color: #999; font-size: 0.85rem; margin: 0;">Data Analyst</p>
                            </div>
                        </div>
                        <p style="color: #666; font-size: 0.95rem; line-height: 1.6; margin: 0;">
                            The onboarding process was seamless and the continuous training programs keep me updated with the latest industry trends. I appreciate the work-life balance and the transparent communication from management.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HR TEAM SECTION -->
<section class="hr-team-section py-5" style="padding-top: 80px; padding-bottom: 80px; background-color: #FFFFFF;">
    <div class="container-fluid px-4 px-md-5">
        <!-- Heading -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 style="color: #111; font-weight: 700; font-size: 2rem; margin-bottom: 0;">
                    Meet Our HR Team
                </h2>
            </div>
        </div>

        <!-- HR Team Grid -->
        <div class="row g-4">
            <!-- HR Member 1 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="instructor-card">
                    <img src="assets/img/instructor1.svg" alt="HR Manager" class="instructor-img">
                    <div class="instructor-content">
                        <h6 class="instructor-name">Faisal Khan</h6>
                        <p class="instructor-title">HR Manager</p>
                        <p class="instructor-bio">
                            "Building strong teams and fostering a culture of growth and excellence."
                        </p>
                    </div>
                </div>
            </div>

            <!-- HR Member 2 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="instructor-card">
                    <img src="assets/img/instructor2.svg" alt="Recruitment Specialist" class="instructor-img">
                    <div class="instructor-content">
                        <h6 class="instructor-name">Lora Shrof</h6>
                        <p class="instructor-title">Recruitment Specialist</p>
                        <p class="instructor-bio">
                            "Connecting talented professionals with their dream careers."
                        </p>
                    </div>
                </div>
            </div>

            <!-- HR Member 3 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="instructor-card">
                    <img src="assets/img/instructor3.svg" alt="Training Coordinator" class="instructor-img">
                    <div class="instructor-content">
                        <h6 class="instructor-name">John Smith</h6>
                        <p class="instructor-title">Training & Development</p>
                        <p class="instructor-bio">
                            "Empowering employees through continuous learning and skill development."
                        </p>
                    </div>
                </div>
            </div>

            <!-- HR Member 4 -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="instructor-card">
                    <img src="assets/img/team-member4.svg" alt="HR Team Member" class="instructor-img">
                    <div class="instructor-content">
                        <h6 class="instructor-name">HR Team Member</h6>
                        <p class="instructor-title">HR Officer</p>
                        <p class="instructor-bio">
                            "Supporting staff and strengthening our people-first culture."
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA MOTIVATIONAL SECTION -->
    <section class="cta-motivational-section py-5" style="padding-top: 80px; padding-bottom: 80px; background: linear-gradient(135deg, #1a2332 0%, #2d3e50 100%); position: relative; overflow: hidden;">
        <!-- Background decorative elements -->
        <div style="position: absolute; top: -50px; right: -100px; width: 300px; height: 300px; background: rgba(200, 35, 51, 0.1); border-radius: 50%; z-index: 0;"></div>

        <div class="container-fluid px-4 px-md-5" style="position: relative; z-index: 1;">
            <div class="row align-items-center g-5">
                <!-- Left Column - Image -->
                <div class="col-12 col-lg-5">
                    <img src="assets/img/cta-motivation.svg" alt="Join our team" class="img-fluid" style="border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-width: 100%;">
                </div>

                <!-- Right Column - Content -->
                <div class="col-12 col-lg-7" style="padding-left: 2rem;">
                    <h2 style="color: #FFFFFF; font-weight: 400; font-size: 2.2rem; line-height: 1.3; margin-bottom: 1.5rem;">
                        Ready To Take The <br><span style="font-weight: 700;">Next Step</span> In <br>Your Career?
                    </h2>

                    <p style="color: #d0d0d0; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; max-width: 500px;">
                        Join our team and unlock your potential. Browse available positions or access the staff portal to manage your employment.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="d-flex flex-wrap gap-3">
                        <a href="application.php" class="btn btn-primary rounded-pill px-5 py-3" style="background-color: #C82333; border: none; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; text-decoration: none;" onmouseover="this.style.background='#a01c28'" onmouseout="this.style.background='#C82333'">
                            Apply Now
                        </a>
                        <a href="staff/login.php" class="btn rounded-pill px-5 py-3" style="background-color: #FFFFFF; border: 2px solid #FFFFFF; color: #1a2332; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; text-decoration: none;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='#FFFFFF'">
                            Staff Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</section>
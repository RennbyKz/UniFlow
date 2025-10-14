<?php
// student/view_profile_summary_pdf.php
// Data fetching is done in generate_pdf.php (variables: $profile, $skills, $experiences, etc. are available)

// ------------------------------------------
// Helper functions
// ------------------------------------------
function get_base64_image($path) {
    if (empty($path) || !file_exists('../' . $path)) {
        $path = '../assets/images/default_profile.png';
    } else {
        $path = '../' . $path;
    }
    
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        // Use JPEG mime type as Dompdf handles it well
        $mime_type = ($type === 'png') ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime_type . ';base64,' . base64_encode($data);
    }
    return ''; 
}

$profile_photo_base64 = get_base64_image($profile['photo_path'] ?? null);

function format_date_range($start, $end) {
    $start_year = date('Y', strtotime($start));
    $end_text = $end ? date('Y', strtotime($end)) : 'Present';
    return $start_year . ' - ' . $end_text;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($profile['full_name']); ?> - CV</title>
    
    <style>
        /* 💥 Fix: Box-sizing and Single Page Control 💥 */
        * {
            box-sizing: border-box; /* Fixes overlap issue */
        }
        @page { 
            size: A4; 
            margin: 0; 
        }
        body { 
            font-family: 'DejaVu Sans', sans-serif; 
            font-size: 10pt; 
            margin: 0; 
            color: #333; 
            height: 842pt; /* A4 Height */
            width: 595pt;  /* A4 Width */
            overflow: hidden; /* CRUCIAL: Forces single page, cuts off excess content */
        }
        
        .cv-container {
            width: 100%;
            height: 100%; 
            position: absolute; 
            overflow: hidden; 
        }

        /* ----- Left Column (35%) ----- */
        .left-column {
            width: 35%;
            padding: 30px 15px 15px 30px; 
            background-color: #f4f4f4; 
            float: left;
            height: 100%; 
            page-break-inside: avoid;
        }
        
        /* ----- Right Column (65%) ----- */
        .right-column {
            width: 55%;
            padding: 30px 30px 15px 20px;
            float: right;
            height: 100%; 
            page-break-inside: avoid;
        }
        
        /* General Styling */
        .main-header h1 { font-size: 19pt; color: #1a1a1a; margin: 0 0 3px 0; text-transform: uppercase; }
        .main-header h2 { font-size: 11pt; color: #666; margin: 0 0 15px 0; font-weight: normal; }
        .section-box { margin-bottom: 15px; page-break-inside: avoid; }
        .section-title {
            font-size: 12pt; font-weight: bold; color: #4f46e5; 
            border-bottom: 2px solid #4f46e5; padding-bottom: 3px; margin-bottom: 10px;
        }
        .side-heading {
            font-size: 10pt; font-weight: bold; color: #4f46e5; 
            border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-top: 15px; margin-bottom: 8px;
        }

        /* Profile Photo */
        .profile-photo {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover; 
            border: 3px solid #4f46e5; margin-bottom: 10px;
        }

        /* Item Details (Dense content style) */
        .item-box {
            margin-bottom: 10px; border-left: 3px solid #10b981; padding-left: 8px; font-size: 9pt; 
        }
        .item-title { font-size: 10pt; font-weight: bold; margin-bottom: 0; }
        .item-meta { font-size: 8pt; color: #666; margin-bottom: 3px; }
        .item-meta span { margin-right: 10px; }
        .contact-info p, .skill-item { font-size: 8.5pt; margin: 3px 0; }
        .item-box p { font-size: 8.5pt; line-height: 1.3; margin-top: 3px; }
        body, p, span { line-height: 1.2; } /* Maximize space */

    </style>
</head>
<body>
    <div class="cv-container">
        <div class="left-column">
            
            <?php if (!empty($profile_photo_base64)): ?>
                <img src="<?php echo $profile_photo_base64; ?>" alt="Profile Photo" class="profile-photo"/>
            <?php endif; ?>

            <div class="main-header">
                <h1 style="color: #4f46e5;"><?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?></h1>
                <h2 style="color: #333; font-weight: normal;"><?php echo htmlspecialchars($profile['major'] ?? 'Student'); ?></h2>
            </div>
            
            <div class="section-box">
                <div class="side-heading">CONTACT</div>
                <div class="contact-info">
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['contact_phone'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                    <p><strong>D.O.B:</strong> <?php echo htmlspecialchars($profile['dob'] ?? 'N/A'); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($profile['address'] ?? 'N/A'); ?></p>
                    <p><strong>LinkedIn:</strong> <?php echo htmlspecialchars($profile['linkedin_url'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="section-box">
                <div class="side-heading">EDUCATION</div>
                <div class="item-box">
                    <div class="item-title"><?php echo htmlspecialchars($profile['university_name'] ?? 'N/A'); ?></div>
                    <div class="item-meta">
                        <span><?php echo htmlspecialchars($profile['major'] ?? 'N/A'); ?></span>
                        <span>Year: <?php echo htmlspecialchars($profile['year_of_study'] ?? 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($profile['gpa'])): ?>
                        <p style="font-size: 9pt; color: #10b981; font-weight: bold;">GPA: <?php echo htmlspecialchars($profile['gpa']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($skills)): ?>
            <div class="section-box">
                <div class="side-heading">SKILLS</div>
                <?php foreach ($skills as $skill): ?>
                    <div class="skill-item">
                        <strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong> (<?php echo htmlspecialchars($skill['proficiency_level']); ?>)
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
        
        <div class="right-column">
            
            <div class="section-box">
                <div class="section-title">PROFILE SUMMARY</div>
                <p style="font-size: 9pt; line-height: 1.4; color: #555;">
                    <?php echo htmlspecialchars($profile['summary'] ?? 'Detail-oriented and motivated student seeking to leverage academic knowledge and technical skills in a professional environment.'); ?>
                </p>
            </div>

            <div class="section-box">
                <div class="section-title">WORK EXPERIENCE</div>
                <?php if (empty($experiences)): ?>
                    <p style="font-size: 9pt; color: #666;">No professional work experience added yet.</p>
                <?php else: ?>
                    <?php foreach ($experiences as $exp): ?>
                        <div class="item-box">
                            <div class="item-title"><?php echo htmlspecialchars($exp['title']); ?></div>
                            <div class="item-meta">
                                <span><?php echo htmlspecialchars($exp['company']); ?></span>
                                <span style="font-style: italic; color: #4f46e5;"><?php echo format_date_range($exp['start_date'], $exp['end_date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($projects)): ?>
            <div class="section-box">
                <div class="section-title">KEY PROJECTS</div>
                <?php foreach ($projects as $project): ?>
                    <div class="item-box">
                        <div class="item-title"><?php echo htmlspecialchars($project['title']); ?></div>
                        <div class="item-meta">
                            <span>**Technologies:** <?php echo htmlspecialchars($project['technologies']); ?></span>
                            <?php if (!empty($project['project_url'])): ?>
                                <span style="color: #10b981;">[Link]</span>
                            <?php endif; ?>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="section-box">
                <div class="section-title">CERTIFICATES & AWARDS</div>
                <?php if (empty($certificates)): ?>
                    <p style="font-size: 9pt; color: #666;">No certificates added yet.</p>
                <?php else: ?>
                    <?php foreach ($certificates as $cert): ?>
                        <div class="item-box">
                            <div class="item-title"><?php echo htmlspecialchars($cert['cert_name']); ?></div>
                            <div class="item-meta">
                                <span>Issuer: <?php echo htmlspecialchars($cert['issuer']); ?></span>
                                <span>Issue Date: <?php echo htmlspecialchars($cert['issue_date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</body>
</html>
<?php
require_once 'auth.php';
require_once 'config.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>

<div class="main">
    <nav class="navbar navbar-expand navbar-light navbar-bg">
        <a class="sidebar-toggle js-sidebar-toggle">
            <i class="hamburger align-self-center"></i>
        </a>

        <div class="navbar-collapse collapse">
            <ul class="navbar-nav navbar-align">
                <li class="nav-item">
                    <span class="nav-link"><strong>Help &amp; About</strong></span>
                </li>
            </ul>
        </div>
    </nav>

    <main class="content">
        <div class="container-fluid p-0">

            <h1 class="h3 mb-3"><strong>Help &amp; About</strong></h1>

            <!-- HOW VERIFICATION WORKS -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">How domain verification works</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        New websites are created with status <strong>Pending verification</strong>.
                        This is a security step – only domains you actually control will be monitored.
                    </p>
                    <ol>
                        <li>Add a new website in the system. It will appear as <strong>Pending</strong>.</li>
                        <li>Click <strong>“Download token”</strong> for that website and download the <code>.txt</code> file.</li>
                        <li>
                            Upload that <code>.txt</code> file to the <strong>web root</strong> of the website, so that it is publicly accessible, for example:<br>
                            <code>https://your-domain.com/verification-token-XXXXX.txt</code>
                        </li>
                        <li>
                            Go back to the monitoring app and click <strong>“Verify”</strong> next to that website.
                        </li>
                        <li>
                            If the file is found and the token matches, the website status changes to
                            <strong>Active</strong> and monitoring starts.
                        </li>
                    </ol>
                    <p class="mb-0">
                        After successful verification you can safely <strong>delete the token file</strong>
                        from the web server.
                    </p>
                </div>
            </div>

            <!-- ABUSEIPDB / DNS & IP -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">DNS &amp; IP reputation (AbuseIPDB)</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        In the <strong>DNS &amp; IP tools</strong> section you can see the current IP address of each
                        active website and its <strong>AbuseIPDB score</strong> (0–100).
                        This score represents how often that IP has been reported as potentially abusive
                        (spam, scans, attacks, etc.).
                    </p>
                    <ul>
                        <li><strong>0</strong> – no reports (clean IP).</li>
                        <li><strong>1–10</strong> – low risk.</li>
                        <li><strong>10–50</strong> – elevated risk, IP has been reported multiple times.</li>
                        <li><strong>50+</strong> – high risk, recommended to review traffic or hosting provider.</li>
                    </ul>
                    <p class="mb-0">
                        IP reputation data is provided by
                        <a href="https://www.abuseipdb.com/" target="_blank" rel="noopener">
                            AbuseIPDB
                        </a>.
                    </p>
                </div>
            </div>

            <!-- CRON JOBS -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cron jobs (automation)</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        To run checks and send alerts/reports automatically, you need to configure
                        <strong>cron jobs</strong> on your server or hosting control panel.
                    </p>

                    <h6 class="fw-bold">1. Main site check (runs every minute)</h6>
                    <p class="mb-1">
                        This cron runs the main script that checks all websites that are due for a check
                        (based on their interval in the database).
                    </p>
                    <p class="mb-1"><strong>Linux crontab example:</strong></p>
                    <pre class="mb-2"><code>* * * * * /usr/bin/php /path/to/monitoring/check-all.php &gt; /dev/null 2&gt;&amp;1</code></pre>
                    <p class="mb-3 small text-muted">
                        Replace <code>/usr/bin/php</code> and <code>/path/to/monitoring</code> with actual paths on your server.
                    </p>

                    <h6 class="fw-bold">2. DNS &amp; IP checks (optional)</h6>
                    <p class="mb-1">
                        Example: run DNS and IP reputation checks every 6 hours:
                    </p>
                    <pre class="mb-2"><code>0 */6 * * * /usr/bin/php /path/to/monitoring/check-dns.php &gt; /dev/null 2&gt;&amp;1</code></pre>

                    <h6 class="fw-bold">3. SSL certificate checks (optional)</h6>
                    <p class="mb-1">
                        Example: run SSL checks once per day at 03:00:
                    </p>
                    <pre class="mb-2"><code>0 3 * * * /usr/bin/php /path/to/monitoring/check-ssl.php &gt; /dev/null 2&gt;&amp;1</code></pre>

                    <h6 class="fw-bold">4. Alerts &amp; PDF reports</h6>
                    <p class="mb-1">
                        This cron triggers sending of email alerts and scheduled PDF reports based on the
                        <strong>alert rules</strong> and <strong>report rules</strong> you configured in the app.
                    </p>
                    <p class="mb-1"><strong>Example (run every 15 minutes):</strong></p>
                    <pre class="mb-2"><code>*/15 * * * * /usr/bin/php /path/to/monitoring/send-alerts.php &gt; /dev/null 2&gt;&amp;1</code></pre>

                    <hr>

                    <h6 class="fw-bold">Using cPanel / Plesk / shared hosting</h6>
                    <p class="mb-1">
                        On shared hosting you usually have a <strong>"Cron Jobs"</strong> or <strong>"Scheduled Tasks"</strong>
                        section. Use commands similar to:
                    </p>
                    <pre class="mb-2"><code>php -q /home/USERNAME/public_html/monitoring/check-all.php
php -q /home/USERNAME/public_html/monitoring/send-alerts.php</code></pre>
                    <p class="mb-0 small text-muted">
                        Replace <code>/home/USERNAME/public_html/monitoring</code> with the actual path to your installation.
                    </p>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/templates/footer.php'; ?>
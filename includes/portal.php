<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function portalSectionDefinition(string $section): array
{
    $definitions = [
        'admin' => [
            'eyebrow' => administration_portal_eyebrow('admin'),
            'brand' => administration_portal_brand('admin'),
            'home' => 'dashboard.php',
            'links' => [
                ['key' => 'dashboard', 'label' => 'Overview', 'href' => 'dashboard.php'],
              ['key' => 'student-ids', 'label' => 'Access & IDs', 'href' => 'user-management.php'],
                ['key' => 'student-directory', 'label' => 'Student Accounts', 'href' => 'student-directory.php'],
                ['key' => 'staff-management', 'label' => 'Staff Accounts', 'href' => 'staff-management.php'],
                ['key' => 'exam-management', 'label' => 'Exam Setup', 'href' => 'exam-management.php'],
                ['key' => 'staff-activity', 'label' => 'Staff Activity', 'href' => 'staff-activity.php'],
                ['key' => 'reports', 'label' => 'Report Control', 'href' => 'report-control.php'],
                ['key' => 'announcements', 'label' => 'Announcements', 'href' => 'announcements.php'],
                ['key' => 'server-control', 'label' => 'Server Access', 'href' => 'server-control.php'],
            ],
        ],
        'staff' => [
            'eyebrow' => administration_portal_eyebrow('staff'),
            'brand' => administration_portal_brand('staff'),
            'home' => 'dashboard.php',
            'links' => [
                ['key' => 'dashboard', 'label' => 'Overview', 'href' => 'dashboard.php'],
                ['key' => 'class-lists', 'label' => 'Class Lists', 'href' => 'class-lists.php'],
                ['key' => 'marks-input', 'label' => 'Marks Input', 'href' => 'marks-input.php'],
                ['key' => 'assessment-sheet', 'label' => 'Assessment Sheet', 'href' => 'assessment-sheet.php'],
                ['key' => 'timetable', 'label' => 'Timetable', 'href' => 'timetable.php'],
            ],
        ],
        'student' => [
            'eyebrow' => administration_portal_eyebrow('student'),
            'brand' => administration_portal_brand('student'),
            'home' => 'dashboard.php',
            'links' => [
                ['key' => 'dashboard', 'label' => 'Overview', 'href' => 'dashboard.php'],
                ['key' => 'academics', 'label' => 'Academics', 'href' => 'academics.php'],
                ['key' => 'assessment-sheet', 'label' => 'Assessment Sheet', 'href' => 'assessment-sheet.php'],
                ['key' => 'fees', 'label' => 'Fees', 'href' => 'fees-tracker.php'],
                ['key' => 'messages', 'label' => 'Announcements', 'href' => 'messages.php'],
                ['key' => 'events', 'label' => 'Events', 'href' => 'event.php'],
                ['key' => 'history', 'label' => 'Highlights', 'href' => 'history.php'],
            ],
        ],
    ];

    return $definitions[$section] ?? $definitions['student'];
}

function portalAlertClass(string $alertType): string
{
    return match ($alertType) {
        'success' => 'alert-card-success',
        'warning' => 'alert-card-warning',
        default => 'alert-card-info',
    };
}

function portalBadgeClass(string $category): string
{
    return match ($category) {
        'reports' => 'badge text-bg-success',
        'marks' => 'badge text-bg-primary',
        default => 'badge text-bg-secondary',
    };
}

function portalUserInitials(string $userName): string
{
  $segments = preg_split('/\s+/', trim($userName)) ?: [];
  $initials = '';

  foreach ($segments as $segment) {
    $segment = trim((string) $segment);
    if ($segment === '') {
      continue;
    }

    $initials .= strtoupper(substr($segment, 0, 1));
    if (strlen($initials) >= 2) {
      break;
    }
  }

  return $initials !== '' ? $initials : 'U';
}

function renderPortalNavigation(string $section, string $activeKey, string $userName): void
{
    $definition = portalSectionDefinition($section);
    ?>
    <header class="portal-header">
      <nav class="navbar navbar-expand-lg navbar-dark admin-navbar" aria-label="Portal navigation">
        <div class="container-fluid portal-navbar-shell">
          <a class="navbar-brand portal-brand" href="<?php echo htmlspecialchars($definition['home'], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="portal-eyebrow"><?php echo htmlspecialchars($definition['eyebrow'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="portal-brand-title"><?php echo htmlspecialchars($definition['brand'], ENT_QUOTES, 'UTF-8'); ?></span>
          </a>

          <button
            class="navbar-toggler portal-navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#portalNavigation"
            aria-controls="portalNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
          >
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse" id="portalNavigation">
            <ul class="navbar-nav ms-auto portal-nav">
              <?php foreach ($definition['links'] as $link): ?>
                <li class="nav-item">
                  <a
                    class="nav-link portal-nav-link <?php echo $link['key'] === $activeKey ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $link['key'] === $activeKey ? 'aria-current="page"' : ''; ?>
                  >
                    <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </li>
              <?php endforeach; ?>

              <li class="nav-item portal-nav-user">
                <span class="portal-user-chip">
                  <span class="portal-user-label">Signed in as</span>
                  <span class="portal-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
              </li>

              <li class="nav-item portal-logout-item">
                <a class="nav-link portal-nav-link portal-logout-link" href="../auth/logout.php">Logout</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
    </header>
    <?php
}

function formatPortalDateTime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y, H:i', $timestamp);
}

function portalIsFragmentRequest(string $fragmentKey): bool
{
  $requestedFragment = trim((string) ($_REQUEST['partial'] ?? ''));
  $isXmlHttpRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

  return $isXmlHttpRequest && $requestedFragment === $fragmentKey;
}

function portalRenderFragment(callable $renderer): void
{
  ob_start();
  $renderer();
  $contents = ob_get_clean();

  if (!is_string($contents)) {
    $contents = '';
  }

  header('Content-Type: text/html; charset=UTF-8');
  echo $contents;
  exit;
}
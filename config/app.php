<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function administration_config_text(string $name, string $default): string
{
    $value = trim((string) administration_env($name, $default));

    return $value === '' ? $default : $value;
}

function administration_app_name(): string
{
    return administration_config_text('APP_NAME', 'Administration Suite');
}

function administration_organization_name(): string
{
    return administration_config_text('APP_ORGANIZATION', 'Your School');
}

function administration_login_badge(): string
{
    return administration_config_text('APP_LOGIN_BADGE', 'School Administration');
}

function administration_login_kicker(): string
{
    return administration_config_text('APP_LOGIN_KICKER', administration_app_name());
}

function administration_login_title(): string
{
    return administration_config_text(
        'APP_LOGIN_TITLE',
        'A professional control center for students, staff, and school leadership.'
    );
}

function administration_login_copy(): string
{
    return administration_config_text(
        'APP_LOGIN_COPY',
        'Manage student records, internal communication, fees, academic reporting, and school operations from one secure system designed for institutional use.'
    );
}

function administration_support_copy(): string
{
    $default = 'Need access or credential recovery? Contact the administration office for account setup or PIN assistance.';

    return administration_config_text('APP_SUPPORT_COPY', $default);
}

function administration_portal_eyebrow(string $section): string
{
    return match ($section) {
        'admin' => administration_config_text('APP_ADMIN_EYEBROW', 'Administration'),
        'staff' => administration_config_text('APP_STAFF_EYEBROW', 'Staff'),
        'student' => administration_config_text('APP_STUDENT_EYEBROW', 'Student'),
        default => administration_app_name(),
    };
}

function administration_portal_brand(string $section): string
{
    return match ($section) {
        'admin' => administration_config_text('APP_ADMIN_BRAND', administration_app_name()),
        'staff' => administration_config_text('APP_STAFF_BRAND', 'Staff Portal'),
        'student' => administration_config_text('APP_STUDENT_BRAND', 'Student Portal'),
        default => administration_app_name(),
    };
}

function administration_footer_name(): string
{
    return administration_config_text('APP_FOOTER_NAME', administration_app_name());
}

function administration_application_path(string $path = ''): string
{
    return administration_application_base_path() . ltrim($path, '/');
}

function administration_asset_url(string $assetPath): string
{
    return administration_application_path('assets/' . ltrim($assetPath, '/'));
}

function renderAdministrationFooter(): void
{
    ?>
    <footer>&copy; <span data-year></span> <?php echo htmlspecialchars(administration_footer_name(), ENT_QUOTES, 'UTF-8'); ?></footer>
    <?php
}

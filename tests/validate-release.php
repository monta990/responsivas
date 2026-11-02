<?php
declare(strict_types=1);

/**
 * Validate release metadata, expected production layout, route contracts,
 * security-sensitive implementation guards, translations and image assets.
 */
$root = dirname(__DIR__);
$errors = [];
$checks = 0;

$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required file not found: %s', $relative));
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        throw new RuntimeException(sprintf('Unable to read file: %s', $relative));
    }
    return $content;
};

$expect = static function (bool $condition, string $message) use (&$errors, &$checks): void {
    $checks++;
    if (!$condition) {
        $errors[] = $message;
    }
};

$setup = $read('setup.php');
$hook = $read('hook.php');
$pluginXml = $read('plugin.xml');
$readme = $read('README.md');
$changelog = $read('CHANGELOG.md');

$expect(
    preg_match("/define\\('PLUGIN_RESPONSIVAS_VERSION',\\s*'([^']+)'\\);/", $setup, $versionMatch) === 1,
    'setup.php must declare PLUGIN_RESPONSIVAS_VERSION.'
);
$version = $versionMatch[1] ?? '';
$expect($version !== '', 'Plugin version must not be empty.');
$expect(
    preg_match("/'minphpversion'\\s*=>\\s*'([^']+)'/", $setup, $phpMatch) === 1
        && ($phpMatch[1] ?? '') === '8.2',
    'setup.php must keep PHP 8.2 as the minimum requirement.'
);
$expect(
    str_contains($setup, "version_compare(PHP_VERSION, '8.2', '<')"),
    'Plugin activation must enforce its declared PHP minimum.'
);
$expect(
    str_contains($setup, "PLUGIN_RESPONSIVAS_MIN_GLPI', '11.0'")
        && str_contains($setup, "PLUGIN_RESPONSIVAS_MAX_GLPI', '12.99'"),
    'setup.php must retain the published GLPI 11/12 compatibility range.'
);
$expect(
    preg_match('/<key>responsivas<\\/key>/', $pluginXml) === 1
        && preg_match('/<name>Responsivas<\\/name>/', $pluginXml) === 1,
    'plugin.xml must identify the published Responsivas plugin.'
);
$expect(
    str_contains($pluginXml, '<num>' . $version . '</num>')
        && str_contains($pluginXml, 'responsivas-' . $version . '.zip'),
    'plugin.xml version and download URL must match setup.php.'
);
$expect(
    str_contains($pluginXml, '<compatibility>~11.0</compatibility>')
        && str_contains($pluginXml, '<compatibility>~12.0</compatibility>'),
    'plugin.xml must advertise both GLPI 11 and GLPI 12 compatibility.'
);
$expect(
    str_contains($readme, '11.x / 12.x')
        && str_contains($readme, '8.2+ on GLPI 11')
        && str_contains($readme, '8.3+ on GLPI 12'),
    'README requirements must match the plugin metadata.'
);
$expect(
    preg_match('/^##\\s+\\[' . preg_quote($version, '/') . '\\]/m', $changelog) === 1,
    'CHANGELOG.md must have an entry for the current published plugin version.'
);

$requiredFiles = [
    'CHANGELOG.md',
    'LICENSE',
    'README.md',
    'config.png',
    'hook.php',
    'logo.png',
    'plugin.xml',
    'setup.php',
    'user.png',
    'src/Paths.php',
    'src/Generator.php',
    'src/Pdf/PDF.php',
    'src/Pdf/PdfBuilder.php',
    'src/Twig.php',
    'src/UserTab.php',
    'src/Utils.php',
    'src/Controller/ConfigController.php',
    'src/Controller/InspectionController.php',
    'src/Controller/MailController.php',
    'src/Controller/ManualFormController.php',
    'src/Controller/PdfController.php',
    'src/Controller/PreviewController.php',
    'src/Controller/ResourceController.php',
    'src/Controller/ReturnController.php',
    'src/Service/ConfigService.php',
    'src/Service/MailService.php',
    'src/Service/UpdateChecker.php',
    'locales/responsivas.pot',
    'locales/es_MX.po',
    'locales/es_MX.mo',
    'locales/fr_FR.po',
    'locales/fr_FR.mo',
    'locales/de_DE.po',
    'locales/de_DE.mo',
    'locales/it_IT.po',
    'locales/it_IT.mo',
    'templates/config/page.html.twig',
    'templates/config/tab_email.html.twig',
    'templates/config/tab_general.html.twig',
    'templates/config/tab_pc.html.twig',
    'templates/config/tab_pri.html.twig',
    'templates/config/tab_pho.html.twig',
    'templates/partials/footer_fields.html.twig',
    'templates/partials/format_toolbar.html.twig',
    'templates/partials/ribbon_header.html.twig',
    'templates/partials/template_editor.html.twig',
    'templates/partials/variable_hints.html.twig',
    'templates/user/tab.html.twig',
    'assets/schematics/computer_general.png',
    'assets/schematics/printer_general.png',
    'assets/schematics/phone_general.png',
    'assets/schematics/damage_icons/scratch.png',
    'assets/schematics/damage_icons/impact.png',
    'assets/schematics/damage_icons/wear.png',
    'assets/schematics/damage_icons/missing.png',
];

foreach ($requiredFiles as $relative) {
    $expect(
        is_file($root . DIRECTORY_SEPARATOR . $relative),
        sprintf('Published plugin file is missing: %s', $relative)
    );
}

foreach (['src/Controller', 'src/Service', 'src/Pdf', 'templates/config', 'templates/partials', 'templates/user', 'locales', 'assets/schematics'] as $relative) {
    $expect(is_dir($root . '/' . $relative), sprintf('Published directory is missing: %s', $relative));
}

foreach (['composer.json', 'composer.lock', 'vendor', 'front', 'ajax'] as $forbidden) {
    $expect(!file_exists($root . '/' . $forbidden), sprintf('Unexpected runtime/development path found at plugin root: %s', $forbidden));
}

$inspectionRoutes = [
    '/inspection',
    '/inspeccion-visual',
    '/inspection-visuelle',
    '/sichtpruefung',
    '/ispezione-visiva',
];
$returnRoutes = [
    '/return',
    '/devolucion',
    '/restitution',
    '/rueckgabe',
    '/restituzione',
];

$inspectionSource = $read('src/Controller/InspectionController.php');
$returnSource = $read('src/Controller/ReturnController.php');
foreach ($inspectionRoutes as $route) {
    $expect(str_contains($inspectionSource, "#[Route('" . $route . "'"), 'Published inspection route disappeared: ' . $route);
}
foreach ($returnRoutes as $route) {
    $expect(str_contains($returnSource, "#[Route('" . $route . "'"), 'Published return route disappeared: ' . $route);
}

$previewSource = $read('src/Controller/PreviewController.php');
foreach ([
    'manual_pc_inspection',
    'manual_pc_return',
    'manual_pri_inspection',
    'manual_pri_return',
    'manual_pho_inspection',
    'manual_pho_return',
] as $preview) {
    $expect(str_contains($previewSource, "'" . $preview . "'"), 'Manual PDF preview type disappeared: ' . $preview);
}

$manualSource = $read('src/Controller/ManualFormController.php');
$pdfSource = $read('src/Controller/PdfController.php');
$mailController = $read('src/Controller/MailController.php');
$resourceSource = $read('src/Controller/ResourceController.php');
$configSource = $read('src/Service/ConfigService.php');
$pdfBuilder = $read('src/Pdf/PdfBuilder.php');
$pdfBase = $read('src/Pdf/PDF.php');

$expect(str_contains($manualSource, "Session::haveRight('user', READ)"), 'Manual PDF endpoint must check GLPI user read permission.');
$expect(str_contains($manualSource, '$targetUser->can($userId, READ)'), 'Manual PDF endpoint must authorize the target user.');
$expect(str_contains($previewSource, "Session::haveRight('config', UPDATE)"), 'Configuration previews must require config update rights.');
$expect(str_contains($mailController, "\$request->isMethod('POST')"), 'Mail endpoint must reject non-POST requests.');
$expect(str_contains($resourceSource, "Session::checkRight('config', UPDATE)"), 'Institutional logo resource must be protected by config permissions.');
$expect(str_contains($pdfBuilder, '->canView()') && str_contains($pdfBuilder, 'haveAccessToEntity'), 'PDF asset queries must enforce item and entity visibility.');
$expect(str_contains($pdfSource, 'random_bytes(32)'), 'PDF download tickets must use cryptographically strong tokens.');
$expect(str_contains($pdfSource, 'expires') && str_contains($pdfSource, 'is_file($path)'), 'PDF tickets must be time-limited and tied to an existing file.');
$expect(str_contains($pdfSource, 'X-Content-Type-Options'), 'PDF responses must set nosniff.');
$expect(str_contains($configSource, 'exportableKeys()'), 'Configuration export/import must use an explicit allow-list.');
$expect(str_contains($configSource, 'base64_decode((string)$data[\'logo\'][\'data\'], true)'), 'Logo imports must use strict Base64 decoding.');
$expect(str_contains($configSource, "=== 'image/png'") && str_contains($configSource, '500 * 1024'), 'Logo imports must validate MIME and size.');
$expect(str_contains($pdfBase, 'getAutoPageBreak()') && str_contains($pdfBase, 'getBreakMargin()') && str_contains($pdfBase, 'finally'), 'PDF footer must restore TCPDF page-break state.');
$expect(str_contains($pdfSource, 'Output($filename, \'S\')'), 'PDF generation must use in-memory output for GLPI 11/12 compatibility.');

$pageTemplate = $read('templates/config/page.html.twig');
$userTemplate = $read('templates/user/tab.html.twig');
$expect(str_contains($pageTemplate, 'name="_glpi_csrf_token"'), 'Configuration form must include the GLPI CSRF token.');
$expect(str_contains($pageTemplate, 'responsivasTabs'), 'Configuration page must preserve the published tab navigation.');
foreach (['tab_email', 'tab_general', 'tab_pc', 'tab_pri', 'tab_pho'] as $template) {
    $expect(is_file($root . '/templates/config/' . $template . '.html.twig'), 'Configuration tab template is missing: ' . $template);
}
$expect(str_contains($userTemplate, 'name="_glpi_csrf_token"'), 'User actions must include CSRF tokens.');
$expect(str_contains($userTemplate, 'data-resp-pdf-form'), 'PDF generation form contract disappeared from the user tab.');
$expect(str_contains($hook, 'migrateConfig') && str_contains($hook, "'migrate'=>'keep'"), 'Configuration lifecycle must preserve the schema migration policy.');

$expectedLocales = [
    'es_MX' => 'es_MX',
    'fr_FR' => 'fr_FR',
    'de_DE' => 'de_DE',
    'it_IT' => 'it_IT',
];
foreach ($expectedLocales as $locale => $language) {
    $po = $read('locales/' . $locale . '.po');
    $moPath = $root . '/locales/' . $locale . '.mo';
    $expect(str_contains($po, '"Language: ' . $language . '\\n"'), $locale . ' PO catalog has an incorrect language header.');
    $expect(is_file($moPath) && filesize($moPath) > 0, $locale . ' compiled MO catalog is missing or empty.');
}

$schematics = [
    'assets/schematics/computer_general.png',
    'assets/schematics/printer_general.png',
    'assets/schematics/phone_general.png',
    'assets/schematics/damage_icons/scratch.png',
    'assets/schematics/damage_icons/impact.png',
    'assets/schematics/damage_icons/wear.png',
    'assets/schematics/damage_icons/missing.png',
    'logo.png',
    'config.png',
    'user.png',
];
foreach ($schematics as $relative) {
    $image = @getimagesize($root . '/' . $relative);
    $expect(
        is_array($image) && ($image['mime'] ?? '') === 'image/png',
        sprintf('Expected a valid PNG image: %s', $relative)
    );
}

if ($errors !== []) {
    fwrite(STDERR, "Responsivas release validation failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Responsivas %s release metadata, security contracts, UI resources and layout are valid (%d checks).\n",
    $version,
    $checks
));

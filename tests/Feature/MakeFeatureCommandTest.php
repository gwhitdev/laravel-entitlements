<?php

use Illuminate\Support\Str;

$suffix = Str::random(8);

$sharedEnumClass = "App\\Enums\\TestFeature_{$suffix}";
$sharedEnumPath = null;
$sharedTmpDir = null;

beforeEach(function () use (&$sharedTmpDir, &$sharedEnumPath, $sharedEnumClass) {
    $basename = class_basename($sharedEnumClass);

    if ($sharedTmpDir === null) {
        $sharedTmpDir = sys_get_temp_dir()."/entitlements_make_test_{$basename}";
    }

    $this->tmpDir = $sharedTmpDir;
    $this->enumClass = $sharedEnumClass;

    if (! is_dir($this->tmpDir.'/Enums')) {
        mkdir($this->tmpDir.'/Enums', 0755, true);
    }

    if ($sharedEnumPath === null) {
        $sharedEnumPath = $this->tmpDir.'/Enums/'.$basename.'.php';
    }

    file_put_contents($sharedEnumPath, "<?php\n\nnamespace App\\Enums;\n\nenum {$basename}: string\n{\n}\n");

    require_once $sharedEnumPath;

    $this->enumPath = $sharedEnumPath;

    config()->set('entitlements.enum', $this->enumClass);
});

afterAll(function () use ($sharedEnumPath, &$sharedTmpDir) {
    foreach (glob($sharedTmpDir.'/Enums/*') as $file) {
        unlink($file);
    }
    @rmdir($sharedTmpDir.'/Enums');
    @rmdir($sharedTmpDir);
});

it('appends a case to an existing enum', function () {
    $this->artisan('entitlements:make', ['name' => 'DarkMode'])
        ->assertSuccessful();

    $contents = file_get_contents($this->enumPath);

    expect($contents)->toContain("case DarkMode = 'dark_mode';");
});

it('is idempotent and does not duplicate an existing case', function () {
    $this->artisan('entitlements:make', ['name' => 'DarkMode'])->assertSuccessful();
    $this->artisan('entitlements:make', ['name' => 'DarkMode'])
        ->assertSuccessful()
        ->expectsOutputToContain('already exists');

    $contents = file_get_contents($this->enumPath);

    expect(substr_count($contents, "case DarkMode = 'dark_mode';"))->toBe(1);
});

it('creates the enum file if it does not exist', function () {
    $class = 'App\\Enums\\NewFeature';
    $basename = class_basename($class);

    unlink($this->enumPath);

    config()->set('entitlements.enum', $class);

    $this->artisan('entitlements:make', ['name' => 'ExportCsv'])
        ->assertSuccessful()
        ->expectsOutputToContain('created');

    $appPath = app_path("Enums/{$basename}.php");

    expect(file_exists($appPath))->toBeTrue();

    $contents = file_get_contents($appPath);

    expect($contents)->toContain('namespace App\\Enums;')
        ->toContain('enum NewFeature: string')
        ->toContain("case ExportCsv = 'export_csv';");

    unlink($appPath);
});

it('prints PlanFeature::grant reminder', function () {
    $this->artisan('entitlements:make', ['name' => 'DarkMode'])
        ->expectsOutputToContain('PlanFeature::grant');
});

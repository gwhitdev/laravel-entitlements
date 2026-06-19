<?php

namespace Entitlements\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;

class MakeFeatureCommand extends Command
{
    protected $signature = 'entitlements:make
                            {name : StudlyCase feature name}';

    protected $description = 'Add a case to the feature enum';

    public function handle(): int
    {
        $enumClass = config('entitlements.enum');

        $studly = Str::studly($this->argument('name'));
        $value = Str::snake($this->argument('name'));

        if (! class_exists($enumClass)) {
            $basename = class_basename($enumClass);
            $namespace = 'App\\Enums';
            $path = app_path("Enums/{$basename}.php");

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, "<?php\n\nnamespace {$namespace};\n\nenum {$basename}: string\n{\n}\n");

            $this->info("Feature enum created at {$path}.");

            require_once $path;
        }

        $reflection = new ReflectionClass($enumClass);
        $file = $reflection->getFileName();

        $contents = file_get_contents($file);
        $lines = file($file, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            if (str_contains($line, "'{$value}'")) {
                $this->info("Feature '{$value}' already exists in {$enumClass}.");

                return self::SUCCESS;
            }
        }

        $closingBracePos = strrpos($contents, '}');

        $insert = "\n    case {$studly} = '{$value}';\n";

        $newContents = substr_replace($contents, $insert, $closingBracePos, 0);

        file_put_contents($file, $newContents);

        $this->info("Added <comment>{$enumClass}::{$studly}</comment> = '{$value}'.");

        $this->line('');
        $this->line('Grant it to a plan:');
        $this->line("  <info>PlanFeature::grant('your_price_id', Feature::{$studly});</info>");

        return self::SUCCESS;
    }
}

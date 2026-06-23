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
        $name = (string) $this->argument('name');

        // The name is written verbatim into generated PHP source, so constrain it to a safe
        // identifier shape. Without this, a crafted argument could inject code into the enum file.
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            $this->error('Feature name must start with a letter and contain only letters and digits.');

            return self::FAILURE;
        }

        $enumClass = config('entitlements.enum');

        $studly = Str::studly($name);
        $value = Str::snake($name);

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

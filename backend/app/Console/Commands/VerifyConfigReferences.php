<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class VerifyConfigReferences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:verify
                    {--paths= : Comma-separated list of paths to scan (default: routes,config)}
                    {--exclude=vendor,: Comma-separated list of  directories to exclude}
                    {--fix : Autimatically fix simple mismatches (experimental usage, be careful when you use)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify all config() references in php files against existing config values';

    protected $files;

    /**
    * Create a new command instance.
    *
    * @param  Filesystem  $files
    * @return void
    */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verifying config() references...');

        $paths = $this->option('paths') ? explode(',', $this->option('paths'))
            : ['routes', 'config', 'app/Http/Controllers/API', 'app/Http/Middleware', 'app/Http/Requests',
            'app/Http/Resources', 'app/Policies', 'app/Services', 'app/Providers',
            'app/Events', 'app/Domain', 'app/Contracts/Repositories', 'app/Listeners', 'app/Jobs',
            'app/Domain/Receivables/*'];

        $exclude = $this->option('exclude') ?  explode(',', $this->option('exclude'))
            : ['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache'];

        $require_fix = $this->option('fix') ? true:false;

        $all_files = collect();

        foreach ($paths as $path) {
            $base_path = base_path($path);
            if ($this->files->isDirectory($base_path)) {
                $files = Finder::create()
                    ->in($base_path)
                    ->name('*.php')
                    ->ignoreVCS(true)
                    ->ignoreDotFiles(true);

                foreach ($exclude as $exclude_file) {
                    $files->exclude($exclude_file);
                }

                $files_iterator = $files->getIterator();
                $all_files = $all_files->merge(iterator_to_array($files_iterator));
            } elseif ($this->files->exists($base_path)) {
                $all_files->push($this->files->file($base_path));
            }
        }

        $problem_lines = [];
        $fix_cnt = 0;

        foreach ($all_files as $file) {
            $content = $this->files->get($file->getRealPath());
            $relative_path = $file->getRelativePathName();

            preg_match_all("/config\(\s*(['\"])(.*?)\\1\s*\)/", $content, $matches, PREG_SET_ORDER);

            foreach($matches as $match ){
                $exact_match = $match[0];
                $quote = $match[1];
                $config_key = trim($match[2]);

                if (empty($config_key)) {
                    $problem_lines[] = [
                        'file' => $relative_path,
                        'line' => $this->getLineNumber($content, $exact_match),
                        'issue' => 'Empty config() key',
                        'match' => $exact_match
                    ];
                    continue;
                }

                if (is_null(config($config_key))) {
                    $problem_lines[] = [
                        'file' => $relative_path,
                        'line' => $this->getLineNumber($content, $exact_match),
                        'issue' => "Config key {$config_key} not found",
                        'match' => $exact_match
                    ];

                    if ($require_fix) {
                        $fixed_key = $this->suggestCorrectio($config_key);

                        if ($fixed_key && $fixed_key !== $config_key && !is_null($fixed_key)) {
                            $new_content = str_replace(
                                "config({$quote}{$config_key}{$quote})",
                                "config({$quote}{$fixed_key}{$quote})", // hopefully correct one :)
                                $content
                            );

                            $this->files->put($file->getRealPath(), $new_content);
                            $fix_cnt++;
                            $this->info("Fixed: {$relative_path}:{$this->getLineNumber($content, $exact_match)} - {$config_key} -> {$fixed_key}");
                        }
                    }
                }
            }
        }

        if (empty($problem_lines)) {
            $this->info('All config references are valid');
            return 0;
        }

        $this->error('Found ' . count($problem_lines) . ' config validation issues:');
        $this->table(
            ['File', 'Line', 'Issue', 'Match'],
            collect($problem_lines)->map(function ($issue) {
                return [
                    $issue['file'],
                    $issue['line'],
                    $issue['issue'],
                    $issue['match'],
                ];
            })->toArray()
        );

        if ($require_fix && $fix_cnt > 0) {
            $this->info("Automatically fixed {$fix_cnt} issue(s). Please review and commit");
        } elseif ( $require_fix ) {
            $this->error("No automated fixes found. Please review and commit");
        }

        $this->warn('Please use the --fix option to automatically fix these issues');
        return 1;
    }

    private function getLineNumber(string $content, string $search): int
    {
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (strpos($line, $search) !== false) {
                return $i + 1;
            }
        }
        return 0;
    }

    private function suggestCorrectio(string $key): ?string
    {
        $common_keys = [
            'uuid_regex' => 'app_uuid_regex',
            'app_uuid.regex' => 'app_uuid_regex',
            'api_route.uuid' => 'api_route.app_uuid_regex',
        ];

        if (isset($common_keys[$key])) {
            return $common_keys[$key];
        }

        $config_keys = array_keys(config()->all());
        $correspondent = [];

        foreach ($config_keys as $config_key) {
            similar_text($key, $config_key, $similarity);

            if ($similarity > 79) {
                $correspondent[$config_key] = $similarity;
            }
        }

        if (!empty($correspondent)) {
            arsort($correspondent);
            return key($correspondent);
        }

        return null;
    }
}

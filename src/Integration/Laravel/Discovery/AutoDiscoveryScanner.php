<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use ReflectionClass;

#[Singleton]
final readonly class AutoDiscoveryScanner
{
    public function __construct(
        private \Psr\Log\LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<DiscoveryTarget> $targets
     * @param list<string> $excluded
     *
     * @return array{resources: list<class-string<Resource>>, pages: list<class-string<PageDefinitionContract>>}
     */
    public function scan(array $targets, array $excluded = []): array
    {
        $resources = [];
        $pages = [];

        foreach ($targets as $target) {
            if (!is_dir($target->directory())) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($target->directory(), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = ltrim(
                    str_replace($target->directory(), '', $file->getPathname()),
                    DIRECTORY_SEPARATOR
                );
                $className = str_replace(
                    [DIRECTORY_SEPARATOR, '.php'],
                    ['\\', ''],
                    $relativePath,
                );
                $fqcn = $target->namespace() . '\\' . $className;
                $basename = class_basename($fqcn);

                if ($this->isExcluded($fqcn, $basename, $relativePath, $excluded)) {
                    continue;
                }

                if ($target->discoversResources() && str_ends_with($basename, Resource::DEFAULT_SUFFIX)) {
                    if ($this->isConcreteSubtype($fqcn, Resource::class)) {
                        /** @var class-string<Resource> $fqcn */
                        $resources[] = $fqcn;
                    }
                }

                if ($target->discoversPages() && str_ends_with($basename, 'Page')) {
                    if ($this->isConcreteSubtype($fqcn, PageDefinitionContract::class)) {
                        /** @var class-string<PageDefinitionContract> $fqcn */
                        $pages[] = $fqcn;
                    }
                }
            }
        }

        $resources = array_values(array_unique($resources));
        $pages = array_values(array_unique($pages));

        return [
            'resources' => $resources,
            'pages' => $pages,
        ];
    }

    /**
     * @param list<string> $excluded
     */
    private function isExcluded(string $fqcn, string $basename, string $relativePath, array $excluded): bool
    {
        return in_array($fqcn, $excluded, true)
            || in_array($basename, $excluded, true)
            || in_array(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), $excluded, true);
    }

    private function isConcreteSubtype(string $fqcn, string $contract): bool
    {
        try {
            if (!class_exists($fqcn)) {
                return false;
            }
            if (!is_subclass_of($fqcn, $contract)) {
                return false;
            }

            $reflection = new ReflectionClass($fqcn);
            if ($reflection->isAbstract()) {
                return false;
            }
        } catch (\Throwable $exception) {
            $this->logger->error('[AutoDiscoveryScanner.isConcreteSubtype] Failed to inspect candidate.', [
                'class' => $fqcn,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}

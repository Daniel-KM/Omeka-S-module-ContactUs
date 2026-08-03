<?php declare(strict_types=1);

namespace ContactUsTest\Theme;

use PHPUnit\Framework\TestCase;

/**
 * Scan the installed themes for the contact form templates that were not
 * upgraded to the flat fields introduced in version 3.4.32.
 *
 * The "fields" fieldset does not exist any more: a template calling
 * $form->get('fields') is now fatal, and a template posting "fields[name]" only
 * works through the legacy fallback of ContactSubmission, that may be removed
 * later.
 *
 * The test does not fail: the themes are not owned by the module, so the
 * offending ones are reported as a skip in order to be upgraded.
 *
 * @see \ContactUs\Stdlib\ContactSubmission::collectPostedFields()
 */
class ThemeFieldsUpgradeTest extends TestCase
{
    /**
     * Patterns of the legacy fields fieldset, with the reason to upgrade.
     */
    private const LEGACY_PATTERNS = [
        '~\$form\s*->\s*get\(\s*[\'"]fields[\'"]\s*\)~' => 'the "fields" fieldset does not exist any more (fatal error)',
        '~[\'"]fields\[~' => 'the fields are posted flat, not inside "fields[…]"',
        '~name\s*=\s*[\'"]fields\[~' => 'the fields are posted flat, not inside "fields[…]"',
    ];

    public function testInstalledThemesDoNotUseTheLegacyFieldsFieldset(): void
    {
        $themesPath = (defined('OMEKA_PATH') ? OMEKA_PATH : dirname(__DIR__, 5)) . '/themes';
        if (!is_dir($themesPath)) {
            $this->markTestSkipped('No themes directory to scan.');
        }

        $issues = [];
        foreach ($this->contactTemplates($themesPath) as $filepath) {
            $content = (string) file_get_contents($filepath);
            foreach (self::LEGACY_PATTERNS as $pattern => $reason) {
                if (preg_match($pattern, $content)) {
                    $relative = substr($filepath, strlen($themesPath) + 1);
                    $issues[$relative] = $reason;
                    break;
                }
            }
        }

        if ($issues) {
            $list = [];
            foreach ($issues as $relative => $reason) {
                $list[] = "- $relative: $reason";
            }
            $this->markTestSkipped(sprintf(
                "%d contact template(s) still use the legacy fields fieldset and should be upgraded:\n%s",
                count($issues),
                implode("\n", $list)
            ));
        }

        $this->assertSame([], $issues);
    }

    /**
     * Contact templates of the themes, excluding the build and backup dirs that
     * are not used at runtime.
     *
     * @return string[]
     */
    private function contactTemplates(string $themesPath): array
    {
        $filepaths = [];
        foreach (glob($themesPath . '/*/view', GLOB_ONLYDIR) ?: [] as $viewPath) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($viewPath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileinfo) {
                $filepath = $fileinfo->getPathname();
                if ($fileinfo->isFile()
                    && substr($filepath, -6) === '.phtml'
                    && strpos(basename($filepath), 'contact') !== false
                ) {
                    $filepaths[] = $filepath;
                }
            }
        }
        sort($filepaths);
        return $filepaths;
    }
}

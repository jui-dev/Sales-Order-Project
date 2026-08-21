<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * No view may mix the two @php styles.
 *
 * Blade stores @php...@endphp blocks before it compiles anything else, using
 * the regex /(?<!@)@php(.*?)@endphp/s. It does not distinguish the inline
 * @php(...) form from the block form, so in a file containing both, the FIRST
 * inline @php( pairs with the FIRST @endphp and everything between them is
 * swallowed into one raw block opening with "<?php(" - which is not even a
 * valid PHP open tag. The whole span is then emitted as literal text and every
 * variable it was supposed to assign is undefined.
 *
 * That is not hypothetical: products/show.blade.php had an inline @php( on line
 * 51 and a block @endphp on line 542, and the ~490 lines between them stopped
 * rendering entirely. The page died on "Undefined variable $returnTransactions"
 * - the first thing after the block to actually be compiled.
 *
 * It fails silently and far from its cause, so it is worth a test rather than a
 * convention. One style per file is all it takes.
 */
class BladePhpDirectiveTest extends TestCase
{
    public function test_no_view_mixes_inline_and_block_php_directives(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());

            $inline = preg_match('/(?<!@)@php\s*\(/', $contents);
            $block = preg_match('/(?<!@)@php\s*$/m', $contents);

            if ($inline && $block) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'These views mix inline @php(...) with @php...@endphp blocks.',
            'Blade pairs the first @php( with the first @endphp and swallows',
            'everything between them, so pick one style per file:',
            '',
            ...$offenders,
        ]));
    }

    public function test_every_view_compiles_without_leaving_a_php_directive_behind(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $compiled = Blade::compileString(File::get($file->getPathname()));

            // A surviving @php, or an open tag Blade never meant to write, is
            // the signature of the mispairing above.
            if (str_contains($compiled, '@php') || str_contains($compiled, '<?php(')) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders,
            "These views did not compile cleanly - a @php directive survived:\n".implode("\n", $offenders)
        );
    }
}
